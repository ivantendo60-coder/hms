<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_landing_page_is_available(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('myPatient HMS');
        $response->assertSee('Better care starts with a');
        $response->assertSee('Support for every step of care');
        $response->assertSee('Hospital departments');
    }
}
