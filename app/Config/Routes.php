<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// Player login: shared login ID and password, then member number.
$routes->get('login', 'Auth::playerLoginForm');
$routes->post('login', 'Auth::playerLogin');
$routes->post('login/member', 'Auth::playerMember');
$routes->get('login/switch', 'Auth::switchMember');
$routes->get('logout', 'Auth::logout');

$routes->group('player', ['filter' => 'playerauth', 'namespace' => 'App\Controllers\Player'], static function ($routes) {
    $routes->get('/', 'Home::index');
});

// Admin login (email and password) and admin screens.
$routes->get('admin/login', 'Auth::adminLoginForm');
$routes->post('admin/login', 'Auth::adminLogin');

$routes->group('admin', ['filter' => 'adminauth', 'namespace' => 'App\Controllers\Admin'], static function ($routes) {
    $routes->get('/', 'Dashboard::index');

    // List, add, edit and delete screens. Path => controller.
    $screens = [
        'leagues'   => 'Leagues',
        'courses'   => 'Courses',
        'tee-times' => 'TeeTimes',
        'players'   => 'Players',
        'groups'    => 'Groups',
        'games'     => 'Games',
        'rounds'    => 'Rounds',
        'scores'    => 'Scores',
    ];
    foreach ($screens as $path => $controller) {
        $routes->get($path, "{$controller}::index");
        $routes->get("{$path}/new", "{$controller}::new");
        $routes->post($path, "{$controller}::create");
        $routes->get("{$path}/(:num)/edit", "{$controller}::edit/$1");
        $routes->post("{$path}/(:num)", "{$controller}::update/$1");
        $routes->post("{$path}/(:num)/delete", "{$controller}::delete/$1");
    }

    $routes->get('leagues/(:num)/copy', 'Leagues::copy/$1');
    $routes->post('rounds/generate', 'Rounds::generate');
    $routes->post('rounds/delete-all', 'Rounds::deleteAll');
    $routes->get('courses/(:num)/holes', 'Holes::edit/$1');
    $routes->post('courses/(:num)/holes', 'Holes::update/$1');
    $routes->post('tee-times/shift', 'TeeTimes::shift');
    $routes->get('settings', 'Settings::edit');
    $routes->post('settings', 'Settings::update');
});
