<?php

/*
 * Pasa-Buy black-box smoke test for a running deployment.
 *
 *   php scripts/smoke-test.php <base-url> <user-email> <user-password> [admin-email admin-password]
 *
 * Env: SMOKE_INSECURE=1 to accept a self-signed certificate (local only).
 * Uses only PHP's curl extension; changes nothing except signing in/out.
 * Exit code 0 = all checks passed.
 */

if ($argc < 4) {
    fwrite(STDERR, "Usage: php scripts/smoke-test.php <base-url> <user-email> <user-password> [admin-email admin-password]\n");
    exit(2);
}

[$script, $base, $email, $password] = $argv;
$adminEmail    = $argv[4] ?? null;
$adminPassword = $argv[5] ?? null;
$base          = rtrim($base, '/') . '/';
$isHttps       = str_starts_with($base, 'https://');
$failures      = 0;
$jar           = tempnam(sys_get_temp_dir(), 'pbsmoke');

function http(string $method, string $url, array $form = [], bool $follow = false): array
{
    global $jar;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => getenv('SMOKE_INSECURE') !== '1',
        CURLOPT_SSL_VERIFYHOST => getenv('SMOKE_INSECURE') === '1' ? 0 : 2,
        CURLOPT_USERAGENT      => 'pasabuy-smoke-test',
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }

    $raw = curl_exec($ch);

    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return ['status' => 0, 'headers' => '', 'body' => '', 'location' => '', 'error' => $error];
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $result     = [
        'status'   => curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'headers'  => substr($raw, 0, $headerSize),
        'body'     => substr($raw, $headerSize),
        'location' => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL),
    ];
    curl_close($ch);

    return $result;
}

function check(bool $ok, string $label, string $detail = ''): void
{
    global $failures;
    $failures += $ok ? 0 : 1;
    echo ($ok ? '  PASS ' : '  FAIL ') . $label . ($ok || $detail === '' ? '' : "  ({$detail})") . PHP_EOL;
}

function csrf(string $html): ?string
{
    return preg_match('/name="csrf_test_name" value="([^"]+)"/', $html, $m) ? $m[1] : null;
}

function header_value(string $headers, string $name): ?string
{
    return preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/mi', $headers, $m) ? trim($m[1]) : null;
}

echo "Smoke testing {$base}\n";

// ------------------------------------------------------------- platform
echo "Platform\n";
$health = http('GET', $base . 'health');
$json   = json_decode($health['body'], true);
check($health['status'] === 200 && ($json['status'] ?? null) === 'ok', 'GET /health is 200 ok', $health['status'] . ' ' . substr($health['body'], 0, 120));

if ($isHttps) {
    $plain = http('GET', 'http://' . substr($base, 8) . 'login');
    check(in_array($plain['status'], [301, 302, 307, 308], true) && str_starts_with($plain['location'], 'https://'), 'HTTP redirects to HTTPS', $plain['status'] . ' ' . $plain['location']);
    check(header_value($health['headers'], 'Strict-Transport-Security') !== null, 'HSTS header present');
}

$login = http('GET', $base . 'login');
check($login['status'] === 200 && csrf($login['body']) !== null, 'GET /login renders with a CSRF token');
check(header_value($login['headers'], 'X-Frame-Options') !== null, 'X-Frame-Options header present');
check(header_value($login['headers'], 'X-Content-Type-Options') === 'nosniff', 'X-Content-Type-Options: nosniff');
check(! preg_match('/Kint|debugbar|CodeIgniter\\\\Debug|toolbarContainer/i', $login['body']), 'No debug toolbar or debug output');

if ($isHttps) {
    $cookie = (string) header_value($login['headers'], 'Set-Cookie');
    check(stripos($cookie, 'secure') !== false && stripos($cookie, 'httponly') !== false, 'Session cookie is Secure and HttpOnly', $cookie);
}

echo "Sensitive files are not served\n";
foreach (['.env', 'env', '.env.example', 'composer.json', 'composer.lock', 'spark', 'app/Config/Database.php', 'vendor/autoload.php', 'writable/logs/', 'writable/uploads/', 'writable/session/', '.git/config', 'tests/README.md', 'deploy/env.production.example', 'phpunit.xml.dist', 'uploads/profile_photos/'] as $path) {
    $r = http('GET', $base . $path);
    check($r['status'] >= 400 && ! str_contains($r['body'], 'database.default'), '/' . $path . ' -> ' . $r['status']);
}

