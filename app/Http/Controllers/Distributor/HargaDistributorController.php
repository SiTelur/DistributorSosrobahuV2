<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Models\MasterBarang;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\BarangDistributor;

class HargaDistributorController extends Controller
{
    public function index()
    {
        $namaRokokList = [];
        $id_user_distributor = session('id_user_distributor');

        $rokokDistributors = BarangDistributor::where('id_user_distributor', $id_user_distributor)->get();
        // ->orderBy('id_master_barang', 'desc')
        // ->paginate(10);

        // Ambil semua ID master_barang yang sudah ada di BarangDistributor
        $existingProductIds = BarangDistributor::where('id_user_distributor', $id_user_distributor)
            ->pluck('id_master_barang')
            ->toArray();

        // Ambil produk yang belum ada di BarangDistributor
        $newProductsCount = MasterBarang::whereNotIn('id_master_barang', $existingProductIds)->count();


        foreach ($rokokDistributors as $barangDistributor) {
            $namaProduk = $barangDistributor->id_master_barang;
            $orderValue = DB::table('master_barang')->where('id_master_barang', $namaProduk)->first();
            if ($orderValue) {
                $namaRokokList[] = $orderValue->nama_rokok;
            } else {
                $namaRokokList[] = null;
            }
        }

        return view('distributor.pengaturanHarga', compact('rokokDistributors', 'namaRokokList', 'newProductsCount'));
    }



    public function showAddProduct()
    {
        $id_user_distributor = session('id_user_distributor');
        // Ambil semua ID master_barang yang sudah ada di BarangDistributor
        $existingProductIds = BarangDistributor::where('id_user_distributor', $id_user_distributor)
            ->pluck('id_master_barang')
            ->toArray();

        // Ambil produk yang belum ada di BarangDistributor
        $newDistributorProducts = MasterBarang::whereNotIn('id_master_barang', $existingProductIds)->get();

        return view('distributor.produkBaru', compact('newDistributorProducts'));
    }


    public function storeSelectedProducts(Request $request)
    {
        $id_user_distributor = session('id_user_distributor');

        foreach ($request->products as $productId) {
            $product = MasterBarang::find($productId);

            if ($product) {
                BarangDistributor::create([
                    'id_master_barang' => $productId,
                    'id_user_distributor' => $id_user_distributor,
                    'harga_distributor' => $product->harga_karton_pabrik, // Gunakan harga yang sudah ada
                    'stok_karton' => 10,
                ]);
            }
        }

        return redirect()->route('pengaturanHargaDistributor')->with('success', 'Produk berhasil ditambahkan');
    }

    public function update(Request $request, $id)
    {
        // Validasi input
        $request->validate([
            // 'harga_distributor' => 'required|string|max:255'
        ]);


        $setting = BarangDistributor::find($id);


        if (!$setting) {
            return redirect()->route('pengaturanHargaDistributor')->with('error', 'Akun sales tidak ditemukan.');
        }


        $setting->harga_distributor = $request->harga_distributor;

        // dd($setting);
        // Menyimpan perubahan
        $setting->save();
        // Redirect dengan pesan sukses
        return redirect()->route('pengaturanHargaDistributor')->with('success', 'Akun sales berhasil diperbarui.');
    }

    public function pengaturanHargaAPI(Request $request)
    {
        // 1. Ambil ID distributor dari token
        $id_user_distributor = $request->user()->currentAccessToken()->user_id;

        // 2. Eager-load masterBarang untuk menghindari N+1
        $items = BarangDistributor::with('masterBarang:id_master_barang,nama_rokok,harga_karton_pabrik,gambar')
            ->where('id_user_distributor', $id_user_distributor)
            ->get();

        // 3. Hitung produk baru yang belum ada di list distributor
        $existingIds      = $items->pluck('id_master_barang')->all();
        $newProductsCount = MasterBarang::whereNotIn('id_master_barang', $existingIds)->count();

        // 4. Bentuk payload JSON
        $data = $items->map(function ($d) {
            return [
                'id'               => $d->id_barang_distributor,
                'id_master_barang' => $d->id_master_barang,
                'harga'            => $d->harga_distributor,
                'gambar'      => optional($d->masterBarang)->gambar,
                'harga_pabrik'      => optional($d->masterBarang)->harga_karton_pabrik,
                'nama_rokok'       => optional($d->masterBarang)->nama_rokok,
            ];
        });

        return response()->json([
            'distributors'       => $data,
            'new_products_count' => $newProductsCount,
        ], 200);
    }

