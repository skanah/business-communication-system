<?php

return [
    'provider' => env('SMS_PROVIDER', 'smppe'),
    'api_endpoint' => env('SMS_API_ENDPOINT', 'https://sms-gateway.example.com/api'),
    'api_key' => env('SMS_API_KEY', 'your-api-key'),
    'sender_id' => env('SMS_SENDER_ID', 'COMMUNIC'),
    'character_limit' => env('SMS_CHARACTER_LIMIT', 160),
    'cost_per_message' => env('SMS_COST_PER_MESSAGE', 0.01),
    'currency' => env('SMS_CURRENCY', 'USD'),
    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', 'US'),
    'delivery_report_enabled' => env('SMS_DELIVERY_REPORT', true),
    'message_expiry' => env('SMS_MESSAGE_EXPIRY', 24), // hours
    'rate_limits' => [
        'per_minute' => env('SMS_RATE_LIMIT_PER_MINUTE', 10),
        'per_hour' => env('SMS_RATE_LIMIT_PER_HOUR', 100),
        'per_day' => env('SMS_RATE_LIMIT_PER_DAY', 1000)
    ],
    'supported_countries' => [
        'US', 'CA', 'GB', 'AU', 'DE', 'FR', 'ES', 'IT', 'NL', 'BE'
    ]
];
