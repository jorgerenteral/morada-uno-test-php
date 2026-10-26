<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    public function test_unknown_routes_return_a_json_not_found_error(): void
    {
        $this->get('/unknown', ['Accept' => 'text/html'])
            ->assertNotFound()
            ->assertExactJson([
                'error' => 'not_found',
                'message' => 'Resource not found',
            ]);
    }

    public function test_health_endpoint_is_still_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