    public function updateHargaAPI(Request $request, $id)
    {
        $validated = $request->validate([
            'prices' => 'required|integer|min:0',
        ]);

        $id_user_distributor = $request->user()->currentAccessToken()->user_id;
        $updated   = [];


        $setting = BarangDistributor::where('id_barang_distributor', $id)
            ->where('id_user_distributor', $id_user_distributor)
            ->first();

        if (! $setting) {
            return response()->json([
                'message' => 'Data tidak ditemukan atau tidak punya akses.'
            ], 404);
        }

        $setting->harga_distributor = $validated['prices'];
        $setting->save();

        // 4) Kembalikan JSON
        return response()->json([
            'message' => 'Harga berhasil diperbarui.',
            'data'    => [
                'id'                => $setting->id_barang_distributor,
                'harga_distributor' => $setting->harga_distributor,
            ],
        ], 200);
    }

    public function getNewBarangAPI(Request $request)
    {
        // ambil id distributor dari session (atau ganti dengan Auth::id() jika menggunakan Auth)
        $id_user_distributor = $request->user()->currentAccessToken()->user_id;

        // ambil semua id_master_barang yang sudah dipakai distributor ini
        $existingIds = BarangDistributor::where('id_user_distributor', $id_user_distributor)
            ->pluck('id_master_barang');

        // ambil produk yang belum ada, hanya kolom yang diperlukan
        $newProducts = MasterBarang::whereNotIn('id_master_barang', $existingIds)
            ->select('id_master_barang', 'nama_rokok', 'harga_karton_pabrik', 'gambar') // contoh kolom
            ->get();

        // kembalikan JSON
        return response()->json([
            'success' => true,
            'count'   => $newProducts->count(),
            'data'    => $newProducts
        ], 200);
    }

    public function addNewBarangAPI(Request $request)
    {
        // 1. Validasi input
        $validated = $request->validate([
            'products'   => 'required|array',
            'products.*' => 'integer|exists:master_barang,id_master_barang',
        ]);

        $distributorId  = $request->user()->currentAccessToken()->user_id;


        // 2. (Opsional) hindari duplikat: cari produk yang sudah terdaftar
        $existing = BarangDistributor::where('id_user_distributor', $distributorId)
            ->whereIn('id_master_barang', $validated['products'])
            ->pluck('id_master_barang')
            ->all();

        $toInsertIds = array_diff($validated['products'], $existing);

        if (empty($toInsertIds)) {
            return response()->json([
                'message'     => 'Tidak ada produk baru untuk ditambahkan.',
                'added_count' => 0,
                'data' => []
            ], 200);
        }

        // 3. Bulk-fetch MasterBarang
        $masterItems = MasterBarang::whereIn('id_master_barang', $toInsertIds)
            ->get(['id_master_barang', 'harga_karton_pabrik'])
            ->keyBy('id_master_barang');

        $now     = now();
        $records = [];

        foreach ($toInsertIds as $pid) {
            $records[] = [
                'id_master_barang'    => $pid,
                'id_user_distributor' => $distributorId,
                'harga_distributor'   => $masterItems[$pid]->harga_karton_pabrik,
                'stok_karton'         => 10,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }

        // 4. Bulk insert
        BarangDistributor::insert($records);

        // 5. Response JSON
        return response()->json([
            'message'     => 'Produk berhasil ditambahkan.',
            'added_count' => count($records),
            'data'        => $records,
        ], 201);
    }
}
