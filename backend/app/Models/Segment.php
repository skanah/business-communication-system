<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Segment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'rules',
        'contact_count',
        'last_calculated_at',
        'is_active',
        'is_dynamic',
    ];

    protected $casts = [
        'rules' => 'array',
        'contact_count' => 'integer',
        'last_calculated_at' => 'datetime',
        'is_active' => 'boolean',
        'is_dynamic' => 'boolean',
    ];

    /**
     * Get the tenant that owns this segment
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get all campaigns that use this segment
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Get contacts in this segment (for static segments)
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_segment')
                    ->withTimestamps();
    }

    /**
     * Evaluate if a contact matches this segment's rules
     */
    public function matchesContact(Contact $contact): bool
    {
        if (!$this->is_dynamic) {
            // For static segments, check if contact is explicitly in the segment
            return $this->contacts()->where('contact_id', $contact->id)->exists();
        }

        // For dynamic segments, evaluate rules
        return $this->evaluateRules($contact, $this->rules);
    }

    /**
     * Evaluate segment rules against a contact
     */
    private function evaluateRules(Contact $contact, array $rules): bool
    {
        // Support for "all", "any", "none" conditions
        foreach ($rules as $condition => $ruleSet) {
            switch ($condition) {
                case 'all':
                    if (!$this->evaluateAllConditions($contact, $ruleSet)) {
                        return false;
                    }
                    break;
                case 'any':
                    if (!$this->evaluateAnyConditions($contact, $ruleSet)) {
                        return false;
                    }
                    break;
                case 'none':
                    if (!$this->evaluateNoneConditions($contact, $ruleSet)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Evaluate ALL conditions must be true
     */
    private function evaluateAllConditions(Contact $contact, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($contact, $condition)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate ANY condition must be true
     */
    private function evaluateAnyConditions(Contact $contact, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if ($this->evaluateCondition($contact, $condition)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Evaluate NONE of the conditions must be true
     */
    private function evaluateNoneConditions(Contact $contact, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if ($this->evaluateCondition($contact, $condition)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate a single condition
     */
    private function evaluateCondition(Contact $contact, array $condition): bool
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        // Get the field value from contact
        $fieldValue = $this->getContactFieldValue($contact, $field);

        // Evaluate based on operator
        return match ($operator) {
            '=' => $fieldValue == $value,
            '!=' => $fieldValue != $value,
            '>' => $fieldValue > $value,
            '<' => $fieldValue < $value,
            '>=' => $fieldValue >= $value,
            '<=' => $fieldValue <= $value,
            'contains' => str_contains((string)$fieldValue, $value),
            'starts_with' => str_starts_with((string)$fieldValue, $value),
            'ends_with' => str_ends_with((string)$fieldValue, $value),
            'in' => in_array($fieldValue, (array)$value),
            'not_in' => !in_array($fieldValue, (array)$value),
            'is_null' => is_null($fieldValue),
            'is_not_null' => !is_null($fieldValue),
            default => false,
        };
    }

    /**
     * Get field value from contact (supports nested attributes)
     */
    private function getContactFieldValue(Contact $contact, string $field)
    {
        // Check direct attributes first
        if (isset($contact->$field)) {
            return $contact->$field;
        }

        // Check JSON attributes
        if (isset($contact->attributes[$field])) {
            return $contact->attributes[$field];
        }

        // Check tags
        if ($field === 'tags') {
            return $contact->tags;
        }

        return null;
    }

    /**
     * Recalculate contact count for this segment
     */
    public function recalculateContactCount(): void
    {
        if ($this->is_dynamic) {
            // For dynamic segments, count contacts matching rules
            $count = $this->getMatchingContactsQuery()->count();
        } else {
            // For static segments, count explicit memberships
            $count = $this->contacts()->count();
        }

        $this->update([
            'contact_count' => $count,
            'last_calculated_at' => now(),
        ]);
    }

    /**
     * Get query builder for contacts matching this segment's rules
     */
    public function getMatchingContactsQuery()
    {
        if (!$this->is_dynamic) {
            // For static segments, return contacts explicitly added to this segment
            return $this->contacts();
        }

        // For dynamic segments, build query based on rules
        $query = Contact::where('tenant_id', $this->tenant_id);

        if (!empty($this->rules)) {
            $this->applyRulesToQuery($query, $this->rules);
        }

        return $query;
    }

    /**
     * Apply segment rules to a query builder
     */
    private function applyRulesToQuery($query, array $rules): void
    {
        foreach ($rules as $condition => $ruleSet) {
            switch ($condition) {
                case 'all':
                    $this->applyAllConditionsToQuery($query, $ruleSet);
                    break;
                case 'any':
                    $this->applyAnyConditionsToQuery($query, $ruleSet);
                    break;
                case 'none':
                    $this->applyNoneConditionsToQuery($query, $ruleSet);
                    break;
            }
        }
    }

    /**
     * Apply ALL conditions to query (AND logic)
     */
    private function applyAllConditionsToQuery($query, array $conditions): void
    {
        foreach ($conditions as $condition) {
            $this->applySingleConditionToQuery($query, $condition);
        }
    }

    /**
     * Apply ANY conditions to query (OR logic)
     */
    private function applyAnyConditionsToQuery($query, array $conditions): void
    {
        $query->where(function ($subQuery) use ($conditions) {
            foreach ($conditions as $condition) {
                $subQuery->orWhere(function ($innerQuery) use ($condition) {
                    $this->applySingleConditionToQuery($innerQuery, $condition, true);
                });
            }
        });
    }

    /**
     * Apply NONE conditions to query (NOT logic)
     */
    private function applyNoneConditionsToQuery($query, array $conditions): void
    {
        foreach ($conditions as $condition) {
            $query->where(function ($subQuery) use ($condition) {
                $subQuery->whereRaw('1 = 1') // Always true placeholder
                    ->where(function ($innerQuery) use ($condition) {
                        $this->applySingleConditionToQuery($innerQuery, $condition);
                    }, null, null, 'and', true); // This negates the condition
            });
        }
    }

    /**
     * Apply a single condition to query
     */
    private function applySingleConditionToQuery($query, array $condition, bool $useOr = false): void
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        // Handle date/datetime operators with relative dates
        if (in_array($operator, ['>', '>=', '<', '<=']) && is_string($value) && preg_match('/^-?\d+d$/', $value)) {
            $value = now()->addDays((int) $value);
        }

        // Determine if this is a direct field or a JSON attribute
        if ($this->isDirectField($field)) {
            // Direct field on the contacts table
            $method = $useOr ? 'orWhere' : 'where';
            $this->applyOperatorToQuery($query, $method, $field, $operator, $value);
        } else {
            // JSON attribute field
            $method = $useOr ? 'orWhereJsonContains' : 'whereJsonContains';
            $this->applyJsonOperatorToQuery($query, $method, $field, $operator, $value);
        }
    }

    /**
     * Check if field is a direct field on the contacts table
     */
    private function isDirectField(string $field): bool
    {
        $directFields = [
            'id', 'tenant_id', 'phone', 'email', 'first_name', 'last_name', 'company',
            'sms_consent', 'email_consent', 'sms_consent_given_at', 'email_consent_given_at',
            'last_contacted_at', 'last_reply_at', 'timezone', 'country_code', 'status'
        ];

        return in_array($field, $directFields);
    }

    /**
     * Apply operator to query for direct fields
     */
    private function applyOperatorToQuery($query, string $method, string $field, string $operator, $value): void
    {
        switch ($operator) {
            case '=':
                $query->$method($field, $value);
                break;
            case '!=':
                $query->$method($field, '!=', $value);
                break;
            case '>':
                $query->$method($field, '>', $value);
                break;
            case '<':
                $query->$method($field, '<', $value);
                break;
            case '>=':
                $query->$method($field, '>=', $value);
                break;
            case '<=':
                $query->$method($field, '<=', $value);
                break;
            case 'contains':
                $query->$method($field, 'LIKE', '%' . $value . '%');
                break;
            case 'starts_with':
                $query->$method($field, 'LIKE', $value . '%');
                break;
            case 'ends_with':
                $query->$method($field, 'LIKE', '%' . $value);
                break;
            case 'in':
                $query->whereIn($field, $value);
                break;
            case 'not_in':
                $query->whereNotIn($field, $value);
                break;
            case 'is_null':
                $query->$methodNull($field);
                break;
            case 'is_not_null':
                $query->$methodNotNull($field);
                break;
            default:
                $query->$method($field, $value);
                break;
        }
    }

    /**
     * Apply operator to query for JSON fields
     */
    private function applyJsonOperatorToQuery($query, string $method, string $field, string $operator, $value): void
    {
        // For JSON fields, we'll use a more generic approach
        switch ($operator) {
            case '=':
                $query->whereJsonContains('attributes', [$field => $value]);
                break;
            case '!=':
                $query->whereJsonDoesntContain('attributes', [$field => $value]);
                break;
            case 'contains':
                $query->where(function ($subQuery) use ($field, $value) {
                    $subQuery->whereRaw("JSON_EXTRACT(attributes, '$.\"{$field}\"') LIKE ?", ['%' . $value . '%'])
                             ->orWhereRaw("JSON_EXTRACT(attributes, '$.\"{$field}\"') LIKE ?", [$value . '%'])
                             ->orWhereRaw("JSON_EXTRACT(attributes, '$.\"{$field}\"') LIKE ?", ['%' . $value]);
                });
                break;
            case 'in':
                $query->where(function ($subQuery) use ($field, $value) {
                    foreach ($value as $val) {
                        $subQuery->orWhereRaw("JSON_EXTRACT(attributes, '$.\"{$field}\"') = ?", [$val]);
                    }
                });
                break;
            case 'not_in':
                $query->where(function ($subQuery) use ($field, $value) {
                    foreach ($value as $val) {
                        $subQuery->whereRaw("JSON_EXTRACT(attributes, '$.\"{$field}\"') != ?", [$val]);
                    }
                });
                break;
            case 'is_null':
                $query->where(function ($subQuery) use ($field) {
                    $subQuery->whereRaw("JSON_EXTRACT(attributes, '$.\"{$field}\"') IS NULL")
                             ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.\"{$field}\"')) = ''");
                });
                break;
            case 'is_not_null':
                $query->whereRaw("JSON_EXTRACT(attributes, '$.\"{$field}\"') IS NOT NULL")
                      ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.\"{$field}\"')) != ''");
                break;
            default:
                $query->whereJsonContains('attributes', [$field => $value]);
                break;
        }
    }

    /**
     * Get contacts matching this segment's rules
     */
    public function getMatchingContacts(int $limit = null)
    {
        $query = $this->getMatchingContactsQuery();

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Count contacts matching this segment's rules
     */
    public function countMatchingContacts(): int
    {
        if (!$this->is_dynamic) {
            return $this->contacts()->count();
        }

        return $this->getMatchingContactsQuery()->count();
    }

    /**
     * Scope for active segments only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for dynamic segments
     */
    public function scopeDynamic($query)
    {
        return $query->where('is_dynamic', true);
    }
}
