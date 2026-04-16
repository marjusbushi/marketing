<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Unauthenticated users must not be able to access the marketing root.
     * Accept either 302 (redirect to login) or 401 (unauthorized) — Laravel
     * returns 401 when no named login route is registered.
     */
    public function test_the_application_blocks_unauthenticated_users(): void
    {
        $response = $this->get('/');

        $this->assertContains(
            $response->status(),
            [302, 401, 403],
            "Expected 302/401/403 for unauthenticated root access, got {$response->status()}"
        );
    }
}
