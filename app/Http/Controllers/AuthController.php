<?php

namespace App\Http\Controllers;

use App\Mail\ForgotPasswordOtpMail;
use App\Mail\VerifyEmailMail;
use App\Models\EmailVerification;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Exception;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', 'in:hotel_manager,customer'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'customer',
            'status' => 'active',
        ]);

        $token = Str::random(64);

        EmailVerification::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        // Send Email
        Mail::to($user->email)->send(new VerifyEmailMail($user->email, $token));

        return response()->json([
            'message' => 'Register successful. Please verify your email.',
            'user' => $user,
            'verification_token' => $token,  // remove in production
        ], 201);
    }

    public function sendMail(Request $request)
    {
        $rawEmail = $request->input('email') ?? $request->query('email');
        if (!$rawEmail) {
            return response()->json(['message' => 'Email parameter is required'], 400);
        }
        $email = str_replace(' ', '+', trim($rawEmail));
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
                'debug_email_received' => $email
            ], 404);
        }
        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email is already verified'], 400);
        }
        $token = Str::random(64);
        EmailVerification::updateOrCreate(
            [
                'user_id' => $user->id,
            ],
            [
                'token' => $token,
                'expires_at' => Carbon::now()->addMinutes(30),
                'verified_at' => null,
            ]
        );
        try {
            Mail::to($user->email)->send(new VerifyEmailMail($user->email, $token));
        } catch (Exception $e) {
            \Log::error('Failed to send email: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to send email logic'], 500);
        }
        if ($request->isMethod('get')) {
            return redirect('https://mail.google.com/mail/u/0/#inbox');
        }
        return response()->json([
            'message' => 'Verification email resent successfully.',
            'verification_token' => $token,
        ]);
    }

    public function resendMail(Request $request)
    {
        return $this->sendMail($request);
    }


    public function confirmMail(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Token is required'], 400);
            }
            return redirect('http://localhost:5173/login?status=invalid_token');
        }
        $verification = EmailVerification::where('token', $token)
            ->whereNull('verified_at')
            ->first();

        if (!$verification) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid or already used token'], 404);
            }
            return redirect('http://localhost:5173/login?status=invalid_token');
        }
        if (Carbon::parse($verification->expires_at)->isPast()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Token has expired'], 400);
            }
            return redirect('http://localhost:5173/login?status=expired');
        }
        DB::transaction(function () use ($verification) {
            if ($verification->user) {
                $verification->user->update([
                    'email_verified_at' => now(),
                ]);
            }

            $verification->update([
                'verified_at' => now(),
            ]);
        });
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Email verified successfully']);
        }

        return redirect('http://localhost:5173/login?verified=true');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password',
            ], 401);
        }

        if (!$user->email_verified_at) {
            return response()->json([
                'message' => 'Please verify your email first',
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active',
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request
            ->user()
            ->currentAccessToken()
            ->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $otp = rand(100000, 999999);

        PasswordResetOtp::updateOrCreate(
            [
                'user_id' => $user->id,
            ],
            [
                'otp' => $otp,
                'expires_at' => Carbon::now()->addMinutes(10),
                'verified_at' => null,
            ]
        );

        // Send OTP Email
        Mail::to($user->email)->send(new ForgotPasswordOtpMail($otp));

        return response()->json([
            'message' => 'OTP sent successfully',
            'otp' => $otp,  // remove in production
        ]);
    }

    public function resendOtp(Request $request)
    {
        return $this->forgotPassword($request);
    }

    public function confirmOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $passwordResetOtp = PasswordResetOtp::where('user_id', $user->id)
            ->where('otp', $request->otp)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (!$passwordResetOtp) {
            return response()->json([
                'message' => 'Invalid OTP',
            ], 400);
        }

        if (Carbon::parse($passwordResetOtp->expires_at)->isPast()) {
            return response()->json([
                'message' => 'OTP expired',
            ], 400);
        }

        $passwordResetOtp->update([
            'verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'OTP verified successfully',
        ]);
    }

    public function setNewPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:7', 'confirmed'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $otp = PasswordResetOtp::where('user_id', $user->id)
            ->whereNotNull('verified_at')
            ->latest()
            ->first();

        if (!$otp) {
            return response()->json([
                'message' => 'Please verify OTP first',
            ], 403);
        }

        if (Carbon::parse($otp->expires_at)->isPast()) {
            return response()->json([
                'message' => 'OTP verification expired',
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Delete used OTP
        $otp->delete();

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check(
            $request->current_password,
            $user->password
        )) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    public function updateProfile(Request $request)
    {
        // Get currently authenticated user
        $user = $request->user();

        // Validate input
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:1',
                'max:100',
            ],
            'phone' => [
                'nullable',
                'string',
                'min:8',
                'max:20',
            ],
            'avatar' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        // Upload avatar
        if ($request->hasFile('avatar')) {
            // Delete old avatar
            if (
                $user->avatar &&
                file_exists(public_path($user->avatar))
            ) {
                unlink(public_path($user->avatar));
            }

            $file = $request->file('avatar');

            $fileName = time() . '_' . uniqid() . '.'
                . $file->getClientOriginalExtension();

            $file->move(
                public_path('uploads/avatars'),
                $fileName
            );

            $validated['avatar'] = 'uploads/avatars/' . $fileName;
        } else {
            // Remove avatar from validated array
            unset($validated['avatar']);
        }

        // Update profile
        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $user->fresh(),
        ], 200);
    }
}
