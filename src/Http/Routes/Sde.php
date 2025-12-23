<?php

/*
 * Route override for SDE version check.
 * This overrides the default Fuzzwork check with CCP official API.
 */

Route::get('/check/sde')
    ->name('seatcore::check.sde')
    ->uses('SdeController@getApprovedSDE');
