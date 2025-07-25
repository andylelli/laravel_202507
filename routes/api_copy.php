<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Middleware\Cors;
use App\Http\Middleware\ValidateToken;

use App\Http\Controllers\GetUpdateController;
use App\Http\Controllers\PostDeleteController;
use App\Http\Controllers\PostInsertController;
use App\Http\Controllers\PostUpdateController;
use App\Http\Controllers\DeleteController;
use App\Http\Controllers\PostLoginController;
use App\Http\Controllers\PostRegisterController;
use App\Http\Controllers\PostImageController;
use App\Http\Controllers\GetScanController;
use App\Http\Controllers\GetUserScanController;
use App\Http\Controllers\GetEventBackup;
use App\Http\Controllers\PostEventBackup;
use App\Http\Controllers\GetManifestController;
use App\Http\Controllers\GetEmailController;
use App\Http\Controllers\GetLogController;
use App\Http\Controllers\PostInstallController;
use App\Http\Controllers\PostTileController;
use App\Http\Controllers\GetTileController;
use App\Http\Controllers\MapStyliseController;
use App\Http\Controllers\EventCloneController; // <--- add this for EventCloner

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Clone Demo
Route::post('/clone-demo-event', [EventCloneController::class, 'cloneDemoEvent']);

// Send tiles to client
Route::get('/get/tiles/pindrop/{pindropid}', [GetTileController::class, 'getTilesForPindrop']);

// Save tiles
Route::post('/post/insert/tiles', [PostTileController::class, 'downloadTiles']);

// Stylise tiles
Route::post('/stylise-map', [MapStyliseController::class, 'handleStored']);

// Login
Route::post('/post/login',  [PostLoginController::class, 'postLogin']);

// Register
Route::post('/post/register', [PostRegisterController::class, 'postRegister']);

// Email
Route::get('/get/email', [GetEmailController::class, 'sendEmail']);

// Get updates
Route::get('/get/events',  [GetUpdateController::class, 'getEvents']);
Route::get('/get/liveevents',  [GetUpdateController::class, 'getLiveEvents']);

// Get manifest
Route::get('/get/manifest/{eventid}',  [GetManifestController::class, 'getManifest']);

// Install
Route::post('/install',  [PostInstallController::class, 'postInstall']);

// Protected routes with ValidateToken middleware
Route::middleware([ValidateToken::class])->group(function () {

    // Get updates
    Route::get('/get/update/user/{userid}/{unixtime}',  [GetUpdateController::class, 'getUpdatesForUser']);
    Route::get('/get/update/event/{eventid}/{unixtime}',  [GetUpdateController::class, 'getUpdatesForEvent']);
    Route::get('/get/update/{table}/{eventid}/{unixtime}',  [GetUpdateController::class, 'getUpdatesForGeneric']);
    Route::get('/get/qrcode/guest/{guestid}',  [GetUpdateController::class, 'getQRCodeGuest']);
    Route::get('/get/qrcode/event/{eventid}',  [GetUpdateController::class, 'getQRCodeEvent']);

    Route::get('/get/update/log/{eventid}',  [GetLogController::class, 'getLog']);

    // Post deletes
    Route::post('/post/delete/{table}',  [PostDeleteController::class, 'postDelete']);

    // Post insert
    Route::post('/post/insert/order',  [PostInsertController::class, 'postInsertOrder']);
    Route::post('/post/insert/event',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/project',  [PostInsertController::class, 'PostInsert']);
    Route::post('/post/insert/poll',  [PostInsertController::class, 'PostInsert']);
    Route::post('/post/insert/pollitem',  [PostInsertController::class, 'PostInsert']);
    Route::post('/post/insert/scoreboard',  [PostInsertController::class, 'PostInsert']);
    Route::post('/post/insert/scoreboardscore',  [PostInsertController::class, 'PostInsert']);
    Route::post('/post/insert/pindrop',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/directory',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/directoryentry',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/shop',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/shopitem',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/hunt',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/huntitem',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/news',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/newsitem',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/schedule',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/myschedule',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/guest',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/qrcode',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/lookup',  [PostInsertController::class, 'postInsert']);
    Route::post('/post/insert/bulk/directoryentry',  [PostInsertController::class, 'postInsertBulk']);

    // Post update
    Route::post('/post/update/event',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/project',  [PostUpdateController::class, 'PostUpdate']);
    Route::post('/post/update/poll',  [PostUpdateController::class, 'PostUpdate']);
    Route::post('/post/update/pollitem',  [PostUpdateController::class, 'PostUpdate']);
    Route::post('/post/update/pollscore',  [PostUpdateController::class, 'PostUpdate']);
    Route::post('/post/update/scoreboard',  [PostUpdateController::class, 'PostUpdate']);
    Route::post('/post/update/scoreboardscore',  [PostUpdateController::class, 'PostUpdate']);
    Route::post('/post/update/pindrop',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/directory',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/directoryentry',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/shop',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/shopitem',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/hunt',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/huntitem',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/news',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/newsitem',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/schedule',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/myschedule',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/guest',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/qrcode',  [PostUpdateController::class, 'postUpdate']);
    Route::post('/post/update/lookup',  [PostUpdateController::class, 'postUpdateLookup']);
    Route::post('/post/update/image',  [PostImageController::class, 'postImage']);

    Route::post('/post/remove/image',  [PostImageController::class, 'postRemoveImage']);

    // Get Event Backup
    Route::get('/get/event/insert/backup/{eventid}/{file}',  [GetEventBackup::class, 'getInsertEventBackup']);

    // Post Event Backup
    Route::post('/post/event/restore/backup',  [PostEventBackup::class, 'postRestoreEventBackup']);

    // Delete
    Route::delete('/delete/{table}/{id}',  [DeleteController::class, 'deleteItem']);
    Route::delete('/delete-all/{table}/{parenttable}/{id}',  [DeleteController::class, 'deleteAll']);

    // Misc
    Route::get('/get/scan/{type}/{eventid}/{id}/{value}/{email}/{uniqueid}/{token}', [GetScanController::class, 'getScan']);
    Route::get('/get/userscan/{table}/{tableid}/{itemid}/{token}', [GetUserScanController::class, 'getUserScan']);

    Route::get('/get/pollsubmit/{id}/{pollid}/{eventid}/{token}/{guestid}', [GetUpdateController::class, 'getPollSubmit']);
    Route::get('/get/pollscore/{pollid}/{eventid}/{token}/{guestid}', [GetUpdateController::class, 'getPollScore']);

});
