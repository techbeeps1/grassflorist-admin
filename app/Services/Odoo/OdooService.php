<?php

namespace App\Services\Odoo;

use App\Models\GlobalSetting;
use App\Models\OdooSyncLog;
use App\Models\Order;
use App\Models\OrderActivity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OdooService
{
    /**
     * Check if an order is bypassed from syncing to Odoo.
     * (E.g. testing accounts, specific domains like techbeeps.com, specific emails)
     */
    public function checkBypass(Order $order): ?string
    {
        $settings = GlobalSetting::current();

        // 1. Bypass Specific Email Check
        $customerEmail = strtolower(trim((string)$order->email));
        $bypassEmailsRaw = $settings->odoo_bypass_emails ?: "gurjeet@techbeeps.com";
        $bypassEmails = array_filter(array_map('trim', preg_split('/[\s,]+/', strtolower((string)$bypassEmailsRaw))));
        if (!empty($customerEmail) && in_array($customerEmail, $bypassEmails, true)) {
            return "Customer email '{$customerEmail}' is in Odoo testing bypass list.";
        }

        // 3. Bypass Email Domain Check (e.g. techbeeps.com, techbeeps.co.in)
        $bypassDomainsRaw = $settings->odoo_bypass_domains ?: "techbeeps.com\ntechbeeps.co.in";
        $bypassDomains = array_filter(array_map('trim', preg_split('/[\s,\r\n]+/', strtolower((string)$bypassDomainsRaw))));

        if (!empty($customerEmail) && str_contains($customerEmail, '@')) {
            $emailDomain = strtolower(substr(strrchr($customerEmail, '@'), 1));
            if (in_array($emailDomain, $bypassDomains, true)) {
                return "Customer email domain '@{$emailDomain}' is configured to bypass Odoo sync.";
            }
        }

        return null; // Not bypassed
    }

    /**
     * Build the structured JSON payload for Odoo upsert API.
     */
    public function buildOrderPayload(Order $order): array
    {
        $orderNumber = (string)($order->order_number ?: $order->id);

        $customerName = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));
        if (empty($customerName)) {
            $customerName = 'Customer #' . ($order->user_id ?: $order->id);
        }

        $customer = [
            'name' => $customerName,
            'email' => (string)($order->email ?: ''),
            'phone' => (string)($order->customer_phone ?: ($order->recipient_phone ?: '')),
        ];

        // Format Products List
        $products = [];
        $order->loadMissing('items.product');

        foreach ($order->items as $item) {
            $qty = max(1, (int)($item->quantity ?? 1));
            
            // Calculate price unit (before shipping/overall taxes)
            $itemTotal = (float)($item->total ?? ($item->price ? $item->price * $qty : 0));
            $priceUnit = $qty > 0 ? ($itemTotal / $qty) : (float)($item->price ?? 0);

            $productName = $item->product_name ?: ($item->product?->name ?: 'Item #' . $item->id);

            $products[] = [
                'name' => $productName,
                'qty' => $qty,
                'price_unit' => round($priceUnit, 2),
                'vat_percentage' => 15,
            ];
        }

        // Shipping line item
        $shippingAmount = (float)($order->shipping_amount ?? 0);
        $products[] = [
            'name' => "Shipping",
            'qty' => 1,
            'price_unit' => round($shippingAmount, 2),
            'vat_percentage' => 0,
        ];

        // Format Delivery details
        $deliveryDateStr = $order->delivery_date ? $order->delivery_date->format('Y-m-d') : '';
        $deliveryTimeStr = (string)($order->delivery_time ?: '');
        $deliveryNote = "Delivery between " . trim("{$deliveryDateStr} {$deliveryTimeStr}");

        $paymentMethod = (string)($order->payment_method ?: 'ONLINE');
        $paymentMethodTitle = strtoupper($paymentMethod);
        $transactionId = (string)($order->razorpay_payment_id ?: ($order->razorpay_order_id ?: 'TXN-' . $orderNumber));

        $paidDateFormatted = $order->date_paid 
            ? $order->date_paid->format('Y-m-d H:i:s') 
            : ($order->created_at ? $order->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'));

        $orderDate = $order->created_at ? $order->created_at->format('Y-m-d') : date('Y-m-d');

        return [
            'customer' => $customer,
            'order_date' => $orderDate,
            'order_notes' => trim($deliveryNote . ($order->notes ? " | Note: " . $order->notes : '')),
            'order_status' => 'processing',
            'currency' => (string)($order->currency ?: 'SAR'),
            'order_number' => $orderNumber,
            'payment_method' => $paymentMethod,
            'payment_method_title' => $paymentMethodTitle,
            'transaction_id' => $transactionId,
            'paid_date' => $paidDateFormatted,
            'products' => $products,
        ];
    }

    /**
     * Synchronize order to Odoo ERP.
     * Enforces paid validation, bypass checking, invoice PDF saving, and sanitized log generation.
     */
    public function syncOrder(Order $order, string $syncedBy = 'system'): array
    {
        $settings = GlobalSetting::current();
        $orderNumber = (string)($order->order_number ?: $order->id);

        // 1. Mandatory Rule: Only sync orders that are PAID!
        if (!$order->isPaid()) {
            $reason = "Order #{$orderNumber} is not paid (Payment Status: " . ($order->payment_status ?: 'unpaid') . "). Odoo sync requires verified payment.";

            return [
                'success' => false,
                'status' => 'unpaid',
                'message' => $reason,
            ];
        }

        // 2. Check Testing / Domain Bypass Rules
        $bypassReason = $this->checkBypass($order);
        if ($bypassReason) {
            $order->update([
                'odoo_sync_status' => 'bypassed',
                'odoo_error' => $bypassReason,
            ]);

            OdooSyncLog::create([
                'order_id' => $order->id,
                'order_number' => $orderNumber,
                'status' => 'bypassed',
                'request_url' => $settings->odoo_api_url,
                'request_payload' => null,
                'response_payload' => ['reason' => $bypassReason],
                'error_message' => $bypassReason,
                'synced_by' => $syncedBy,
            ]);

            try {
                OrderActivity::log(
                    $order,
                    'odoo_sync',
                    "Odoo Sync Skipped (Bypassed)",
                    $bypassReason,
                    ['reason' => $bypassReason]
                );
            } catch (\Throwable $e) {}

            return [
                'success' => true,
                'bypassed' => true,
                'status' => 'bypassed',
                'message' => $bypassReason,
            ];
        }

        // 3. Check if Odoo Integration is enabled
        if (!$settings->odoo_enabled) {
            return [
                'success' => false,
                'status' => 'disabled',
                'message' => 'Odoo ERP integration is currently disabled in Global Settings.',
            ];
        }

        $apiUrl = $settings->odoo_api_url ?: 'https://odoo.grassflorist.com/website/api/order/upsert';
        $apiToken = $settings->odoo_api_token ?: '9b8a82e926bfc65571587fab53195f9d667169';

        // 4. Construct Payload
        $payload = $this->buildOrderPayload($order);

        try {
            // 5. Send POST request to Odoo ERP
            $response = Http::timeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiToken,
                ])
                ->post($apiUrl, $payload);

            $statusCode = $response->status();
            $body = $response->body();
            $responseData = json_decode($body, true);

            $isSuccess = $response->successful() && !empty($responseData['result']['success']);

            if (!$isSuccess) {
                $errorMsg = $responseData['result']['message'] 
                    ?? ($responseData['error']['message'] ?? ("HTTP {$statusCode}: " . substr($body, 0, 300)));

                $order->update([
                    'odoo_sync_status' => 'failed',
                    'odoo_error' => $errorMsg,
                ]);

                OdooSyncLog::create([
                    'order_id' => $order->id,
                    'order_number' => $orderNumber,
                    'status' => 'failed',
                    'request_url' => $apiUrl,
                    'request_payload' => $payload,
                    'response_payload' => is_array($responseData) ? $responseData : ['raw_body' => substr($body, 0, 2000)],
                    'error_message' => $errorMsg,
                    'synced_by' => $syncedBy,
                ]);

                try {
                    OrderActivity::log(
                        $order,
                        'odoo_sync',
                        "Odoo Sync Failed",
                        "Failed to sync with Odoo ERP: {$errorMsg}",
                        ['error' => $errorMsg]
                    );
                } catch (\Throwable $e) {}

                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => "Odoo sync failed: {$errorMsg}",
                ];
            }

            // 6. Handle Invoice PDF if provided in Base64
            $invoicePdfBase64 = $responseData['result']['data']['invoice_pdf'] ?? null;
            $savedPdfPath = null;
            $savedPdfUrl = null;

            if (!empty($invoicePdfBase64)) {
                $pdfBinary = base64_decode($invoicePdfBase64);

                if (!empty($pdfBinary)) {
                    $cleanOrderNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', $orderNumber);
                    $fileName = 'invoice_' . $cleanOrderNumber . '.pdf';
                    $storagePath = 'odoo_pdf/' . $fileName;

                    // Save to public storage disk
                    Storage::disk('public')->put($storagePath, $pdfBinary);

                    $savedPdfPath = $storagePath;
                    $savedPdfUrl = Storage::disk('public')->url($storagePath);
                }
            }

            // 7. STRIP BASE64 PDF FROM RESPONSE BEFORE DB LOG TO PREVENT DB BLOAT
            $sanitizedResponse = $responseData;
            if (isset($sanitizedResponse['result']['data']['invoice_pdf'])) {
                $pdfSizeKb = !empty($pdfBinary) ? round(strlen($pdfBinary) / 1024, 2) : 0;
                $sanitizedResponse['result']['data']['invoice_pdf'] = "[BASE64_SAVED_TO_FILE: {$pdfSizeKb} KB at " . ($savedPdfUrl ?: 'N/A') . "]";
            }

            // 8. Update Order Record
            $order->update([
                'odoo_sync_status' => 'synced',
                'odoo_synced_at' => now(),
                'odoo_invoice_pdf_path' => $savedPdfPath,
                'odoo_invoice_pdf_url' => $savedPdfUrl,
                'odoo_error' => null,
            ]);

            // 9. Save Cleaned Log
            OdooSyncLog::create([
                'order_id' => $order->id,
                'order_number' => $orderNumber,
                'status' => 'success',
                'request_url' => $apiUrl,
                'request_payload' => $payload,
                'response_payload' => $sanitizedResponse,
                'invoice_pdf_url' => $savedPdfUrl,
                'error_message' => null,
                'synced_by' => $syncedBy,
            ]);

            // 10. Record Order Activity Milestone
            try {
                OrderActivity::log(
                    $order,
                    'odoo_sync',
                    "Order Synced with Odoo ERP",
                    "Order successfully registered in Odoo ERP." . ($savedPdfUrl ? " Invoice PDF stored." : ""),
                    [
                        'odoo_status' => 'synced',
                        'invoice_url' => $savedPdfUrl,
                        'synced_at' => now()->toDateTimeString(),
                    ]
                );
            } catch (\Throwable $e) {}

            return [
                'success' => true,
                'status' => 'synced',
                'message' => "Order #{$orderNumber} successfully synchronized to Odoo ERP.",
                'invoice_pdf_url' => $savedPdfUrl,
            ];

        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::error("[OdooService] Exception during order sync (#{$orderNumber}): {$errorMsg}");

            $order->update([
                'odoo_sync_status' => 'failed',
                'odoo_error' => $errorMsg,
            ]);

            OdooSyncLog::create([
                'order_id' => $order->id,
                'order_number' => $orderNumber,
                'status' => 'failed',
                'request_url' => $apiUrl,
                'request_payload' => $payload,
                'response_payload' => null,
                'error_message' => $errorMsg,
                'synced_by' => $syncedBy,
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => "Exception during Odoo sync: {$errorMsg}",
            ];
        }
    }
}
