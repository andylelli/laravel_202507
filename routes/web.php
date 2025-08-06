<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\GetWebPermissionsController;
use App\Http\Controllers\GetAdminPermissionsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|  return view('welcome');
*/
Route::get('/app/{eventname}/{eventtoken}/{id}/{bgcolor}/{type}',  [GetWebPermissionsController::class, 'getWebPermissionsPage']);
Route::get('/app/admin',  [GetAdminPermissionsController::class, 'getAdminPermissionsPage']);
