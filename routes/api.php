<?php

use App\Http\Controllers\Agen\HargaAgenController;
use App\Http\Controllers\Agen\LoginAgenController;
use App\Http\Controllers\Agen\OrderAgenController;
use App\Http\Controllers\Agen\PesananMasukAgenController;
use App\Http\Controllers\BarangAgenController;
use App\Http\Controllers\BarangDistributorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PabrikLoginAPIController;
use App\Http\Controllers\BarangPabrikController;
use App\Http\Controllers\Distributor\HargaDistributorController;
use App\Http\Controllers\Distributor\LoginDistributorController;
use App\Http\Controllers\Distributor\OrderDistributorController;
use App\Http\Controllers\Distributor\PesananMasukDistributorController;
use App\Http\Controllers\Pabrik\AkunDistributorController;
use App\Http\Controllers\Pabrik\PesananMasukPabrikController;
use App\Http\Controllers\Pabrik\RestockPabrikController;
use App\Http\Controllers\Sales\DaftarTokoController;
use App\Http\Controllers\Sales\KunjunganTokoController;
use App\Http\Controllers\Sales\LoginSalesController;
use App\Http\Controllers\Sales\OrderSaleController;
use App\Models\OrderDistributor;
use App\Models\OrderSale;

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

    Route::get('/pabrik/riwayatPabrik', [RestockPabrikController::class, 'riwayatRestockAPI']);
    Route::get('pabrik/nota-pabrik/{idNota}/pdf', [RestockPabrikController::class, 'notaPabrikPdf']);
});


Route::post('/distributor/login', [LoginDistributorController::class, 'loginDistributorAPI']);
Route::middleware(['auth:sanctum', 'role:distributor'])->group(function () {
    Route::get('/distributor/dashboard', [BarangDistributorController::class, 'stockbarangAPI']);
    Route::get('/distributor/pesananMasuk', [PesananMasukDistributorController::class, 'pesananMasukDistributorAPI']);
    Route::get('/distributor/pesananMasuk/{idPesanan}', [PesananMasukDistributorController::class, 'detailPesananMasukDistributorAPI']);
    Route::post('/distributor/pesananMasuk/{idPesanan}', [PesananMasukDistributorController::class, 'updateStatusPesananMasukAPI']);


    Route::post("/distributor/order", [OrderDistributorController::class, 'storeOrderAPI']);
    Route::get("/distributor/pilihBarang", [BarangPabrikController::class, 'listBarangPabrikDistributorAPI']);
    Route::get("/distributor/riwayatOrder", [OrderDistributorController::class, 'listRiwayatOrderAPI']);
    Route::get('distributor/nota-distributor/{idNota}/pdf', [OrderDistributorController::class, 'notaDistributorPdf']);

    Route::get('/distributor/pengaturan-harga', [HargaDistributorController::class, "pengaturanHargaAPI"]);
    Route::put('/distributor/pengaturan-harga/{id}', [HargaDistributorController::class, "updateHargaAPI"]);
    Route::get('/distributor/barang-baru', [HargaDistributorController::class, "getNewBarangAPI"]);
    Route::post('/distributor/barang-baru', [HargaDistributorController::class, "addNewBarangAPI"]);
});

Route::post('/agen/login', [LoginAgenController::class, 'loginAgenAPI']);
Route::middleware(['auth:sanctum', 'role:agen'])->group(function () {
    Route::get('/agen/dashboard', [BarangAgenController::class, 'stockbarangAPI']);

    Route::get('/agen/pesananMasuk', [PesananMasukAgenController::class, 'pesananMasukAgenAPI']);
    Route::get('/agen/pesananMasuk/{idPesanan}', [PesananMasukAgenController::class, 'detailPesananMasukAgenAPI']);
    Route::post('/agen/pesananMasuk/{idPesanan}', [PesananMasukAgenController::class, 'updateStatusPesananAPI']);
    Route::get("/agen/pilihBarang", [BarangDistributorController::class, 'listBarangDistributorAgenAPI']);

    Route::post('/agen/order', [OrderAgenController::class, 'storeOrder']);
    Route::get('/agen/riwayatOrder', [OrderAgenController::class, 'riwayatOrderAPI']);
    Route::get('agen/nota-agen/{idNota}/pdf', [OrderAgenController::class, 'notaAgenPdf']);

    Route::get('/agen/pengaturan-harga', [HargaAgenController::class, 'pengaturanHargaAPI']);
    Route::put('/agen/pengaturan-harga/{id}', [HargaAgenController::class, "updateHargaAPI"]);
    Route::get('/agen/barang-baru', [HargaAgenController::class, "getNewBarangAPI"]);
    Route::post('/agen/barang-baru', [HargaAgenController::class, "addNewBarangAPI"]);
});

Route::post('/sales/login', [LoginSalesController::class, 'loginSalesAPI']);
Route::middleware(['auth:sanctum', 'role:sales'])->group(function () {
    Route::get('/sales/dashboard', [OrderSaleController::class, 'dashboardSalesAPI']);
    Route::get('/sales/listBarangOrder', [OrderSaleController::class, 'getListBarangOrderAPI']);

    Route::post('/sales/order', [OrderSaleController::class, 'storeOrderAPI']);

    Route::get('/sales/riwayatOrder', [OrderSaleController::class, 'riwayatOrderAPI']);

    Route::get('/sales/listToko', [DaftarTokoController::class, 'daftarTokoAPI']);
    Route::post('/sales/toko', [DaftarTokoController::class, 'storeTokoAPI']);
    Route::put('/sales/toko/{id_daftar_toko}', [DaftarTokoController::class, 'updateTokoAPI']);
    Route::delete('/sales/toko/{id_daftar_toko}', [DaftarTokoController::class, 'deleteTokoAPI']);

    Route::get('/sales/kunjungan/{id_toko}', [KunjunganTokoController::class, 'getKunjunganTokoAPI']);
    Route::post('/sales/kunjungan/{id_toko}', [KunjunganTokoController::class, 'insertKunjunganTokoAPI']);
    Route::post('/sales/kunjungan/update/{id_kunjungan_toko}', [KunjunganTokoController::class, 'updateKunjunganTokoAPI']);
    Route::get('sales/nota-sales/{idNota}/pdf', [OrderSaleController::class, 'notaSalesPdf']);
});
