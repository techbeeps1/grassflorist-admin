<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Mail\CustomerThankYouMail;
use App\Mail\AdminNewCustomerNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;  // Add this import
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:customers',
                'phone' => 'nullable|string|max:20',
                'password' => 'required|string|min:8',
            ]);

            $cleanPhone = null;
            if ($request->filled('phone')) {
                $rawPhone = preg_replace('/[^\d+]/', '', (string)$request->phone);
                if (!empty($rawPhone) && $rawPhone !== '9876543210') {
                    $cleanPhone = $rawPhone;
                }
            }

            $customer = Customer::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $cleanPhone,
                'country' => $request->country ?: 'Saudi Arabia',
                'password' => Hash::make($validated['password']),
            ]);

            // Send registration welcome notifications (Email & SMS) with live logging
            try {
                $reqLocale = $request->header('X-Locale')
                    ?: ($request->input('locale')
                    ?: ($request->input('lang')
                    ?: (app()->getLocale() ?: 'ar')));
                $locale = in_array(strtolower(substr((string)$reqLocale, 0, 2)), ['en', 'ar'], true) ? strtolower(substr((string)$reqLocale, 0, 2)) : 'ar';

                /** @var \App\Services\Notifications\NotificationService $notificationService */
                $notificationService = app(\App\Services\Notifications\NotificationService::class);
                $notificationService->sendUserRegistrationNotification($customer, $locale);
            } catch (\Throwable $notifEx) {
                \Illuminate\Support\Facades\Log::error('Registration notification failed: ' . $notifEx->getMessage());
                try {
                    Mail::to($customer->email)->send(new CustomerThankYouMail($customer));
                } catch (\Throwable $fallbackEx) {}
            }


        //$token = $customer->createToken('auth_token')->plainTextToken;
        
        $credentials = $request->only('email', 'password');
           
        if (! $token = auth('customer')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'access_token' => $token,
            'customer' => [
            'id' => auth('customer')->id(),
            'name' => auth('customer')->user()->first_name,
        ],
        ]);
    }
    catch (\Exception $e) {
        logger()->error('Registration error:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function login(Request $request)
{
    try {

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');

        if (! $token = auth('customer')->attempt($credentials)) {
            return response()->json([
                'error' => 'The provided credentials are incorrect.',
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        /** @var \App\Models\Customer|null $customer */
        $customer = auth('customer')->user();
        if (!$customer || !$customer->is_active) {
            try {
                auth('customer')->setToken($token)->invalidate();
            } catch (\Throwable $t) {}

            return response()->json([
                'status' => 'error',
                'code' => 'account_deactivated',
                'error' => 'Your account has been deactivated. Please contact support.',
                'message' => 'Your account has been deactivated. Please contact support.',
            ], 403);
        }

        return response()->json([
            'access_token' => $token,
            'customer' => [
            'id' => auth('customer')->id(),
            'name' => auth('customer')->user()->first_name,
        ],
        ]);

    } catch (\Exception $e) {

        logger()->error('Login error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'error' => $e->getMessage(),
        ], 500);
    }
}

    public function logout(Request $request)
    {   
        try{
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
        }
         catch (\Exception $e) {
            logger()->error('Registration error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function user(Request $request)
    {   
        try {
            $user = $request->user();
            if (!$user || !$user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'account_deactivated',
                    'message' => 'Account is deactivated or not found.'
                ], 401);
            }
            if ($user && ($user->phone === '9876543210' || $user->phone === '0500000000')) {
                $user->phone = null;
                try {
                    Customer::where('id', $user->id)->whereIn('phone', ['9876543210', '0500000000'])->update(['phone' => null]);
                } catch (\Throwable $t) {}
            }
            $userData = $user ? $user->toArray() : [];
            if ($user) {
                $userData['email_verified'] = !is_null($user->email_verified_at);
                $userData['email_verified_at'] = $user->email_verified_at ? $user->email_verified_at->toIso8601String() : null;
            }
            return response()->json($userData);
        }
        catch (\Exception $e) {
            logger()->error('User profile error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function viewAddress($user_id)
{   
    try{
        $customer = Customer::where('id', $user_id)->first();

        if ($customer) {
            // Remove sensitive or unwanted fields
            $customerData = $customer->toArray();
            
            // Remove created_at and updated_at if they exist
            unset($customerData['created_at']);
            unset($customerData['updated_at']);
            
            // You can also remove other fields if needed
            // unset($customerData['password']);
            // unset($customerData['remember_token']);
            
            return response()->json([
                'success' => True,
                'data' => $customerData
            ], 200);
        } else {
            return response()->json(['error' => "User not found"], 404);
        }
    }
    catch (\Exception $e) {
        logger()->error('View address error:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    public function checkuser(Request $request)
    {   
        try{
        $customer = Customer::where('email', $request->email)->first();

        if ($customer) {
                return response()->json(['success' => "User found"], 200);
        } else {
                 return response()->json(['error' => "New user"], 500);
        }

        }
        catch (\Exception $e) {
            logger()->error('Registration error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }

        
        
    }


     public function updateuser(Request $request)
    {
            try {
                $validated = $request->validate([
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'sometimes|nullable|string|max:255',
                    'phone' => 'sometimes|nullable|string',
                    'country' => 'sometimes|nullable|string',
                ]);

                $cleanPhone = null;
                if ($request->filled('phone')) {
                    $raw = trim((string)$request->phone);
                    if ($raw !== '' && $raw !== '9876543210' && $raw !== '0500000000') {
                        $cleanPhone = $raw;
                    }
                }

                // Strictly protect registered email: find by user_id or auth customer or existing email
                $customer = null;
                if ($request->filled('user_id')) {
                    $customer = Customer::find($request->user_id);
                }
                if (!$customer && auth('customer')->check()) {
                    $customer = auth('customer')->user();
                }
                if (!$customer && $request->filled('email')) {
                    $customer = Customer::where('email', $request->email)->first();
                }

                if ($customer) {
                    $customer->update([
                        'first_name' => $validated['first_name'],
                        'last_name' => $validated['last_name'] ?? '',
                        'phone' => $cleanPhone,
                        'city' => $request['city'] ?? $customer->city,
                        'district' => $request['district'] ?? $customer->district,
                        'address' => $request['address'] ?? $customer->address,
                        'address_2' => $request['address_2'] ?? $customer->address_2,
                        'zip_code' => $request['zip_code'] ?? $customer->zip_code,
                        'state' => $request['state'] ?? $customer->state,
                        'date_of_birth' => $request['date_of_birth'] ?? $customer->date_of_birth,
                        'country' => $request['country'] ?? $customer->country ?? 'Saudi Arabia',
                        // Registered email is non-modifiable
                    ]);
                }

                $customerData = $customer ? $customer->toArray() : [];
                if ($customer) {
                    $customerData['email_verified'] = !is_null($customer->email_verified_at);
                    $customerData['email_verified_at'] = $customer->email_verified_at ? $customer->email_verified_at->toIso8601String() : null;
                }

                return response()->json([
                    'customer' => $customerData,
                ]);
            }
            catch (\Exception $e) {
                logger()->error('Registration error:', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['error' => $e->getMessage()], 500);
            }
    }
    public function passwordchange(Request $request)
    {
        try {
            $customer = auth('customer')->user();
            if (!$customer && $request->filled('email')) {
                $customer = Customer::where('email', $request->email)->first();
            }

            if (!$customer) {
                return response()->json(['error' => 'Customer unauthenticated or not found.'], 401);
            }

            $validated = $request->validate([
                'current_password' => 'nullable|string',
                'password' => 'required|string|min:8',
                'password_confirmation' => 'required|string|same:password',
            ]);

            // If current_password is provided, check if it matches existing password
            if (!empty($validated['current_password'])) {
                if (!\Illuminate\Support\Facades\Hash::check($validated['current_password'], $customer->password)) {
                    return response()->json([
                        'message' => 'The provided current password does not match our records.',
                        'error' => 'The provided current password does not match our records.'
                    ], 422);
                }
            }

            $customer->update([
                'password' => bcrypt($validated['password']),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password changed successfully.',
                'success' => 'Password Change'
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation error',
                'error' => collect($e->errors())->flatten()->first() ?? 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            logger()->error('Password change error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

            /**
             * Silent token refresh for customers.
             * Exchanges an expired access_token for a fresh one as long as it's within refresh_ttl.
             */
            public function refresh(Request $request)
            {
                try {
                    $token = JWTAuth::getToken();
                    if (!$token) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Token not provided'
                        ], 401);
                    }

                    // Refresh the token (even if access token expired, if within refresh_ttl)
                    $newToken = auth('customer')->setToken($token)->refresh();
                    $user = auth('customer')->setToken($newToken)->user();

                    if (!$user || !$user->is_active) {
                        try {
                            auth('customer')->setToken($newToken)->invalidate();
                        } catch (\Throwable $t) {}

                        return response()->json([
                            'status' => 'error',
                            'code' => 'account_deactivated',
                            'message' => 'Account has been deactivated or removed.',
                            'error' => 'Your account is inactive or has been deleted. Please login again.'
                        ], 401);
                    }

                    // Check token version
                    try {
                        $payload = JWTAuth::setToken($token)->getPayload();
                        $tokenVersionInClaim = $payload?->get('tv');
                        if ($tokenVersionInClaim !== null && (int)$tokenVersionInClaim !== (int)($user->token_version ?? 1)) {
                            try {
                                auth('customer')->setToken($newToken)->invalidate();
                            } catch (\Throwable $t) {}

                            return response()->json([
                                'status' => 'error',
                                'code' => 'session_revoked',
                                'message' => 'Session has been invalidated. Please log in again.',
                                'error' => 'Session invalidated'
                            ], 401);
                        }
                    } catch (\Throwable $t) {}

                    return response()->json([
                        'status' => 'success',
                        'access_token' => $newToken,
                        'token_type' => 'bearer',
                        'expires_in' => auth('customer')->factory()->getTTL() * 60,
                        'customer' => [
                            'id' => $user->id,
                            'name' => $user->first_name,
                        ],
                    ], 200);

                } catch (TokenExpiredException $e) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Refresh token has expired',
                        'error' => 'Please login again'
                    ], 401);
                } catch (TokenInvalidException $e) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid token',
                        'error' => 'The provided token is invalid'
                    ], 401);
                } catch (JWTException $e) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Token refresh error',
                        'error' => $e->getMessage()
                    ], 401);
                } catch (\Exception $e) {
                    \Log::error('Token refresh error:', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unable to refresh token',
                        'error' => 'Please login again'
                    ], 401);
                }
            }
            public function checkAuth()
            {
                try {
                    // Get the token from the request
                    $token = JWTAuth::getToken();
                    
                    if (!$token) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Token not provided'
                        ], 401);
                    }

                    // Authenticate the user using the token
                    $user = auth('customer')->setToken($token)->authenticate();
                    
                    if (!$user || !$user->is_active) {
                        try {
                            JWTAuth::setToken($token)->invalidate();
                        } catch (\Throwable $t) {}

                        return response()->json([
                            'status' => 'error',
                            'code' => 'account_deactivated',
                            'message' => 'User not found or account deactivated',
                            'error' => 'Account inactive'
                        ], 401);
                    }

                    // Verify token version
                    try {
                        $payload = JWTAuth::getPayload($token);
                        $claimVersion = $payload?->get('tv');
                        if ($claimVersion !== null && (int)$claimVersion !== (int)($user->token_version ?? 1)) {
                            try {
                                JWTAuth::setToken($token)->invalidate();
                            } catch (\Throwable $t) {}

                            return response()->json([
                                'status' => 'error',
                                'code' => 'session_revoked',
                                'message' => 'Your session has expired. Please log in again.',
                                'error' => 'Session invalidated'
                            ], 401);
                        }
                    } catch (\Throwable $t) {}

                    // Return user details (excluding sensitive fields)
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Token is valid',
                        'data' => [
                            'id' => $user->id,
                            'name' => $user->first_name
                            
                        ]
                    ], 200);

                } catch (TokenExpiredException $e) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Token has expired',
                        'error' => 'Please login again to get a new token'
                    ], 401);
                    
                } catch (TokenInvalidException $e) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid token',
                        'error' => 'The provided token is invalid'
                    ], 401);
                    
                } catch (JWTException $e) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Token error',
                        'error' => $e->getMessage()
                    ], 401);
                    
                } catch (\Exception $e) {
                    \Log::error('Auth check error:', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Server error',
                        'error' => 'Unable to verify token. Please try again later.'
                    ], 500);
                }
            }

    /**
     * Send email verification link to customer
     */
    public function sendVerificationEmail(Request $request)
    {
        try {
            $customer = auth('customer')->user() ?: $request->user();
            if (!$customer && $request->filled('email')) {
                $customer = Customer::where('email', $request->email)->first();
            }
            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found or unauthenticated.'
                ], 401);
            }

            if (!empty($customer->email_verified_at)) {
                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'is_verified' => true,
                    'message' => 'Your email address is already verified.',
                ]);
            }

            $token = Str::random(48);
            $customer->email_verification_token = $token;
            $customer->save();

            $reqLocale = $request->header('X-Locale')
                ?: ($request->input('locale')
                ?: ($request->input('lang')
                ?: (app()->getLocale() ?: 'ar')));
            $locale = in_array(strtolower(substr((string)$reqLocale, 0, 2)), ['en', 'ar'], true)
                ? strtolower(substr((string)$reqLocale, 0, 2))
                : 'ar';

            $origin = $request->header('origin') ?: ($request->header('referer') ? preg_replace('#^([^:]+://[^/]+).*$#', '$1', $request->header('referer')) : null);
            $frontendUrl = $origin ?: env('FRONTEND_URL', 'http://localhost:3000');
            $prefix = ($locale === 'en') ? '/en/account' : '/account';
            $verificationUrl = rtrim($frontendUrl, '/') . $prefix . '?tab=profile&verify_token=' . $token;

            /** @var \App\Services\Notifications\NotificationService $notificationService */
            $notificationService = app(\App\Services\Notifications\NotificationService::class);
            $notifRes = $notificationService->sendEmailVerificationNotification($customer, $verificationUrl, $locale);

            if (!($notifRes['success'] ?? false) && !empty($notifRes['errors'])) {
                $errMsg = implode(', ', (array)$notifRes['errors']);
                \Log::error('Send verification email error: ' . $errMsg);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to send verification email: ' . $errMsg,
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => $locale === 'ar'
                    ? 'تم إرسال رابط تأكيد البريد الإلكتروني بنجاح. يرجى مراجعة صندوق الوارد.'
                    : 'A verification link has been sent to your email address. Please check your inbox.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Send verification email error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send verification email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify customer email via token
     */
    public function verifyEmail(Request $request)
    {
        try {
            $token = $request->input('token') ?? $request->query('token');
            if (empty($token)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Verification token is required.'
                ], 400);
            }

            $customer = Customer::where('email_verification_token', $token)->first();
            if (!$customer) {
                $authCustomer = auth('customer')->user() ?: $request->user();
                if ($authCustomer && !empty($authCustomer->email_verified_at)) {
                    return response()->json([
                        'status' => 'success',
                        'success' => true,
                        'is_verified' => true,
                        'message' => 'Email address is already verified.',
                    ]);
                }
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid or expired verification token.',
                ], 400);
            }

            $customer->email_verified_at = now();
            $customer->email_verification_token = null;
            $customer->save();

            return response()->json([
                'status' => 'success',
                'success' => true,
                'is_verified' => true,
                'message' => 'Your email address has been verified successfully!',
            ]);
        } catch (\Exception $e) {
            \Log::error('Verify email error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Verification failed. Please try again.',
            ], 500);
        }
    }
}
