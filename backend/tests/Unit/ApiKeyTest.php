<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\ApiKey;
use App\Models\Tenant;
use App\Http\Middleware\ApiKeyMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiKeyTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_api_key_middleware_sets_tenant_id()
    {
        // Create a tenant
        $tenant = Tenant::factory()->create();
        
        // Create an API key
        $apiKey = ApiKey::factory()->create([
            'tenant_id' => $tenant->id,
            'permissions' => ['*']
        ]);

        // Create a mock request with the API key
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $apiKey->key);

        // Create the middleware instance
        $middleware = new ApiKeyMiddleware();

        // Call the middleware
        $response = $middleware->handle($request, function ($req) {
            // Check if tenant_id is set on the request
            $this->assertEquals($req->tenant_id, $req->user()->tenant->id ?? $req->get('tenant_id'));
            return response('Success');
        });

        $this->assertEquals($request->get('tenant_id'), $tenant->id);
    }
}