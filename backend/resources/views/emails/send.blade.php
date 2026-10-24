<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Email from ' . config('app.name') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 5px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #6c757d;
        }
        .message-content {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $subject ?? 'Message' }}</h2>
        @if(isset($sender))
            <p><strong>From:</strong> {{ $sender }}</p>
        @endif
    </div>

    <div class="content">
        @if(isset($htmlContent) && !empty($htmlContent))
            {!! $htmlContent !!}
        @else
            <div class="message-content">
                {{ $message }}
            </div>
        @endif
    </div>

    <div class="footer">
        <p>This email was sent via {{ config('app.name') }} communication system.</p>
        <p>If you believe this email was sent in error, please ignore it.</p>
    </div>
</body>
</html>
