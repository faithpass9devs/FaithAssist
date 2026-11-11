<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_renders_the_login_page_for_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
    }
}
