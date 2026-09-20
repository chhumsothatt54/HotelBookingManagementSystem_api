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
            // កំណត់ឈ្មោះសណ្ឋាគារ និងជៀសវាងអក្សរវែងពេក ឬគ្មានតម្លៃ (កំណត់យ៉ាងច្រើនត្រឹម 25 តួអក្សร)
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
                terminalLabel: 'Counter 01'
            );

            $result = BakongKHQR::generateIndividual($individualInfo);

            if ($result->status['code'] === 0) {
                $qrString = $result->data['qr'];
                $md5      = $result->data['md5'];

                $booking->md5_hash = $md5;
                $booking->save();

                return response()->json([
                    'success'  => true,
                    'qr_image' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrString),
                    'md5'      => $md5,
                    'amount'   => $booking->total_amount,
                ]);
            }

            Log::error('KHQR Generation Error: ' . json_encode($result->status));
            return response()->json([
                'success' => false,
                'message' => $result['status']['message'] ?? 'Failed to generate KHQR',
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

            // responseCode 0 = ទូទាត់ជោគជ័យ
            if ($response->status['code'] === 0) {
                $booking->payment_status = 'paid';
                $booking->status = 'confirmed';
                $booking->save();

                return response()->json(['status' => 'paid', 'data' => $response->data]);
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

        return response()->json(['success' => true]);
    }
}