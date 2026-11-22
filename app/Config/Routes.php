<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 *
 * Access rules:
 *  - Public: landing/login/register/about/contact/health.
 *  - `auth`  group: requires a signed-in user (AuthFilter).
 *  - `admin` group: requires role=admin, re-checked against the DB (AdminFilter).
 *  - Every POST is CSRF-checked globally (Config\Filters::$globals).
 *  - State changes use POST only.
 */

// ------------------------------------------------------------------ public
$routes->get('/', 'Home::index');
$routes->get('/login', 'Auth::login');
$routes->post('/login/process', 'Auth::loginProcess');
$routes->get('/register', 'Auth::register');
$routes->post('/register/process', 'Auth::process');
$routes->post('/logout', 'Auth::logout');

$routes->get('/about', 'NavBar::about');
$routes->get('/contact', 'NavBar::contact');
$routes->get('/health', 'Health::index');

// ------------------------------------------------------------- signed in
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Dashboard::index');

    $routes->get('post-request', 'Request::create');
    $routes->post('request/process', 'Request::process');
    $routes->post('request/accept/(:num)', 'Request::accept/$1');
    $routes->post('request/status/(:num)', 'Request::updateAcceptedStatus/$1');
    $routes->post('request/pay-runner/(:num)', 'Request::payRunner/$1');
    $routes->post('request/cancel/(:num)', 'Request::cancel/$1');

    $routes->get('browse-requests', 'Request::browse');
    $routes->get('request-details/(:num)', 'Request::details/$1');
    $routes->get('my-requests', 'Request::myRequests');
    $routes->get('accepted-requests', 'Request::acceptedRequests');

    $routes->get('messages', 'Message::index');
    $routes->post('messages/send', 'Message::send');
    $routes->get('messages/attachment/(:num)', 'Message::attachment/$1');

    $routes->get('wallet', 'Wallet::index');
    $routes->post('wallet/top-up', 'Wallet::topUp');

    $routes->get('profile', 'Profile::index');
    $routes->post('profile/update', 'Profile::updateProfile');
    $routes->post('profile/rate-website', 'Profile::rateWebsite');
    $routes->post('profile/refer-friend', 'Profile::referFriend');

    $routes->get('media/avatar/(:segment)', 'Media::avatar/$1');

    $routes->get('users/search', 'UserSearch::index');
    $routes->get('users/(:num)', 'UserSearch::view/$1');
    $routes->post('users/(:num)/rate', 'UserSearch::rate/$1');

    $routes->get('notifications', 'RequestNotification::index');
    $routes->get('notifications/unread-count', 'RequestNotification::unreadCount');
    $routes->get('notifications/open/(:num)', 'RequestNotification::open/$1');
    $routes->post('notifications/mark-all-read', 'RequestNotification::markAllRead');
});

// ----------------------------------------------------------------- admin
$routes->group('admin', ['filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'AdminController::dashboard');
    $routes->post('request/cancel/(:num)', 'AdminController::cancelRequest/$1');
});
