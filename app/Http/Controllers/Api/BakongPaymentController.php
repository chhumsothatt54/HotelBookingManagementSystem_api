<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Http;

class BakongPaymentController extends Controller
{
    // មុខងារបង្កើត QR Code (ហៅ Bakong API ឬ Mock)
    public function generateQR($id)
    {
        $booking = Booking::findOrFail($id);

        // ប្រសិនបើអ្នកចង់តេស្តសិនដោយមិនបាច់រង់ចាំ Token ផ្លូវការ
        // អ្នកអាច Mock ទុកបែបนี้សិនបាន
        $booking->md5_hash = 'mock_md5_' . $booking->id;
        $booking->save();

        return response()->json([
            'success' => true,
            'qr_image' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=StayNest_Mock_Payment',
            'amount' => $booking->total_price ?? 50.00
        ]);
    }

    // មុខងារឆែកស្ថានភាពបង់ប្រាក់ (Polling / ឬ Mock Check)
    public function checkPaymentStatus($id)
    {
        $booking = Booking::findOrFail($id);

        // បើ Status ជា paid ហើយ ឱ្យឆ្លើយតបថា paid
        if ($booking->payment_status === 'paid') {
            return response()->json(['status' => 'paid']);
        }

        return response()->json(['status' => 'pending']);
    }

    // មុខងារជំនួស (សម្រាប់ពេល Demo ឬពេលគ្មាន Token) ឱ្យវាប្ដូរជា Paid ភ្លាម
    public function mockPaymentSuccess($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->payment_status = 'paid';
        $booking->status = 'confirmed';
        $booking->save();

        return response()->json(['success' => true, 'message' => 'Payment marked as paid']);
    }
}
