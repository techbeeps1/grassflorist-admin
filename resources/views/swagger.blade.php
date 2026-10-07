<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - Grass Florist</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
    <style>
        html {
            box-sizing: border-box;
            overflow-y: scroll;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin: 0;
            background: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #111827;
        }
        .header-bar {
            background: #0f172a;
            color: #f8fafc;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #1e293b;
        }
        .header-bar .brand-title {
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.2px;
        }
        .header-bar .brand-badge {
            background: #334155;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: 8px;
        }
        .header-bar .nav-actions {
            display: flex;
            gap: 10px;
        }
        .header-bar .nav-btn {
            background: #1e293b;
            color: #e2e8f0;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid #334155;
            transition: all 0.15s ease;
        }
        .header-bar .nav-btn:hover {
            background: #334155;
            color: #ffffff;
        }

        /* Hide standalone header bar when embedded in iframe */
        body.is-embedded .header-bar {
            display: none !important;
        }

        .swagger-ui .topbar {
            display: none !important;
        }
        .swagger-ui .info {
            margin: 20px 0 16px 0;
        }
        .swagger-ui .info .title {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }
        .swagger-ui .scheme-container {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: none;
            padding: 12px 0;
            margin-bottom: 16px;
        }
        .swagger-ui .btn.authorize {
            border-color: #059669;
            color: #059669;
        }
        .swagger-ui .btn.authorize svg {
            fill: #059669;
        }
    </style>
</head>
<body>
    <header class="header-bar">
        <div style="display: flex; align-items: center;">
            <span class="brand-title">Grass Florist REST API</span>
            <span class="brand-badge">OpenAPI 3.1</span>
        </div>
        <div class="nav-actions">
            <a href="/docs/api" class="nav-btn">Modern View</a>
            <a href="/admin" class="nav-btn">Admin Panel</a>
        </div>
    </header>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js" crossorigin></script>
    <script>
        // Check if embedded in iframe or url query
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('embedded') === '1' || window.self !== window.top) {
            document.body.classList.add('is-embedded');
        }

        window.onload = function() {
            window.ui = SwaggerUIBundle({
                url: "{{ url('/docs/api.json') }}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                displayRequestDuration: true,
                filter: true,
                persistAuthorization: true,
                docExpansion: 'list',
                defaultModelsExpandDepth: 1,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout"
            });
        };
    </script>
</body>
</html>