// ---------------------------------------------------------------- user
echo "User session\n";
$guest = http('GET', $base . 'dashboard');
check(in_array($guest['status'], [302, 303], true) && str_contains($guest['location'], '/login'), 'Guests are redirected from /dashboard');

$forged = http('POST', $base . 'login/process', ['email' => $email, 'password' => $password]);
$after  = http('GET', $base . 'dashboard');
check(str_contains($after['location'], '/login'), 'Login without CSRF token is rejected');

$token = csrf(http('GET', $base . 'login')['body']);
$bad   = http('POST', $base . 'login/process', ['csrf_test_name' => $token, 'email' => $email, 'password' => $password . 'x']);
$page  = http('GET', $base . 'login');
check(str_contains($page['body'], 'Invalid email or password.'), 'Wrong password shows "Invalid email or password."');

$token = csrf($page['body']);
$ok    = http('POST', $base . 'login/process', ['csrf_test_name' => $token, 'email' => $email, 'password' => $password]);
check(str_ends_with(rtrim($ok['location'], '/'), '/dashboard'), 'Valid login redirects to /dashboard', $ok['status'] . ' ' . $ok['location']);

foreach (['dashboard', 'browse-requests', 'my-requests', 'accepted-requests', 'wallet', 'messages', 'notifications', 'profile', 'post-request'] as $path) {
    $r = http('GET', $base . $path);
    check($r['status'] === 200 && ! preg_match('/Fatal error|Exception|Undefined (variable|index|array key)/', $r['body']), 'GET /' . $path . ' -> 200', (string) $r['status']);
    check(! str_contains($r['body'], 'http-equiv="refresh"'), '/' . $path . ' does not auto-refresh');

    if ($path === 'wallet') {
        check(str_contains($r['body'], 'demo') || str_contains($r['body'], 'Demo'), 'Wallet states that funds are demo credits');
    }
}

$count = http('GET', $base . 'notifications/unread-count');
check($count['status'] === 200 && is_array(json_decode($count['body'], true)), 'Unread-count endpoint returns JSON');

$admin = http('GET', $base . 'admin/dashboard');
check(in_array($admin['status'], [302, 303], true), 'Ordinary user cannot open /admin/dashboard');

$logoutToken = csrf(http('GET', $base . 'dashboard')['body']);
http('POST', $base . 'logout', ['csrf_test_name' => $logoutToken]);
check(str_contains(http('GET', $base . 'dashboard')['location'], '/login'), 'Logout ends the session');

// --------------------------------------------------------------- admin
if ($adminEmail !== null && $adminPassword !== null) {
    echo "Admin session\n";
    $token = csrf(http('GET', $base . 'login')['body']);
    $r     = http('POST', $base . 'login/process', ['csrf_test_name' => $token, 'email' => $adminEmail, 'password' => $adminPassword]);
    check(str_ends_with(rtrim($r['location'], '/'), '/admin/dashboard'), 'Admin login redirects to /admin/dashboard', $r['location']);

    $dash = http('GET', $base . 'admin/dashboard');
    check($dash['status'] === 200 && stripos($dash['body'], 'Platform fees collected') !== false, 'Admin dashboard renders');
    check(str_contains($dash['body'], 'Balanced'), 'Platform fees reconcile with settled requests');
    $walletsBalanced = str_contains($dash['body'], 'all balances match their ledgers');
    if (! $walletsBalanced && str_contains($dash['body'], 'Admin overview')) {
        $ledgerCheck = http('GET', $base . 'admin/users?check=ledger');
        $walletsBalanced = $ledgerCheck['status'] === 200 && str_contains($ledgerCheck['body'], 'No accounts match');
    }
    check($walletsBalanced, 'Every wallet matches its ledger');
}

@unlink($jar);

echo $failures === 0 ? "ALL CHECKS PASSED\n" : "{$failures} CHECK(S) FAILED\n";
exit($failures === 0 ? 0 : 1);
