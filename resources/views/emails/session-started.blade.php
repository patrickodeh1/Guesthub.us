<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleaning Session Started</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; background: #f4f6f8; color: #1f2937; }
        .container { max-width: 560px; margin: 24px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 20px; color: #ffffff; font-weight: 600; }
        .header p { margin: 6px 0 0; font-size: 13px; color: rgba(255,255,255,0.85); }
        .body { padding: 28px 32px; }
        .detail { display: flex; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .detail:last-child { border-bottom: none; }
        .detail-label { width: 110px; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; flex-shrink: 0; }
        .detail-value { font-size: 14px; color: #1f2937; }
        .footer { padding: 16px 32px; background: #f9fafb; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧹 Cleaning Session Started</h1>
            <p>A cleaning session has begun</p>
        </div>
        <div class="body">
            <div class="detail">
                <div class="detail-label">Property</div>
                <div class="detail-value">{{ $propertyName }}</div>
            </div>
            <div class="detail">
                <div class="detail-label">Cleaner</div>
                <div class="detail-value">{{ $cleanerName }}</div>
            </div>
            <div class="detail">
                <div class="detail-label">Started At</div>
                <div class="detail-value">{{ $startTime }}</div>
            </div>
            <div class="detail">
                <div class="detail-label">Session ID</div>
                <div class="detail-value">#{{ $sessionId }}</div>
            </div>
        </div>
        <div class="footer">
            {{ config('app.name') }} &bull; Automated Notification
        </div>
    </div>
</body>
</html>
