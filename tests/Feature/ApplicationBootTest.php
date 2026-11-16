<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationBootTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_returns_http_200_or_redirect_on_get_root(): void
    {
        $response = $this->get('/');

        $this->assertContains($response->status(), [200, 302]);
    }

    public function test_app_returns_http_200_on_get_login(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_app_key_is_set_and_app_does_not_throw_on_boot(): void
    {
        $this->assertNotEmpty(config('app.key'));
        $this->assertTrue(app()->isBooted());
    }
}
