<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;

use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\IndividualInfo;

class BakongPaymentController extends Controller
{
    protected $token;
    protected $accountId;
    protected $merchantName;
    protected $merchantCity;

    public function __construct()
    {
        $this->token   = env('BAKONG_TOKEN');
        $this->accountId = env('BAKONG_MERCHANT_ID', 'then_puthea@bkrt');
        $this->merchantName = env('BAKONG_MERCHANT_NAME', 'AngkorStay');
        $this->merchantCity = env('BAKONG_MERCHANT_CITY', 'Phnom Penh');
    }

    /**
     * 1. បង្កើត KHQR — ធ្វើនៅ local ដោយប្រើ KHQR SDK (មិនហៅ Bakong server ទេ)
     */
    public function generateQR($id)
    {
        $booking = Booking::with(['room', 'hotel'])->findOrFail($id);

        try {
            // កំណត់ឈ្មោះសណ្ឋាគារ និងជៀសវាងអក្សរវែងពេក ឬគ្មានតម្លៃ (កំណត់យ៉ាងច្រើនត្រឹម 25 តួអក្សរ)
            $rawHotelName = $booking->hotel->name ?? $this->merchantName;
            $safeMerchantName = substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $rawHotelName), 0, 25);
            if (empty($safeMerchantName)) {
                $safeMerchantName = 'AngkorStay';
            }

            $individualInfo = new IndividualInfo(
                bakongAccountID: $this->accountId,
                merchantName: $safeMerchantName,
                merchantCity: $this->merchantCity,
                currency: KHQRData::CURRENCY_USD,
                amount: (float) $booking->total_amount,
                billNumber: $booking->booking_number,
                storeLabel: $safeMerchantName,
                terminalLabel: 'Counter 01',
                // ចាំបាច់សម្រាប់ Dynamic QR (មាន amount) - timestamp គិតជា milliseconds
                expirationTimestamp: strval((int) (microtime(true) * 1000) + 10 * 60 * 1000), // ផុតកំណត់ក្នុង 10 នាទី
            );

            $result = BakongKHQR::generateIndividual($individualInfo);

            // ការទប់ទល់ទាំង object និង array ព្រោះ package version ខុសគ្នាអាចត្រឡប់ខុសគ្នា
            $resultArray = is_array($result) ? $result : (array) $result;
            $status = $resultArray['status'] ?? [];
            $statusArray = is_array($status) ? $status : (array) $status;
            $code = $statusArray['code'] ?? null;
            $data = $resultArray['data'] ?? [];
            $dataArray = is_array($data) ? $data : (array) $data;

            Log::info('Bakong Generate QR Response: ' . json_encode($resultArray));

            // responseCode 0 = success (generated locally, no network call)
            if ($code === 0) {
                $qrString = $dataArray['qr'];
                $md5      = $dataArray['md5'];

                $booking->md5_hash = $md5;
                $booking->save();

                return response()->json([
                    'success'  => true,
                    'qr_image' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrString),
                    'md5'      => $md5,
                    'amount'   => $booking->total_amount,
                ]);
            }

            Log::error('KHQR Generation Error: ' . json_encode($statusArray));
            return response()->json([
                'success' => false,
                'message' => $statusArray['message'] ?? 'Failed to generate KHQR',
            ], 400);

        } catch (\Exception $e) {
            Log::error('Bakong QR Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Exception error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 2. ពិនិត្យស្ថានភាពទូទាត់ពិត — ត្រូវការ Token, ហៅទៅ Bakong Open API ពិត
     */
    public function checkPaymentStatus($id)
    {
        $booking = Booking::findOrFail($id);

        if ($booking->payment_status === 'paid') {
            return response()->json(['status' => 'paid']);
        }

        if (!$booking->md5_hash) {
            return response()->json(['status' => 'pending']);
        }

        try {
            $bakongKHQR = new BakongKHQR($this->token);
            $response = $bakongKHQR->checkTransactionByMD5($booking->md5_hash);

            // ការទប់ទល់ទាំង object និង array ព្រោះ package version ខុសគ្នាអាចត្រឡប់ខុសគ្នា
            $responseArray = is_array($response) ? $response : (array) $response;

            // checkTransactionByMD5 ត្រឡប់ជា format flat: {"responseCode": 0, ...}
            // ខុសពី generateIndividual ដែលត្រឡប់ nested: {"status": {"code": 0}, ...}
            // ដូច្នេះត្រូវពិនិត្យទាំងពីររូបភាព ដើម្បីធានាដំណើរការទោះបីជា package ផ្លាស់ប្តូរ format
            if (array_key_exists('responseCode', $responseArray)) {
                $code = $responseArray['responseCode'];
            } else {
                $status = $responseArray['status'] ?? [];
                $statusArray = is_array($status) ? $status : (array) $status;
                $code = $statusArray['code'] ?? null;
            }

            Log::info('Bakong Check Status Response: ' . json_encode($responseArray));

            // responseCode 0 = ទូទាត់ជោគជ័យ
            if ($code === 0) {
                $booking->payment_status = 'paid';
                $booking->status = 'confirmed';
                $booking->save();

                // Create a record in the payments table for Bakong KHQR
                \App\Models\Payment::create([
                    'booking_id' => $booking->id,
                    'amount' => $booking->total_amount,
                    'payment_method' => 'bakong',
                    'transaction_id' => $booking->md5_hash, // Use MD5 hash as transaction ID
                    'status' => 'completed',
                    'paid_at' => now(),
                ]);

                return response()->json(['status' => 'paid', 'data' => $responseArray['data'] ?? null]);
            }

            return response()->json(['status' => 'pending']);

        } catch (\Exception $e) {
            Log::error('Check Payment Status Exception: ' . $e->getMessage());
            return response()->json(['status' => 'pending']);
        }
    }

    /**
     * 3. មុខងារ Demo Simulate Success (បម្រុងទុកពេលការពារ Thesis)
     */
    public function mockPaymentSuccess($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->payment_status = 'paid';
        $booking->status = 'confirmed';
        $booking->save();

        \App\Models\Payment::create([
            'booking_id' => $booking->id,
            'amount' => $booking->total_amount,
            'payment_method' => 'bakong',
            'transaction_id' => 'MOCK-' . time(),
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}