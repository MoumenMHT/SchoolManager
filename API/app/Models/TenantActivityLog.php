<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantActivityLog extends Model
{
    protected $table = 'tenant_activity_log';

    protected $fillable = [
        'tenant_id',
        'performed_by',
        'action',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    // ── Relationships ────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // ── Static Helpers ───────────────────────────────────

    /**
     * Record an activity log entry for a tenant.
     */
    public static function log(string $tenantId, string $action, ?array $details = null, ?int $userId = null): self
    {
        return static::create([
            'tenant_id' => $tenantId,
            'action' => $action,
            'details' => $details,
            'performed_by' => $userId ?? auth()->id(),
        ]);
    }

    // ── Scopes ───────────────────────────────────────────

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeRecent(Builder $query, int $limit = 20): Builder
    {
        return $query->latest()->limit($limit);
    }
}
