<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_registration_post_is_disabled_and_does_not_create_users(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Jane Doe',
            'service_number' => 'NIS/ADM/1234',
            'role' => 'officer',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => 'on',
        ]);

        $response->assertStatus(405);

        $this->assertDatabaseMissing('users', [
            'email' => 'jane@example.com',
        ]);
    }
}
