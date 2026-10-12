<?php

namespace App\Services\Notifications;

use App\Models\GlobalSetting;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MsegatSmsService
{
    protected const API_SEND_URL = 'https://www.msegat.com/gw/sendsms.php';
    protected const API_CREDITS_URL = 'https://www.msegat.com/gw/Credits.php';

    /**
     * Map Msegat error response codes to user-friendly messages
     */
    protected static array $errorCodes = [
        '1' => 'Success / Message Sent',
        'M0000' => 'Success / Message Sent',
        'M0001' => 'Variables missing (userName, apiKey, userSender, numbers, or msg missing)',
        'M0002' => 'Invalid username or apiKey credentials',
        'M0022' => 'Exceeded SMS sending frequency limit',
        'M0023' => 'Sender Name is not approved / active in Msegat account',
        'M0024' => 'Invalid Sender Name',
        'M0025' => 'Sender Name must be English alphanumeric characters',
        'M0027' => 'You cannot send this message text',
        '1010' => 'Invalid destination mobile number format',
        '1020' => 'Invalid text encoding',
        '1050' => 'Too many recipient numbers in a single batch',
        '1060' => 'Insufficient SMS account balance (رصيد الحساب غير كافٍ)',
        '1061' => 'SMS gateway dispatch failed',
        '1064' => 'Free SMS credit threshold exceeded',
    ];

    /**
     * Normalize phone number to standard Saudi / international format (e.g. 9665XXXXXXXX)
     */
    public static function normalizePhoneNumber(string $phone): string
    {
        // Remove spaces, dashes, dots, parentheses, and leading plus
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // If starts with 00966, remove leading 00
        if (str_starts_with($cleaned, '00966')) {
            $cleaned = substr($cleaned, 2);
        }

        // If starts with 05 (Saudi local mobile), convert to 9665...
        if (str_starts_with($cleaned, '05') && strlen($cleaned) === 10) {
            $cleaned = '966' . substr($cleaned, 1);
        }

        // If 9 digits starting with 5 (Saudi mobile without leading 0), add 966
        if (str_starts_with($cleaned, '5') && strlen($cleaned) === 9) {
            $cleaned = '966' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Normalize multiple comma or newline-separated phone numbers
     */
    public static function normalizePhoneNumbers(string|array $phones): array
    {
        if (is_string($phones)) {
            $phones = preg_split('/[\r\n,]+/', $phones);
        }

        $result = [];
        foreach ($phones as $p) {
            $p = trim((string)$p);
            if (!empty($p)) {
                $normalized = self::normalizePhoneNumber($p);
                if (!empty($normalized)) {
                    $result[] = $normalized;
                }
            }
        }

        return array_unique($result);
    }

    /**
     * Send SMS via Msegat Gateway
     *
     * @param string|array $recipients Single number or array/string of numbers
     * @param string $message The message text
     * @param string|null $eventKey Event identifier for tracking (e.g., 'order_confirmed', 'contact_us')
     * @param string|null $ipAddress Client IP address
     * @return array Result array ['success' => bool, 'code' => string, 'message' => string, 'raw' => string]
     */
    public function send(
        string|array $recipients,
        string $message,
        ?string $eventKey = null,
        ?string $ipAddress = null,
        ?int $orderId = null
    ): array {
        $settings = GlobalSetting::current();

        $userName = trim((string)($settings->msegat_username ?: config('services.msegat.username')));
        $apiKey = trim((string)($settings->msegat_api_key ?: config('services.msegat.api_key')));
        $userSender = trim((string)($settings->msegat_user_sender ?: config('services.msegat.sender', 'Grass')));
        $isEnabled = (bool)($settings->msegat_sms_enabled ?? true);

        $normalizedNumbers = self::normalizePhoneNumbers($recipients);
        $numbersString = implode(',', $normalizedNumbers);

        // Pre-flight check
        if (empty($normalizedNumbers)) {
            $errorMsg = 'No valid recipient phone numbers provided';
            $this->logDispatch('failed', $recipients, $userSender, $message, $eventKey, $errorMsg, null, $ipAddress);
            return [
                'success' => false,
                'code' => 'INVALID_RECIPIENT',
                'message' => $errorMsg,
            ];
        }

        if (!$isEnabled) {
            $errorMsg = 'Msegat SMS gateway is disabled in admin settings';
            $this->logDispatch('failed', $numbersString, $userSender, $message, $eventKey, $errorMsg, null, $ipAddress);
            return [
                'success' => false,
                'code' => 'GATEWAY_DISABLED',
                'message' => $errorMsg,
            ];
        }

        if (empty($userName) || empty($apiKey) || empty($userSender)) {
            $errorMsg = 'Msegat credentials incomplete (userName, apiKey, or userSender missing in Global Settings)';
            $this->logDispatch('failed', $numbersString, $userSender, $message, $eventKey, $errorMsg, null, $ipAddress, $orderId);
            return [
                'success' => false,
                'code' => 'CONFIG_MISSING',
                'message' => $errorMsg,
            ];
        }

        $payload = [
            'userName' => $userName,
            'apiKey' => $apiKey,
            'userSender' => $userSender,
            'numbers' => $numbersString,
            'userMessage' => $message,
            'msg' => $message,
            'msgEncoding' => 'UTF8',
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(15)
                ->post(self::API_SEND_URL, $payload);

            $rawBody = trim((string)$response->body());
            $parsedJson = null;

            // Msegat can return raw string "1" or JSON like {"code":"1","message":"Success"}
            if (str_starts_with($rawBody, '{')) {
                $parsedJson = json_decode($rawBody, true);
                $respCode = (string)($parsedJson['code'] ?? $rawBody);
                $respMsg = (string)($parsedJson['message'] ?? (self::$errorCodes[$respCode] ?? $rawBody));
            } else {
                $respCode = $rawBody;
                $respMsg = self::$errorCodes[$respCode] ?? "Response code: {$respCode}";
            }

            $isSuccess = in_array($respCode, ['1', 'M0000'], true);

            $status = $isSuccess ? 'sent' : 'failed';
            $errorDetail = $isSuccess ? null : ($respMsg . ' (Raw: ' . $rawBody . ')');

            $this->logDispatch(
                $status,
                $numbersString,
                $userSender,
                $message,
                $eventKey,
                $errorDetail,
                $parsedJson ?? ['raw_response' => $rawBody, 'http_status' => $response->status()],
                $ipAddress,
                $orderId
            );

            return [
                'success' => $isSuccess,
                'code' => $respCode,
                'message' => $respMsg,
                'raw' => $rawBody,
            ];
        } catch (\Throwable $e) {
            $errorDetail = 'Msegat Connection Exception: ' . $e->getMessage();
            Log::error('[MsegatSmsService] ' . $errorDetail, ['numbers' => $numbersString]);

            $this->logDispatch(
                'failed',
                $numbersString,
                $userSender,
                $message,
                $eventKey,
                $errorDetail,
                ['exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()],
                $ipAddress,
                $orderId
            );

            return [
                'success' => false,
                'code' => 'EXCEPTION',
                'message' => $errorDetail,
            ];
        }
    }

    /**
     * Check available SMS Credits balance in Msegat account
     */
    public function checkCredits(): array
    {
        $settings = GlobalSetting::current();
        $userName = trim((string)($settings->msegat_username ?: config('services.msegat.username')));
        $apiKey = trim((string)($settings->msegat_api_key ?: config('services.msegat.api_key')));

        if (empty($userName) || empty($apiKey)) {
            return [
                'success' => false,
                'message' => 'Msegat credentials (userName or apiKey) not configured.',
                'credits' => null,
            ];
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(self::API_CREDITS_URL, [
                    'userName' => $userName,
                    'apiKey' => $apiKey,
                ]);

            $rawBody = trim((string)$response->body());

            // If response is numeric, it is the balance
            if (is_numeric($rawBody)) {
                return [
                    'success' => true,
                    'message' => 'Active balance: ' . $rawBody . ' SMS credits',
                    'credits' => (float)$rawBody,
                ];
            }

            $readableError = self::$errorCodes[$rawBody] ?? $rawBody;
            return [
                'success' => false,
                'message' => 'Failed to retrieve balance: ' . $readableError,
                'credits' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
                'credits' => null,
            ];
        }
    }

    /**
     * Record log entry to notification_logs table
     */
    protected function logDispatch(
        string $status,
        string $recipient,
        ?string $sender,
        string $content,
        ?string $eventKey,
        ?string $errorMessage = null,
        ?array $responsePayload = null,
        ?string $ipAddress = null,
        ?int $orderId = null
    ): void {
        try {
            NotificationLog::create([
                'order_id' => $orderId,
                'channel' => 'sms',
                'event_key' => $eventKey,
                'recipient' => $recipient,
                'subject_or_sender' => $sender,
                'content' => $content,
                'status' => $status,
                'error_message' => $errorMessage,
                'response_payload' => $responsePayload,
                'ip_address' => $ipAddress ?? request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[MsegatSmsService] Failed to record notification log: ' . $e->getMessage());
        }
    }
}
