<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_webhook_rejects_invalid_signature(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Config::set('services.payment.webhook_secret', 'test-secret');

        $payload = json_encode(['event' => 'payment.success', 'data' => ['id' => 1]]);
        
        // Use an invalid signature
        $invalidSignature = 'invalid-hash-12345';

        $response = $this->actingAs($user, 'web')
            ->postJson('/api/webhooks/payment', json_decode($payload, true), [
                'X-Signature' => $invalidSignature
            ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Invalid signature');
    }

    public function test_payment_webhook_accepts_valid_signature(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $secret = 'test-secret';
        Config::set('services.payment.webhook_secret', $secret);

        $payloadArray = ['event' => 'payment.success', 'data' => ['id' => 1]];
        // The API sends the array as json via postJson, so the content will be json_encode($payloadArray)
        $content = json_encode($payloadArray);
        
        $validSignature = hash_hmac('sha256', $content, $secret);

        $response = $this->actingAs($user, 'web')
            ->postJson('/api/webhooks/payment', $payloadArray, [
                'X-Signature' => $validSignature
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }
}
