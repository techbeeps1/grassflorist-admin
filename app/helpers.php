<?php

if (! function_exists('currency_symbol')) {
    function currency_symbol(?string $currency = null): string {
        $curr = $currency ?: currency_code();
        return match (strtoupper($curr)) {
            'SAR', 'SR' => 'SAR ',
            'AED' => 'AED ',
            'USD' => '$',
            'EUR' => '€',
            'INR' => '₹',
            default => config('app.currency_symbol', 'SAR '),
        };
    }
}

if (! function_exists('currency_code')) {
    function currency_code(): string {
        return config('app.currency', 'SAR');
    }
}

if (! function_exists('format_currency')) {
    function format_currency($amount, int $decimals = 2, ?string $currency = null): string {
        return currency_symbol($currency) . number_format((float) ($amount ?? 0), $decimals);
    }
}

if (! function_exists('current_locale')) {
    function current_locale(): string {
        return app()->getLocale() ?: config('app.locale', 'en');
    }
}

if (! function_exists('is_rtl')) {
    function is_rtl(?string $locale = null): bool {
        $loc = $locale ?: current_locale();
        return in_array(strtolower($loc), ['ar', 'fa', 'ur', 'he']);
    }
}

if (! function_exists('format_translatable')) {
    /**
     * Extract string for the requested/active locale from array, JSON string, or legacy plain string.
     */
    function format_translatable(mixed $value, ?string $locale = null): ?string {
        if (is_null($value)) {
            return null;
        }

        $targetLocale = $locale ?: current_locale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        if (is_array($value)) {
            if (isset($value[$targetLocale]) && filled($value[$targetLocale])) {
                return (string) $value[$targetLocale];
            }
            if (isset($value[$fallbackLocale]) && filled($value[$fallbackLocale])) {
                return (string) $value[$fallbackLocale];
            }
            foreach ($value as $item) {
                if (filled($item) && is_scalar($item)) {
                    return (string) $item;
                }
            }
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return format_translatable($decoded, $targetLocale);
            }

            return $value;
        }

        return (string) $value;
    }
}

if (! function_exists('zatca_tlv_qr_payload')) {
    /**
     * Generate official ZATCA Phase-1 compliant Base64 TLV payload.
     * Required by Saudi ZATCA e-invoicing for scanning with the official Fatoora / ZATCA app.
     *
     * Tag 1: Seller's Name (e.g. مؤسسة غراس السعودية)
     * Tag 2: VAT Registration Number (15 digits e.g. 300553485900003)
     * Tag 3: Time Stamp (ISO 8601 UTC format: YYYY-MM-DDTHH:mm:ssZ)
     * Tag 4: Invoice Total (with VAT) as string (e.g. 623.00)
     * Tag 5: VAT Total as string (e.g. 78.00)
     */
    function zatca_tlv_qr_payload(
        ?string $sellerName = null,
        ?string $trn = null,
        string|\DateTimeInterface|null $timestamp = null,
        float|int|string $totalAmount = 0,
        float|int|string $vatAmount = 0
    ): string {
        $seller = !empty(trim((string)$sellerName)) ? trim((string)$sellerName) : 'مؤسسة غراس السعودية';
        $vatNo = !empty(trim((string)$trn)) ? trim((string)$trn) : '300553485900003';

        if ($timestamp instanceof \DateTimeInterface) {
            $formattedTime = $timestamp->format('Y-m-d\TH:i:s\Z');
        } elseif (!empty($timestamp)) {
            try {
                $formattedTime = \Carbon\Carbon::parse($timestamp)->format('Y-m-d\TH:i:s\Z');
            } catch (\Throwable $e) {
                $formattedTime = gmdate('Y-m-d\TH:i:s\Z');
            }
        } else {
            $formattedTime = gmdate('Y-m-d\TH:i:s\Z');
        }

        $formattedTotal = number_format((float) $totalAmount, 2, '.', '');
        $formattedVat = number_format((float) $vatAmount, 2, '.', '');

        $tlvTag = static function (int $tag, string $val): string {
            return pack('C', $tag) . pack('C', strlen($val)) . $val;
        };

        $payload = $tlvTag(1, $seller)
                 . $tlvTag(2, $vatNo)
                 . $tlvTag(3, $formattedTime)
                 . $tlvTag(4, $formattedTotal)
                 . $tlvTag(5, $formattedVat);

        return base64_encode($payload);
    }
}

if (! function_exists('zatca_qr_image_url')) {
    /**
     * Generate URL for a QR code image encoding ZATCA Phase-1 Base64 TLV data.
     */
    function zatca_qr_image_url(
        ?string $sellerName = null,
        ?string $trn = null,
        string|\DateTimeInterface|null $timestamp = null,
        float|int|string $totalAmount = 0,
        float|int|string $vatAmount = 0,
        int $size = 150
    ): string {
        $base64 = zatca_tlv_qr_payload($sellerName, $trn, $timestamp, $totalAmount, $vatAmount);
        return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($base64);
    }
}

