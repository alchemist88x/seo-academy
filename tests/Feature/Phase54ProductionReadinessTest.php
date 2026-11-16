<?php

namespace Tests\Feature;

use Tests\TestCase;

class Phase54ProductionReadinessTest extends TestCase
{
    public function test_security_headers_are_present_on_web_responses(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'self'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_app_version_is_configured_and_matches_release(): void
    {
        $version = config('app.version');
        $this->assertSame('5.4.0', $version);
    }

    public function test_app_upgrade_command_executes_safely_on_installed_system(): void
    {
        $this->artisan('app:upgrade', ['--force' => true, '--skip-cache-clear' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Target: v5.4.0')
            ->expectsOutputToContain('completed successfully');
    }
}
