<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'caller_id',
        'receiver_id',
        'call_type',
        'call_status',
        'start_time',
        'end_time',
        'duration_seconds',
        'call_method',
        'notes',
        'recording_url'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_seconds' => 'integer'
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
