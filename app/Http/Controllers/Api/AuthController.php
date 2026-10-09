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

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:customers',
            'phone' => ['required', 'string', 'regex:/^(\+91[\-\s]?)?[0]?[6-9]\d{9}$/'],
            'password' => 'required|string|min:8',
        ], [
            'phone.required' => 'Mobile number is required.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number.',
        ]);

        $cleanPhone = preg_replace('/\D/', '', (string)$request->phone);
        if (str_starts_with($cleanPhone, '91') && strlen($cleanPhone) > 10) {
            $cleanPhone = substr($cleanPhone, 2);
        } elseif (str_starts_with($cleanPhone, '0') && strlen($cleanPhone) > 10) {
            $cleanPhone = substr($cleanPhone, 1);
        }

        $customer = Customer::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $cleanPhone,
            'country' => 'India',
            // 'city' => $request['city'],
            // 'address' => $request['address'],
            // 'zip_code' => $request['zip_code'],
            // 'date_of_birth' => $request['date_of_birth'],
            'password' => Hash::make($validated['password']),
        ]);

            // Send thank you email to customer
            Mail::to($customer->email)->send(new CustomerThankYouMail($customer));

            // Send notification to admin
            $adminEmail = config('mail.admin_email'); // Make sure to add this to your .env
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new AdminNewCustomerNotification($customer));
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
        try{
        return response()->json($request->user());
        }
        catch (\Exception $e) {
            logger()->error('Registration error:', [
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
                    'last_name' => 'required|string|max:255',
                    'phone' => 'sometimes|nullable|string',
                    'country' => 'sometimes|nullable|string',
                ]);

                $cleanPhone = $request->phone;

                // Find by email or user_id
                $customer = Customer::where('email', $request->email)->first();
                if (!$customer && $request->filled('user_id')) {
                    $customer = Customer::find($request->user_id);
                }

                if ($customer) {
                    $customer->update([
                        'first_name' => $validated['first_name'],
                        'last_name' => $validated['last_name'],
                        'phone' => $cleanPhone ?? $customer->phone,
                        'city' => $request['city'] ?? $customer->city,
                        'district' => $request['district'] ?? $customer->district,
                        'address' => $request['address'] ?? $customer->address,
                        'address_2' => $request['address_2'] ?? $customer->address_2,
                        'zip_code' => $request['zip_code'] ?? $customer->zip_code,
                        'state' => $request['state'] ?? $customer->state,
                        'date_of_birth' => $request['date_of_birth'] ?? $customer->date_of_birth,
                        'country' => $request['country'] ?? $customer->country ?? 'Saudi Arabia',
                    ]);
                }

                

                return response()->json([
                    
                    'customer' => $customer,
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
                            
                                $validated = $request->validate([
                                'password' => 'required|string|min:8',
                                'password_confirmation' => 'required|string|same:password',
                                ]);

                            $customer = Customer::where('email', $request['email'])->first();


                            if ($customer) {
                            $customer->update([
                                'password' => bcrypt($validated['password_confirmation']),
                            ]);
                            }

                        

                       return response()->json(['success' => "Password Change"], 200);

                    }
                    catch (\Exception $e) {
                        logger()->error('Registration error:', [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        return response()->json(['error' => $e->getMessage()], 500);
                    }
            }

            public function refresh()
            {
                try {
                    $token = auth('customer')->refresh();

                    return response()->json([
                        'access_token' => $token,
                        'token_type' => 'Bearer',
                        'expires_in' => auth('customer')->factory()->getTTL() * 60,
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Token cannot be refreshed'
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
                    
                    if (!$user) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'User not found'
                        ], 404);
                    }

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
}
