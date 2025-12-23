<?php

/*
 * HTTP routes for local-sde plugin.
 * These routes override core SeAT functionality to use CCP API.
 */

Route::group([
    'namespace' => 'LocalSde\SeatLocalSde\Http\Controllers',
    'middleware' => ['web', 'auth', 'locale'],
    'prefix' => 'configuration/settings',
], function () {
    include __DIR__ . '/Routes/Sde.php';
});
