<?php

use App\Filters\SecurityHeadersFilter;
use Tests\Support\FeatureTestCase;

/**
 * R6: Content-Security-Policy.
 *
 * @internal
 */
final class ContentSecurityPolicyTest extends FeatureTestCase
{
    protected function getAs(?int $userId, string $path, string $role = 'user'): \CodeIgniter\Test\TestResponse
    {
        // The test harness reuses one Response object between requests; real
        // requests get a fresh one, so headers from a previous call must not leak.
        \Config\Services::resetSingle('response');

        return parent::getAs($userId, $path, $role);
    }

    private function csp(\CodeIgniter\Test\TestResponse $response): string
    {
        return $response->response()->getHeaderLine('Content-Security-Policy');
    }

    public function testPagesSendAPolicyThatForbidsInlineScripts(): void
    {
        $policy = $this->csp($this->getAs(null, 'login'));

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertMatchesRegularExpression("/script-src 'self' https:\\/\\/cdn\\.jsdelivr\\.net(;|$)/", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'self'", $policy);
        $this->assertStringContainsString('form-action', $policy, 'Top-up redirects to the hosted checkout must be allowed explicitly.');

        $scriptSrc = array_values(array_filter(SecurityHeadersFilter::policy(false), static fn ($d) => str_starts_with($d, 'script-src')))[0];
        $this->assertStringNotContainsString('unsafe-inline', $scriptSrc);
    }

    public function testSignedInPagesHaveNoInlineScripts(): void
    {
        $user = $this->createUser();

        foreach (['dashboard', 'post-request', 'wallet', 'browse-requests', 'my-requests', 'accepted-requests', 'profile'] as $page) {
            $html = $this->getAs($user, $page)->getBody();
            preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>(.*?)<\/script>/s', $html, $m, PREG_SET_ORDER);
            $executable = array_filter($m, static fn ($s) => ! str_contains($s[1], 'application/json') && ! str_contains($s[1], 'debugbar') && ! str_contains($s[1], 'kint'));
            $this->assertSame([], array_values($executable), "Inline script on /{$page}");
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+="/', $html, "Inline event handler on /{$page}");
        }
    }

    public function testPrivateFilesKeepTheirOwnSandboxPolicy(): void
    {
        $requester = $this->createUser();
        $runner    = $this->createUser();
        $id        = $this->createRequest($requester);
        (new \App\Services\RequestLifecycle())->accept($id, $runner);
        $dir  = \App\Libraries\UploadStorage::directory(\App\Libraries\UploadStorage::RECEIPTS);
        is_dir($dir) || mkdir($dir, 0775, true);
        $name = 'csp_' . bin2hex(random_bytes(6)) . '.pdf';
        file_put_contents($dir . $name, "%PDF-1.4\n%%EOF\n");
        $submission = (new \App\Services\PriceApproval())->submit($id, $runner, '100', ['path' => $name, 'name' => 'r.pdf', 'type' => 'application/pdf']);

        try {
            $policy = $this->csp($this->getAs($requester, 'request/' . $id . '/receipt/' . $submission));
            $this->assertStringContainsString('sandbox', $policy);
            $this->assertStringNotContainsString('cdn.jsdelivr.net', $policy, 'The page policy must not replace the file sandbox.');
        } finally {
            @unlink($dir . $name);
        }
    }

    public function testReportOnlyModeAndOff(): void
    {
        config('PasaBuy')->cspMode = 'report-only';
        $response = $this->getAs(null, 'login');
        $this->assertSame('', $this->csp($response));
        $this->assertStringContainsString("default-src 'self'", $response->response()->getHeaderLine('Content-Security-Policy-Report-Only'));

        config('PasaBuy')->cspMode = 'off';
        $this->assertSame('', $this->csp($this->getAs(null, 'login')));

        config('PasaBuy')->cspMode = 'enforce';
    }
}
