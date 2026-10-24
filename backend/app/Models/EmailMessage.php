<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sender_email',
        'receiver_email',
        'subject',
        'message_content',
        'html_content',
        'message_type',
        'message_status',
        'provider',
        'provider_message_id',
        'attachments',
        'size_bytes',
        'cost',
        'currency',
        'error_message'
    ];

    protected $casts = [
        'attachments' => 'array',
        'size_bytes' => 'integer',
        'cost' => 'integer'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function communicationProfile()
    {
        return $this->hasOne(CommunicationProfile::class, 'user_id', 'user_id');
    }
}
