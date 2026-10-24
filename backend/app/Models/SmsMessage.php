<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sender_number',
        'receiver_number',
        'message_content',
        'message_type',
        'message_status',
        'provider',
        'provider_message_id',
        'cost',
        'currency'
    ];

    protected $casts = [
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
