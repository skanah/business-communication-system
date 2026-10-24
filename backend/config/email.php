<?php

return [
    'daily_limit' => env('EMAIL_DAILY_LIMIT', 100),
    'attachment_limit' => env('EMAIL_ATTACHMENT_LIMIT', 10), // MB
    'cost_per_message' => env('EMAIL_COST_PER_MESSAGE', 0.001),
    'currency' => env('EMAIL_CURRENCY', 'USD'),
    'max_recipients' => env('EMAIL_MAX_RECIPIENTS', 50),
    'allowed_attachments' => [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'jpg', 'jpeg', 'png', 'gif', 'txt', 'csv', 'zip'
    ],
    'storage_disk' => env('EMAIL_STORAGE_DISK', 'public'),
    'attachment_path' => env('EMAIL_ATTACHMENT_PATH', 'email_attachments'),
    'rate_limits' => [
        'per_minute' => env('EMAIL_RATE_LIMIT_PER_MINUTE', 10),
        'per_hour' => env('EMAIL_RATE_LIMIT_PER_HOUR', 50),
        'per_day' => env('EMAIL_RATE_LIMIT_PER_DAY', 100)
    ],
    'spam_protection' => [
        'enabled' => env('EMAIL_SPAM_PROTECTION', true),
        'max_links' => env('EMAIL_MAX_LINKS', 5),
        'forbidden_words' => explode(',', env('EMAIL_FORBIDDEN_WORDS', 'viagra,casino,loan'))
    ]
];
