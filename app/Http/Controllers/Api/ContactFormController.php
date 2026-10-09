<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactFormRequest;
use App\Mail\ContactFormMail;
use App\Mail\TutorFormSubmitted;
use App\Mail\VendorRegistrationSubmitted;
use App\Mail\ProductRequestSubmitted;
use App\Models\ContactPage;
use App\Models\ContactInquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ContactFormController extends Controller
{
    public function send(ContactFormRequest $request)
    {
        $validated = $request->validated();

        $name = !empty($validated['name'])
            ? trim($validated['name'])
            : trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? ''));

        if (empty($name)) {
            $name = 'Guest Visitor';
        }

        $email = $validated['email'];
        $phone = $validated['phone'] ?? null;
        $subject = $validated['subject'] ?? null;
        $message = $validated['message'] ?? ($validated['emailMessage'] ?? '');

        // 1. Save submission to database
        $inquiry = ContactInquiry::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'subject' => $subject,
            'message' => $message,
            'ip_address' => $request->ip(),
            'status' => 'new',
        ]);

        // 2. Fetch destination email and subject template from Contact Page settings
        $contactPage = ContactPage::first();
        $recipientSetting = $contactPage?->notification_email
            ?: ($contactPage?->email ?: ($contactPage?->con_email ?: config('mail.from.address', 'info@grassflorist.com')));

        $configuredSubject = $contactPage?->email_subject ?: 'New Contact Inquiry from Grass Florist Website';
        $finalSubject = !empty($subject) ? "{$configuredSubject}: {$subject}" : $configuredSubject;

        $recipients = array_values(array_filter(array_map('trim', explode(',', $recipientSetting))));
        if (empty($recipients)) {
            $recipients = ['info@grassflorist.com'];
        }

        // 3. Send email notification
        try {
            Mail::to($recipients)
                ->send(new ContactFormMail(
                    $name,
                    $email,
                    $phone,
                    $finalSubject,
                    $message
                ));
        } catch (\Throwable $e) {
            Log::error('[ContactFormController] Failed to send contact email notification: ' . $e->getMessage(), [
                'inquiry_id' => $inquiry->id,
                'recipients' => $recipients,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your message has been sent successfully! Our concierge will contact you shortly.',
            'inquiry_id' => $inquiry->id,
        ], 200);
    }

    /**
     * Handle Tutor Form Submission
     */
    public function submitTutorForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'role' => 'required',
            'locality' => 'required|string|max:255',
            'city' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            Mail::to('qasimmizbah@gmail.com')->send(new TutorFormSubmitted($request->all()));
            return response()->json(['message' => 'Tutor form submitted successfully!']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Handle Vendor Registration Form Submission
     */
    public function submitVendorForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            Mail::to('qasimmizbah@gmail.com')->send(new VendorRegistrationSubmitted($request->all()));
            return response()->json(['message' => 'Vendor registration submitted successfully!']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Handle Product Request Form Submission
     */
    public function submitProductRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'request' => 'required|string',
            'remark' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $data = $request->all();
            
            // Handle image upload if present
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('product_requests', 'public');
                $data['image_path'] = $imagePath;
            }

            Mail::to('qasimmizbah@gmail.com')->send(new ProductRequestSubmitted($data));
            return response()->json(['message' => 'Product request submitted successfully!']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }
}