<?php
// Isolated browser checks: real PHP sessions, disposable test DB, no providers.
foreach ([
    'CI_ENVIRONMENT' => 'development',
    'app.baseURL' => 'http://127.0.0.1:8087/',
    'app.forceGlobalSecureRequests' => 'false',
    'cookie.secure' => 'false',
    'session.cookieName' => 'pb_review_session',
    'database.default.database' => 'pasa_buy_test',
    'database.default.hostname' => '127.0.0.1',
    'database.default.username' => 'root',
    'database.default.password' => '',
    'talkjs.appId' => '', 'talkjs.secretKey' => '',
    'xendit.secretKey' => '', 'xendit.callbackToken' => '',
    'paymongo.secretKey' => '', 'paymongo.webhookSecret' => '',
] as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv($key . '=' . $value);
}
return require dirname(__DIR__, 2) . '/vendor/codeigniter4/framework/system/rewrite.php';
