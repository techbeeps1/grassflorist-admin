<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" dir="{{ ($locale ?? 'en') === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Grass Florist - Luxury Floral Atelier' }}</title>

    @php
        $settings = \App\Models\GlobalSetting::current();
        $isAr = ($locale ?? 'en') === 'ar';

        // Logo resolution: custom external URL -> uploaded email_logo -> site_logo -> local fallback
        $logoSrc = null;
        if (!empty($settings->email_custom_logo_url)) {
            $logoSrc = $settings->email_custom_logo_url;
        } elseif (!empty($settings->email_logo_url)) {
            $logoSrc = $settings->email_logo_url;
        } elseif (!empty($settings->site_logo_url)) {
            $logoSrc = $settings->site_logo_url;
        } else {
            $logoPath = public_path('images/grass-logo.webp');
            $logoSrc = file_exists($logoPath)
                ? ('data:image/webp;base64,' . base64_encode(file_get_contents($logoPath)))
                : asset('images/grass-logo.webp');
        }

        // Color palette from Global Settings
        $headerBg = $settings->email_header_bg_color ?: '#2D3F33';
        $bodyBg = $settings->email_body_bg_color ?: '#FAF8F5';
        $cardBg = $settings->email_card_bg_color ?: '#FAF8F5';
        $accentColor = $settings->email_accent_color ?: '#8fae2a';

        // Title and Subtitle
        $title = $isAr
            ? ($settings->email_title_ar ?: ($settings->site_name_ar ?: 'غراس فلوريست'))
            : ($settings->email_title_en ?: ($settings->site_name_en ?: 'Grass Florist'));

        $subtitle = $isAr
            ? ($settings->email_subtitle_ar ?: ($settings->site_tagline_ar ?: 'متجر الزهور والهدايا الفاخرة'))
            : ($settings->email_subtitle_en ?: ($settings->site_tagline_en ?: 'Luxury Floral Atelier & Curated Gifting'));

        // Footer details
        $footerName = $isAr
            ? ($settings->email_footer_name_ar ?: 'مؤسسة غراس السعودية للزهور والهدايا')
            : ($settings->email_footer_name_en ?: 'Grass Florist Atelier — Saudi Arabia');

        $footerAddress = $isAr
            ? ($settings->email_footer_address_ar ?: ($settings->contact_address_ar ?: 'جدة، المملكة العربية السعودية'))
            : ($settings->email_footer_address_en ?: ($settings->contact_address_en ?: 'Jeddah, Kingdom of Saudi Arabia'));

        $footerPhone = $settings->email_footer_phone ?: ($settings->contact_phone ?: '+966 50 000 0000');
        $footerEmail = $settings->email_footer_email ?: ($settings->contact_email ?: 'info@grassflorist.com');

        $cleanPhone = preg_replace('/[^\d+]/', '', (string)$footerPhone);

        $copyrightText = $isAr
            ? ($settings->email_footer_copyright_ar ?: ($settings->footer_copyright_ar ?: 'جميع الحقوق محفوظة.'))
            : ($settings->email_footer_copyright_en ?: ($settings->footer_copyright_en ?: 'All rights reserved.'));
    @endphp

    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Tajawal', 'Cairo', sans-serif;
            line-height: 1.6;
            color: #1E1915;
            background-color: {{ $bodyBg }};
            margin: 0;
            padding: 24px 12px;
            -webkit-font-smoothing: antialiased;
        }
        .email-wrapper {
            max-width: 620px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #EFE7DC;
            box-shadow: 0 4px 20px rgba(45, 63, 51, 0.06);
        }
        .header {
            background-color: {{ $headerBg }};
            color: #ffffff;
            padding: 28px 24px 24px 24px;
            text-align: center;
            border-bottom: 3px solid {{ $accentColor }};
        }
        .logo-img {
            max-width: 140px;
            height: auto;
            display: inline-block;
            margin: 0 auto 8px auto;
        }
        .brand-subtitle {
            margin: 4px 0 0;
            font-size: 11px;
            color: #C2D4BE;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            font-weight: 500;
        }
        .content {
            padding: 32px 28px;
            color: #2D3F33;
            font-size: 15px;
            line-height: 1.7;
        }
        .content h1, .content h2, .content h3 {
            color: {{ $headerBg }};
            margin-top: 0;
            font-weight: 700;
        }
        .content p {
            margin: 0 0 16px 0;
        }
        .content a {
            color: {{ $accentColor }};
            font-weight: 600;
            text-decoration: underline;
        }
        .card {
            background-color: {{ $cardBg }};
            border: 1px solid #EAE3D7;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        .badge {
            display: inline-block;
            background-color: {{ $accentColor }};
            color: #ffffff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .btn-action {
            display: inline-block;
            background-color: {{ $headerBg }};
            color: #ffffff !important;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none !important;
            margin: 16px 0;
            text-align: center;
            border: 1px solid {{ $accentColor }};
        }
        .footer {
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #8C827A;
            border-top: 1px solid #EFE7DC;
            background-color: {{ $cardBg }};
        }
        .footer p {
            margin: 4px 0;
        }
        .footer a {
            color: {{ $headerBg }};
            text-decoration: none;
            font-weight: 600;
        }
        /* RTL support */
        [dir="rtl"] {
            text-align: right;
        }
        [dir="rtl"] .header {
            text-align: center;
        }
        [dir="rtl"] .footer {
            text-align: center;
        }
    </style>
</head>
<body style="background-color: {{ $bodyBg }};">
    <div class="email-wrapper">
        <div class="header" style="background-color: {{ $headerBg }}; border-bottom: 3px solid {{ $accentColor }};">
            <a href="{{ config('app.url', 'https://grassflorist.com') }}" target="_blank" style="text-decoration: none; display: block;">
                <img src="{{ $logoSrc }}" alt="{{ $title }}" class="logo-img" style="filter: brightness(0) invert(1);">
            </a>
            <div class="brand-subtitle">
                {{ $subtitle }}
            </div>
        </div>

        <div class="content">
            {!! $slot !!}
        </div>

        <div class="footer" style="background-color: {{ $cardBg }};">
            <p style="font-weight: 600; color: {{ $headerBg }};">
                {{ $footerName }}
            </p>
            <p>
                {{ $footerAddress }} &bull;
                <a href="tel:{{ $cleanPhone }}">{{ $footerPhone }}</a> &bull;
                <a href="mailto:{{ $footerEmail }}">{{ $footerEmail }}</a>
            </p>
            <p style="margin-top: 10px; font-size: 11px; color: #A09489;">
                &copy; {{ date('Y') }} {{ $title }}. {{ $copyrightText }}
            </p>
        </div>
    </div>
</body>
</html>
