<?php

use App\Http\Controllers\LoginController;
use Illuminate\Http\Request;

use App\Http\Controllers\ClientsController;
use App\Http\Controllers\DatabaseCleanerController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\ReductionController;
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

Route::group(['namespace' => 'App\Api\v1\Controllers'], function () {
    Route::group(['middleware' => 'auth:api'], function () {
        Route::get('users', ['uses' => 'UserController@index']);
    });
});


Route::get('/clients/count', [ClientsController::class, 'countAllClientsJson']);
Route::get('/clients/all', [ClientsController::class, 'listAllClientsJson']);
Route::get('/clients/payments', [PaymentsController::class, 'getTotalPaymentsByClient']);
Route::get('/clients/payments/{clientId}', [PaymentsController::class, 'getPaymentsByClient']);

Route::get('/payments', [PaymentsController::class, 'getTotalPaymentsJson']);
Route::get('/payments/byinvoice', [PaymentsController::class, 'getAllPaymentsByInvocieJson']);
Route::get('/payments/{invoiceId}', [PaymentsController::class, 'getByInvoiceJson']);
Route::get('/payments/id/{id}', [PaymentsController::class, 'getByIdJson']);

Route::put('/payments/update', [PaymentsController::class, 'updatePayment']);
Route::put('/payments/delete', [PaymentsController::class, 'deletePayment']);

Route::get('/invoices/count-by-status', [InvoicesController::class, 'countInvoiceByStatus']);

Route::post('/reductions/create', [ReductionController::class, 'store']);
Route::put('/reductions/update', [ReductionController::class, 'update']);
Route::get('/reductions/get', [ReductionController::class, 'getByReductionJson']);

Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');