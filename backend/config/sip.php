<?php

return [
    'server' => env('SIP_SERVER', 'sip.example.com'),
    'port' => env('SIP_PORT', 5060),
    'transport' => env('SIP_TRANSPORT', 'udp'),
    'domain' => env('SIP_DOMAIN', 'sip.example.com'),
    'websocket_port' => env('SIP_WEBSOCKET_PORT', 5066),
    'default_username_prefix' => 'user_',
    'default_password_length' => 16,
    'call_recording_enabled' => env('SIP_CALL_RECORDING', false),
    'call_recording_storage' => env('SIP_RECORDING_STORAGE', 'local'),
    'max_call_duration' => env('SIP_MAX_CALL_DURATION', 3600), // in seconds
    'codecs' => [
        'audio' => ['opus', 'pcmu', 'pcma', 'g722'],
        'video' => ['vp8', 'h264']
    ]
];
