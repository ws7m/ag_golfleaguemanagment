<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// One login for players and admins (each person's own login and password).
$routes->get('login', 'Auth::loginForm');
$routes->post('login', 'Auth::login');
$routes->get('admin/login', 'Auth::loginForm');
$routes->get('logout', 'Auth::logout');

$routes->group('player', ['filter' => 'playerauth', 'namespace' => 'App\Controllers\Player'], static function ($routes) {
    $routes->get('/', 'Home::index');
});

// Admin screens.

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
