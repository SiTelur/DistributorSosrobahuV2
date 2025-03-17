<?php

use App\Http\Controllers\Agen\LoginAgenController;
use App\Http\Controllers\Agen\OrderAgenController;
use App\Http\Controllers\Agen\PesananMasukAgenController;
use App\Http\Controllers\BarangAgenController;
use App\Http\Controllers\BarangDistributorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PabrikLoginAPIController;
use App\Http\Controllers\BarangPabrikController;
use App\Http\Controllers\Distributor\LoginDistributorController;
use App\Http\Controllers\Distributor\OrderDistributorController;
use App\Http\Controllers\Distributor\PesananMasukDistributorController;
use App\Http\Controllers\Pabrik\AkunDistributorController;
use App\Http\Controllers\Pabrik\PesananMasukPabrikController;
use App\Http\Controllers\Pabrik\RestockPabrikController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/pabrik/login', [PabrikLoginAPIController::class, 'loginPabrik']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum', 'role:pabrik'])->group(function () {

    Route::get('/pabrik/dashboard', [BarangPabrikController::class, 'stockbarangAPI']);
    Route::get('/pabrik/distributor', [AkunDistributorController::class, 'showDistributorAPI']);

    Route::get('/pabrik/pesananMasuk', [PesananMasukPabrikController::class, 'pesananMasukPabrikAPI']);

    Route::get('/pabrik/pesananMasuk/{idPesanan}', [PesananMasukPabrikController::class, 'detailPesanMasukAPI']);
    Route::post('/pabrik/pesananMasuk/{idPesanan}', [PesananMasukPabrikController::class, 'updateStatusAPI']);

    Route::get('/pabrik/restock', [BarangPabrikController::class, 'restockBarangAPI']);
    Route::post('/pabrik/restock', [RestockPabrikController::class, 'storeAPI']);
});


Route::post('/distributor/login', [LoginDistributorController::class, 'loginDistributorAPI']);
Route::middleware(['auth:sanctum', 'role:distributor'])->group(function () {
    Route::get('/distributor/dashboard', [BarangDistributorController::class, 'stockbarangAPI']);
    Route::get('/distributor/pesananMasuk', [PesananMasukDistributorController::class, 'pesananMasukDistributorAPI']);
    Route::get('/distributor/pesananMasuk/{idPesanan}', [PesananMasukDistributorController::class, 'detailPesananMasukDistributorAPI']);
    Route::post('/distributor/pesananMasuk/{idPesanan}', [PesananMasukDistributorController::class, 'updateStatusPesananMasukAPI']);


    Route::post("/distributor/order", [OrderDistributorController::class, 'storeOrderAPI']);
    Route::get("/distributor/riwayatOrder", [OrderDistributorController::class, 'listRiwayatOrderAPI']);
});

Route::post('/agen/login', [LoginAgenController::class, 'loginAgenAPI']);
Route::middleware(['auth:sanctum', 'role:agen'])->group(function () {
    Route::get('/agen/dashboard', [BarangAgenController::class, 'stockbarangAPI']);

    Route::get('/agen/pesananMasuk', [PesananMasukAgenController::class, 'pesananMasukAgenAPI']);
    Route::get('/agen/pesananMasuk/{idPesanan}', [PesananMasukAgenController::class, 'detailPesananMasukAgenAPI']);
    Route::post('/agen/pesananMasuk/{idPesanan}', [PesananMasukAgenController::class, 'updateStatusPesananAPI']);

    Route::post('/agen/order', [OrderAgenController::class, 'storeOrder']);
    Route::get('/agen/riwayatOrder', [OrderAgenController::class, 'riwayatOrderAPI']);
});
