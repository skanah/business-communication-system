<?php

return [
    'ice_servers' => [
        'stun' => [
            'stun:stun.l.google.com:19302',
            'stun:stun1.l.google.com:19302',
            'stun:stun2.l.google.com:19302',
            'stun:stun3.l.google.com:19302',
            'stun:stun4.l.google.com:19302'
        ],
        'turn' => [
            // Example TURN server configuration
            // 'turn:your-turn-server.com:3478',
            // 'turn:your-turn-server.com:3478?transport=tcp'
        ]
    ],
    'turn_username' => env('TURN_USERNAME'),
    'turn_credential' => env('TURN_CREDENTIAL'),
    'default_video_constraints' => [
        'width' => ['min' => 320, 'max' => 1280],
        'height' => ['min' => 240, 'max' => 720],
        'frameRate' => ['min' => 15, 'max' => 30]
    ],
    'default_audio_constraints' => [
        'echoCancellation' => true,
        'noiseSuppression' => true,
        'autoGainControl' => true
    ],
    'signaling_server' => env('WEBRTC_SIGNALING_SERVER', 'wss://signaling.example.com'),
    'max_bitrate' => env('WEBRTC_MAX_BITRATE', 1500), // kbps
    'codec_preferences' => [
        'video' => ['VP8', 'H264', 'VP9'],
        'audio' => ['opus', 'PCMU', 'PCMA']
    ]
];
