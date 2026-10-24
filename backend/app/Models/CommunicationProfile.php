<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sip_username',
        'sip_password',
        'sip_domain',
        'webrtc_id',
        'email_address',
        'sms_number',
        'call_forwarding_enabled',
        'call_forwarding_number',
        'voicemail_enabled',
        'voicemail_pin',
        'sms_notifications',
        'email_notifications',
        'webrtc_enabled'
    ];

    protected $casts = [
        'call_forwarding_enabled' => 'boolean',
        'voicemail_enabled' => 'boolean',
        'sms_notifications' => 'boolean',
        'email_notifications' => 'boolean',
        'webrtc_enabled' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function callLogs()
    {
        return $this->hasMany(CallLog::class, 'user_id', 'user_id');
    }

    public function smsMessages()
    {
        return $this->hasMany(SmsMessage::class, 'user_id', 'user_id');
    }

    public function emailMessages()
    {
        return $this->hasMany(EmailMessage::class, 'user_id', 'user_id');
    }
}
