<?php

namespace Tests\Support;

use CodeIgniter\Config\Services;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * HTTP-level tests: requests go through routing, filters (auth, admin,
 * CSRF), controllers and views exactly like a browser request.
 */
abstract class FeatureTestCase extends DatabaseTestCase
{
    use FeatureTestTrait;

    private string $csrfHash = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->csrfHash = bin2hex(random_bytes(16));
    }

    /** Session for a signed-in user (or a guest when $userId is null). */
    protected function sessionFor(?int $userId, string $role = 'user'): array
    {
        $session = ['csrf_test_name' => $this->csrfHash];

        if ($userId !== null) {
            $session += [
                'user_id'    => $userId,
                'user_name'  => 'Test User',
                'role'       => $role,
                'isLoggedIn' => true,
            ];
        }

        return $session;
    }

    /** Same token format a real form contains (tokenRandomize = true). */
    protected function csrfToken(): string
    {
        $key = random_bytes(16);

        return bin2hex((hex2bin($this->csrfHash) ^ $key) . $key);
    }

    protected function getAs(?int $userId, string $path, string $role = 'user'): TestResponse
    {
        Services::resetSingle('security');

        return $this->withSession($this->sessionFor($userId, $role))->get($path);
    }

    /** POST with a valid CSRF token unless $withCsrf is false. */
    protected function postAs(?int $userId, string $path, array $data = [], string $role = 'user', bool $withCsrf = true): TestResponse
    {
        Services::resetSingle('security');

        if ($withCsrf) {
            $data['csrf_test_name'] = $this->csrfToken();
        }

        return $this->withSession($this->sessionFor($userId, $role))->post($path, $data);
    }

    protected function assertRedirectsTo(TestResponse $response, string $path): void
    {
        $this->assertTrue($response->isRedirect(), 'Expected a redirect, got HTTP ' . $response->response()->getStatusCode());
        $this->assertSame(site_url($path), $response->getRedirectUrl());
    }

    protected function flash(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    /** In feature tests a 404 surfaces as PageNotFoundException. */
    protected function assertNotFound(callable $request): void
    {
        try {
            $response = $request();
            $this->assertSame(404, $response->response()->getStatusCode());
        } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }
}
