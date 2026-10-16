<?php

namespace App\Services\Webhooks;

use App\Models\GlobalSetting;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class WebhookService
{
    public const EVENT_ORDER_CREATED = 'order.created';
    public const EVENT_ORDER_STATUS_UPDATED = 'order.status_updated';
    public const EVENT_ORDER_PAID = 'order.paid';
    public const EVENT_USER_REGISTERED = 'user.registered';
    public const EVENT_SUBSCRIPTION_CREATED = 'subscription.created';
    public const EVENT_NEWSLETTER_SUBSCRIBED = 'newsletter.subscribed';
    public const EVENT_USER_PASSWORD_CHANGED = 'user.password_changed';
    public const EVENT_TEST_PING = 'test.ping';

    /**
     * Get all supported webhook events with user-friendly descriptions.
     */
    public static function getAvailableEvents(): array
    {
        return [
            self::EVENT_ORDER_CREATED => 'Order Created (Triggered when a customer places a new order)',
            self::EVENT_ORDER_STATUS_UPDATED => 'Order Status Updated (Triggered on any order status transition)',
            self::EVENT_ORDER_PAID => 'Order Paid (Triggered when payment is successfully confirmed)',
            self::EVENT_USER_REGISTERED => 'New Customer Registered (Triggered when a new user registers)',
            self::EVENT_SUBSCRIPTION_CREATED => 'Subscription Created (Triggered when a new floral subscription is purchased)',
            self::EVENT_NEWSLETTER_SUBSCRIBED => 'Newsletter Subscribed (Triggered when someone joins the mailing list)',
            self::EVENT_USER_PASSWORD_CHANGED => 'Password Changed (Triggered when user or customer changes/resets password)',
        ];
    }

    /**
     * Dispatch a webhook event with the given payload.
     */
    public static function dispatch(
        string $event,
        array $data,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?string $customUrl = null
    ): ?WebhookLog {
        try {
            $settings = GlobalSetting::current();

            // 1. Check if webhooks are globally enabled (unless it's a test.ping)
            if ($event !== self::EVENT_TEST_PING && !$settings->webhook_enabled) {
                return null;
            }

            // 2. Check if this specific event is enabled
            if ($event !== self::EVENT_TEST_PING) {
                $enabledEvents = is_array($settings->webhook_events) ? $settings->webhook_events : [];
                if (!in_array($event, $enabledEvents, true)) {
                    return null;
                }
            }

            // 3. Resolve target URL (custom override, specific event URL, or default URL)
            $url = $customUrl ?: self::resolveTargetUrl($event, $settings);
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                return null;
            }

            // 4. Construct standardized RFC/JSON Webhook Envelope
            $eventId = 'wh_' . Str::uuid()->toString();
            $timestamp = now()->toIso8601String();

            $envelope = [
                'event' => $event,
                'event_id' => $eventId,
                'timestamp' => $timestamp,
                'source' => 'Grass Florist',
                'environment' => config('app.env', 'production'),
                'api_version' => '1.0',
                'reference_id' => $referenceId,
                'data' => $data,
            ];

            $jsonPayload = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // 5. Build Headers (Signature & Custom headers)
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'GrassFlorist-Webhook/1.0 (+https://grassflorist.com)',
                'X-Webhook-Event' => $event,
                'X-Webhook-Delivery' => $eventId,
                'X-Webhook-Timestamp' => $timestamp,
            ];

            if (!empty($settings->webhook_secret)) {
                $signature = hash_hmac('sha256', $jsonPayload, $settings->webhook_secret);
                $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
                $headers['X-Grass-Signature'] = $signature;
            }

            // Merge any custom configured headers (e.g. Authorization: Bearer ...)
            if (is_array($settings->webhook_headers)) {
                foreach ($settings->webhook_headers as $item) {
                    if (is_array($item) && !empty($item['name']) && isset($item['value'])) {
                        $headers[trim($item['name'])] = trim($item['value']);
                    }
                }
            }

            // 6. Execute HTTP Request with precise timing
            $timeout = (int) ($settings->webhook_timeout ?: 10);
            $startTime = microtime(true);

            $responseStatus = null;
            $responseHeaders = null;
            $responseBody = null;
            $status = 'failed';
            $errorMessage = null;

            try {
                $httpResponse = Http::timeout($timeout)
                    ->withHeaders($headers)
                    ->withoutVerifying()
                    ->send('POST', $url, [
                        'body' => $jsonPayload,
                    ]);

                $durationMs = (int) round((microtime(true) - $startTime) * 1000);
                $responseStatus = $httpResponse->status();
                $responseHeaders = $httpResponse->headers();
                $responseBody = Str::limit($httpResponse->body(), 100000);

                if ($httpResponse->successful()) {
                    $status = 'success';
                } else {
                    $status = 'failed';
                    $errorMessage = 'HTTP ' . $responseStatus . ': ' . Str::limit($httpResponse->body(), 300);
                }
            } catch (Throwable $e) {
                $durationMs = (int) round((microtime(true) - $startTime) * 1000);
                $status = 'failed';
                $errorMessage = $e->getMessage();
            }

            // 7. Store Audit Callback Log
            return WebhookLog::create([
                'event' => $event,
                'url' => $url,
                'method' => 'POST',
                'headers' => $headers,
                'payload' => $jsonPayload,
                'response_status' => $responseStatus,
                'response_headers' => $responseHeaders,
                'response_body' => $responseBody,
                'duration_ms' => $durationMs,
                'status' => $status,
                'error_message' => $errorMessage,
                'attempts' => 1,
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
            ]);
        } catch (Throwable $outerError) {
            // Never let webhook failures crash the application
            logger()->error('WebhookService Outer Error: ' . $outerError->getMessage(), [
                'event' => $event,
                'reference_id' => $referenceId,
            ]);

            return null;
        }
    }

    /**
     * Resend an existing webhook log.
     */
    public static function resend(WebhookLog $log): WebhookLog
    {
        $startTime = microtime(true);
        $headers = is_array($log->headers) ? $log->headers : [];

        // Refresh timestamp & signature
        $timestamp = now()->toIso8601String();
        $headers['X-Webhook-Timestamp'] = $timestamp;
        $headers['X-Webhook-Delivery'] = 'wh_' . Str::uuid()->toString();

        $settings = GlobalSetting::current();
        if (!empty($settings->webhook_secret) && !empty($log->payload)) {
            $sig = hash_hmac('sha256', $log->payload, $settings->webhook_secret);
            $headers['X-Webhook-Signature'] = 'sha256=' . $sig;
            $headers['X-Grass-Signature'] = $sig;
        }

        $timeout = (int) ($settings->webhook_timeout ?: 10);
        $responseStatus = null;
        $responseHeaders = null;
        $responseBody = null;
        $status = 'failed';
        $errorMessage = null;

        try {
            $httpResponse = Http::timeout($timeout)
                ->withHeaders($headers)
                ->withoutVerifying()
                ->send($log->method ?: 'POST', $log->url, [
                    'body' => $log->payload,
                ]);

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $responseStatus = $httpResponse->status();
            $responseHeaders = $httpResponse->headers();
            $responseBody = Str::limit($httpResponse->body(), 100000);

            if ($httpResponse->successful()) {
                $status = 'success';
            } else {
                $status = 'failed';
                $errorMessage = 'HTTP ' . $responseStatus . ': ' . Str::limit($httpResponse->body(), 300);
            }
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $status = 'failed';
            $errorMessage = $e->getMessage();
        }

        return WebhookLog::create([
            'event' => $log->event,
            'url' => $log->url,
            'method' => $log->method ?: 'POST',
            'headers' => $headers,
            'payload' => $log->payload,
            'response_status' => $responseStatus,
            'response_headers' => $responseHeaders,
            'response_body' => $responseBody,
            'duration_ms' => $durationMs,
            'status' => $status,
            'error_message' => $errorMessage,
            'attempts' => ($log->attempts ?: 1) + 1,
            'reference_id' => $log->reference_id,
            'reference_type' => $log->reference_type,
        ]);
    }

    /**
     * Send a sample test ping to test webhook destination connectivity.
     */
    public static function sendTestPing(?string $customUrl = null): array
    {
        $settings = GlobalSetting::current();
        $targetUrl = $customUrl ?: $settings->webhook_default_url;

        if (empty($targetUrl) || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => 'Invalid or missing Webhook URL. Please enter a valid URL beginning with http:// or https://.',
            ];
        }

        $sampleData = [
            'message' => 'Grass Florist Webhook Test Ping',
            'store' => [
                'name' => 'Grass Florist',
                'url' => config('app.url', 'https://grassflorist.com'),
                'currency' => 'SAR',
            ],
            'sample_order' => [
                'order_number' => 'TEST-ORD-0099',
                'total_amount' => 350.00,
                'currency' => 'SAR',
                'status' => 'processing',
                'customer' => [
                    'name' => 'Test Customer',
                    'email' => 'test@grassflorist.com',
                    'phone' => '+966555134211',
                ],
            ],
        ];

        $log = self::dispatch(self::EVENT_TEST_PING, $sampleData, 'TEST-001', 'TestPing', $targetUrl);

        if ($log && $log->status === 'success') {
            return [
                'success' => true,
                'message' => "Webhook ping delivered successfully! Status: HTTP {$log->response_status} in {$log->duration_ms}ms.",
                'log_id' => $log->id,
            ];
        }

        return [
            'success' => false,
            'message' => 'Webhook ping failed. ' . ($log ? ($log->error_message ?: "HTTP {$log->response_status}") : 'Could not send request.'),
            'log_id' => $log?->id,
        ];
    }

    /**
     * Resolve target URL based on event-specific override or default URL.
     */
    protected static function resolveTargetUrl(string $event, GlobalSetting $settings): ?string
    {
        return match ($event) {
            self::EVENT_ORDER_CREATED => $settings->webhook_order_created_url ?: $settings->webhook_default_url,
            self::EVENT_ORDER_STATUS_UPDATED, self::EVENT_ORDER_PAID => $settings->webhook_order_status_url ?: $settings->webhook_default_url,
            self::EVENT_USER_REGISTERED => $settings->webhook_user_registered_url ?: $settings->webhook_default_url,
            self::EVENT_SUBSCRIPTION_CREATED => $settings->webhook_subscription_created_url ?: $settings->webhook_default_url,
            self::EVENT_NEWSLETTER_SUBSCRIBED => $settings->webhook_newsletter_url ?: $settings->webhook_default_url,
            self::EVENT_USER_PASSWORD_CHANGED => $settings->webhook_password_changed_url ?: $settings->webhook_default_url,
            default => $settings->webhook_default_url,
        };
    }
}
