<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DeploymentUrlsTest extends TestCase
{
    public function test_https_deployment_generates_secure_forms_assets_and_guest_redirects_over_http_upstream(): void
    {
        $this->withoutVite();
        $this->app->instance('env', 'production');
        config(['app.url' => 'https://bills.example.test', 'session.driver' => 'array']);
        (new AppServiceProvider($this->app))->boot();

        $this->get('http://bills.example.test/login')->assertOk()->assertSee('action="https://bills.example.test/login"', false);
        $this->get('http://bills.example.test/workspace')->assertRedirect('https://bills.example.test/login');
        $this->assertStringStartsWith('https://', URL::asset('build/assets/example.css'));
    }

    public function test_local_http_development_keeps_http_urls(): void
    {
        $this->withoutVite();
        $this->app->instance('env', 'local');
        config(['app.url' => 'http://localhost:8091', 'session.driver' => 'array']);
        (new AppServiceProvider($this->app))->boot();

        $this->get('http://localhost:8091/login')->assertOk()->assertSee('action="http://localhost:8091/login"', false);
    }
}
