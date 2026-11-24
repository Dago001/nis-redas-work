<?php

namespace Tests\Feature;

use Tests\TestCase;

class RegistrationFormFieldsTest extends TestCase
{
    public function test_public_register_route_redirects_to_login_when_hq_managed(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
    }
}
