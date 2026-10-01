<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root route is the public landing page and links into registration and login.
     */
    public function test_the_root_route_shows_the_public_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Help is one report away.')
            ->assertSee('href="'.route('register').'"', escape: false)
            ->assertSee('href="'.route('login').'"', escape: false)
            ->assertSee('Meet the developers');
    }
}
