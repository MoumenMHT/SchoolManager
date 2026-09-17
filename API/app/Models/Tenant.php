<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends BaseTenant
{
    protected $guarded = [];

    /**
     * Custom data columns stored in the `data` JSON column.
     * Only non-indexed metadata stays here; critical fields are real columns.
     */
    public static array $dataColumns = [
        'name',
        'logo_url',
        'address',
        'phone',
        'email',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'is_active',
            'last_activation_at',
            'last_deactivation_at',
            'contact_name',
            'contact_email',
            'contact_phone',
            'subscription_plan',
            'subscription_expires_at',
            'max_students',
            'notes',
        ];
    }

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_activation_at' => 'datetime',
        'last_deactivation_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
        'max_students' => 'integer',
    ];

    // ── Relationships ────────────────────────────────────

    public function platformPayments(): HasMany
    {
        return $this->hasMany(PlatformPayment::class, 'tenant_id', 'id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TenantActivityLog::class, 'tenant_id', 'id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id', 'id');
    }

    // ── Scopes ───────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('subscription_expires_at')
            ->where('subscription_expires_at', '<=', now()->addDays($days));
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('subscription_expires_at')
            ->where('subscription_expires_at', '<', now());
    }

    public function scopeByPlan(Builder $query, string $plan): Builder
    {
        return $query->where('subscription_plan', $plan);
    }

    // ── Helpers ──────────────────────────────────────────

    public function activate(): void
    {
        $this->update([
            'is_active' => true,
            'last_activation_at' => now(),
        ]);
    }

    public function deactivate(): void
    {
        $this->update([
            'is_active' => false,
            'last_deactivation_at' => now(),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->subscription_expires_at !== null
            && $this->subscription_expires_at->isPast();
    }

    public function usersCount(): int
    {
        return $this->users()->count();
    }

    public function studentsCount(): int
    {
        return Student::where('tenant_id', $this->id)->count();
    }

    public function teachersCount(): int
    {
        return Teacher::where('tenant_id', $this->id)->count();
    }
}
