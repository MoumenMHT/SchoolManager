<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected $tenant = null;

    protected function setUp(): void
    {
        parent::setUp();

        $usesDatabase = in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive($this))
            || in_array(\Illuminate\Foundation\Testing\DatabaseMigrations::class, class_uses_recursive($this))
            || in_array(\Illuminate\Foundation\Testing\DatabaseTransactions::class, class_uses_recursive($this));

        if ($usesDatabase) {
            $this->tenant = \App\Models\Tenant::firstOrCreate(
                ['id' => 'test_school'],
                ['data' => ['name' => 'Test School']]
            );

            tenancy()->initialize($this->tenant);
        }
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    public function json($method, $uri, array $data = [], array $headers = [], $options = 0)
    {
        if ($this->tenant && !str_starts_with($uri, '/api/central') && str_starts_with($uri, '/api/')) {
            $uri = str_replace('/api/', '/api/t/' . $this->tenant->id . '/', $uri);
        }

        return parent::json($method, $uri, $data, $headers, $options);
    }
}
