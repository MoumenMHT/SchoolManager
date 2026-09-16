<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExportSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_filename_is_sanitized(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($user, 'web')
            ->getJson('/api/schedules/export?academic_year=2024"onload="alert(1)');
            
        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        
        // Ensure the filename is sanitized (no quotes or invalid chars)
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('filename="schedules_2024_onload__alert_1_.xls"', $disposition);
    }
}
