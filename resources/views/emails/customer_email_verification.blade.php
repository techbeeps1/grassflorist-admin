<!DOCTYPE html>
<html lang="{{ $locale === 'ar' ? 'ar' : 'en' }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $locale === 'ar' ? 'تأكيد البريد الإلكتروني - غراس' : 'Verify Email - Grass Florist' }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #2D3748;
            background-color: #FAF7F2;
            margin: 0;
            padding: 24px;
        }
        .container {
            max-width: 580px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #EAE0D5;
        }
        .header {
            background-color: #435849;
            padding: 32px 24px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 22px;
            margin: 0;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .content {
            padding: 32px 28px;
            text-align: {{ $locale === 'ar' ? 'right' : 'left' }};
        }
        .greeting {
            font-size: 18px;
            font-weight: 700;
            color: #1A202C;
            margin-bottom: 12px;
        }
        .lead {
            font-size: 14px;
            color: #4A5568;
            margin-bottom: 24px;
            line-height: 1.7;
        }
        .btn-box {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background-color: #435849;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 36px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(67, 88, 73, 0.3);
        }
        .notice {
            background-color: #FAF7F2;
            border: 1px solid #EAE0D5;
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 12px;
            color: #718096;
            margin-top: 24px;
            word-break: break-all;
        }
        .footer {
            background-color: #FAF7F2;
            padding: 20px 24px;
            text-align: center;
            font-size: 12px;
            color: #A0AEC0;
            border-top: 1px solid #EAE0D5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌿 GRASS FLORIST | غراس للزهور</h1>
        </div>
        <div class="content">
            @if($locale === 'ar')
                <div class="greeting">مرحباً {{ $customer->first_name ?: $customer->name }}،</div>
                <p class="lead">
                    شكراً لانضمامك إلى عائلة غراس. يرجى تأكيد عنوان بريدك الإلكتروني لضمان أمان حسابك وتلقي إشعارات الطلبات وتحديثات الهدايا بأمان.
                </p>
                <div class="btn-box">
                    <a href="{{ $verificationUrl }}" class="btn" target="_blank">تأكيد البريد الإلكتروني الآن</a>
                </div>
                <div class="notice">
                    إذا واجهت مشكلة في الضغط على الزر، يمكنك نسخ هذا الرابط وفتحه في المتصفح:<br>
                    <a href="{{ $verificationUrl }}" style="color: #435849;">{{ $verificationUrl }}</a>
                </div>
                <p style="font-size: 12px; color: #718096; margin-top: 16px;">
                    هذا الرابط صالح لمدة 24 ساعة. إذا لم تكن أنت صاحب هذا الحساب، يمكنك تجاهل هذه الرسالة بأمان.
                </p>
            @else
                <div class="greeting">Hello {{ $customer->first_name ?: $customer->name }},</div>
                <p class="lead">
                    Thank you for joining Grass Florist. Please confirm your email address to secure your account and receive order notifications safely.
                </p>
                <div class="btn-box">
                    <a href="{{ $verificationUrl }}" class="btn" target="_blank">Verify Email Address Now</a>
                </div>
                <div class="notice">
                    If you are having trouble with the button, copy and paste this link into your browser:<br>
                    <a href="{{ $verificationUrl }}" style="color: #435849;">{{ $verificationUrl }}</a>
                </div>
                <p style="font-size: 12px; color: #718096; margin-top: 16px;">
                    This link is valid for 24 hours. If you did not create this account, please disregard this email.
                </p>
            @endif
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Grass Florist. {{ $locale === 'ar' ? 'جميع الحقوق محفوظة.' : 'All rights reserved.' }}
        </div>
    </div>
</body>
</html>
