<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TenantScopedModel;

class Contact extends Model
{
    use HasFactory;
    use SoftDeletes;
    use TenantScopedModel;

    protected $fillable = [
        'tenant_id',
        'phone',
        'email',
        'first_name',
        'last_name',
        'company',
        'sms_consent',
        'email_consent',
        'sms_consent_given_at',
        'email_consent_given_at',
        'attributes',
        'tags',
        'last_contacted_at',
        'last_reply_at',
        'timezone',
        'country_code',
        'status',
    ];

    protected $dates = ['deleted_at'];

    protected $casts = [
        'sms_consent' => 'boolean',
        'email_consent' => 'boolean',
        'sms_consent_given_at' => 'datetime',
        'email_consent_given_at' => 'datetime',
        'deleted_at' => 'datetime',
        'attributes' => 'array',
        'tags' => 'array',
        'last_contacted_at' => 'datetime',
        'last_reply_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns this contact
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get all messages sent to this contact
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get all conversations with this contact
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get the full name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Check if contact has SMS consent
     */
    public function hasSmsConsent(): bool
    {
        return $this->sms_consent && $this->status === 'active';
    }

    /**
     * Check if contact has email consent
     */
    public function hasEmailConsent(): bool
    {
        return $this->email_consent && $this->status === 'active';
    }

    /**
     * Check if contact has a specific tag
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? []);
    }

    /**
     * Add a tag to the contact
     */
    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    /**
     * Remove a tag from the contact
     */
    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        $tags = array_diff($tags, [$tag]);
        $this->update(['tags' => array_values($tags)]);
    }

    /**
     * Scope for active contacts only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for contacts with SMS consent
     */
    public function scopeSmsConsent($query)
    {
        return $query->where('sms_consent', true)->active();
    }

    /**
     * Scope for contacts with email consent
     */
    public function scopeEmailConsent($query)
    {
        return $query->where('email_consent', true)->active();
    }
}
