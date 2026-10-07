<!DOCTYPE html>
<html lang="{{ $order->order_language ?? 'ar' }}" dir="{{ ($order->order_language ?? 'ar') === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Card - #{{ $order->order_number ?? $order->id }} - Grass Florist</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    
    @php
        $bgPath = public_path('images/gift-card-bg.png');
        $bgSrc = file_exists($bgPath) ? asset('images/gift-card-bg.png') : '';
        $bgBase64 = file_exists($bgPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($bgPath)) : '';
    @endphp

    <style>
        @page {
            size: 100mm 169.25mm; /* Standard Vertical Florist Card (Ratio: 309x523) */
            margin: 0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Cairo', 'Almarai', 'Tajawal', sans-serif;
            background-color: #f7f6f2;
            color: #2c2523;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 30px 15px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Screen Controls Toolbar (Hidden when printing) */
        .toolbar {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #ffffff;
            padding: 10px 22px;
            border-radius: 9999px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
            display: flex;
            gap: 12px;
            align-items: center;
            z-index: 9999;
        }

        .btn {
            background: #059669;
            color: #ffffff;
            border: none;
            padding: 8px 18px;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s, transform 0.1s;
        }

        .btn:hover {
            background: #047857;
        }

        .btn-secondary {
            background: #4b5563;
        }

        .btn-secondary:hover {
            background: #374151;
        }

        .btn-tool {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-tool:hover {
            background: #e5e7eb;
        }

        /* Card Container (Aspect Ratio: 309x523) */
        .card-container {
            position: relative;
            width: 360px;
            height: 609px;
            background: #fdfaf6;
            border-radius: 8px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.09), 0 2px 8px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        /* Background Frame Image */
        .card-bg-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: fill;
            z-index: 1;
            pointer-events: none;
        }

        /* Content Overlay (Centered in Lower-Middle area between empty top and bottom logo) */
        .card-content {
            position: absolute;
            top: 44%;
            bottom: 21%;
            left: 10%;
            right: 10%;
            z-index: 2;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            box-sizing: border-box;
        }

        /* Greeting Message Text */
        .card-message {
            font-family: 'Cairo', 'Almarai', 'Tajawal', sans-serif;
            font-size: 16px;
            font-weight: 500;
            line-height: 1.7;
            color: #2c2523;
            text-align: center;
            word-wrap: break-word;
            width: 100%;
            margin-bottom: 15px;
            transition: font-size 0.2s;
        }

        /* Sender / Sign-off Block */
        .card-sender {
            font-family: 'Cairo', 'Almarai', 'Tajawal', sans-serif;
            font-size: 20px;
            font-weight: 700;
            line-height: 1.45;
            color: #1a1614;
            text-align: center;
            word-wrap: break-word;
            width: 100%;
            margin-top: 2px;
        }

        /* Optional Song QR Code */
        .card-song {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .card-song img {
            width: 34px;
            height: 34px;
            border-radius: 4px;
            border: 1px solid #d4c5b5;
        }

        .card-song-text {
            font-size: 9px;
            color: #786c63;
            line-height: 1.2;
            text-align: right;
        }

        /* Order Metadata (Screen Only) */
        .order-meta-info {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 10px;
            color: #b0a498;
            z-index: 3;
            font-family: monospace;
            direction: ltr;
        }

        @media print {
            body {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
                min-height: auto !important;
                display: block !important;
            }

            .toolbar, .order-meta-info {
                display: none !important;
            }

            .card-container {
                box-shadow: none !important;
                border-radius: 0 !important;
                margin: 0 auto !important;
                width: 100mm !important;
                height: 169.25mm !important;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
            }

            .card-bg-img {
                width: 100mm !important;
                height: 169.25mm !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .card-content {
                top: 44% !important;
                bottom: 21% !important;
                left: 10% !important;
                right: 10% !important;
            }

            .card-message {
                font-size: 13.5pt !important;
                line-height: 1.65 !important;
            }

            .card-sender {
                font-size: 16.5pt !important;
                line-height: 1.4 !important;
            }
        }
    </style>
</head>
<body>

    <!-- Screen Toolbar -->
    <div class="toolbar">
        <button class="btn" onclick="window.print()">
            Print Gift Card
        </button>
        <button class="btn-tool" onclick="adjustFontSize(1)" title="Increase Font Size">
            A+
        </button>
        <button class="btn-tool" onclick="adjustFontSize(-1)" title="Decrease Font Size">
            A-
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            Close
        </button>
    </div>

    <!-- Staff Meta Reference (Screen Only) -->
    <div class="order-meta-info">
        #{{ $order->order_number ?? $order->id }}
    </div>

    <!-- Printable Gift Card -->
    <div class="card-container" id="giftCard">
        <!-- Background Frame & Logo -->
        <img src="{{ $bgBase64 ?: $bgSrc }}" class="card-bg-img" alt="Grass Florist Card Frame" />

        <!-- Card Content Overlay -->
        <div class="card-content">
            <!-- Greeting Message -->
            <div class="card-message" id="cardMessage" dir="auto">
                @if(!empty($order->delivery_message))
                    {!! nl2br(e($order->delivery_message)) !!}
                @else
                    ألف مبروك بكل حب وأطيب الأماني
                @endif
            </div>

            <!-- Sender / Sign-off -->
            @if(!empty($order->sender_name))
                <div class="card-sender" id="cardSender" dir="auto">
                    {{ $order->sender_name }}
                </div>
            @endif

            <!-- Optional Spotify / Song QR -->
            @if(!empty($order->song_link))
                <div class="card-song">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($order->song_link) }}" alt="Song QR">
                    <div class="card-song-text">
                        <strong>Song Attached</strong><br>
                        امسح للاستماع
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        let currentSize = 16;
        function adjustFontSize(delta) {
            currentSize += delta;
            if (currentSize < 11) currentSize = 11;
            if (currentSize > 24) currentSize = 24;
            document.getElementById('cardMessage').style.fontSize = currentSize + 'px';
        }
    </script>

</body>
</html>
