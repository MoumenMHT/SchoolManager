<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant
{
    /**
     * Custom data columns stored in the `data` JSON column.
     * These are accessible as direct properties via the VirtualColumn trait.
     */
    public static array $dataColumns = [
        'name',
        'logo_url',
        'address',
        'phone',
        'email',
        'is_active',
        'subscription_plan',
        'subscription_expires_at',
    ];

    // No HasDatabase — single shared database mode, no per-tenant DB needed.
}
