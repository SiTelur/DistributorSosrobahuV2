<?php

namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\BarangAgen;
use App\Models\MasterBarang;

class HargaAgenController extends Controller
{
    public function index()
    {
        $namaRokokList = [];

        // Dapatkan id_user_agen dari sesi
        $id_user_agen = session('id_user_agen');

        // Query BarangAgen untuk agen tertentu dan urutkan berdasarkan id_master_barang (desc)
        $rokokAgens = BarangAgen::where('id_user_agen', $id_user_agen)
            ->orderBy('id_master_barang', 'desc')
            ->paginate(10);

        // Ambil semua id_master_barang untuk id_user_agen tertentu
        $existingProductIds = BarangAgen::where('id_user_agen', $id_user_agen)
            ->pluck('id_master_barang')
            ->toArray();

        // Hitung jumlah produk baru yang tidak ada di BarangAgen
        $newProductsCount = MasterBarang::whereNotIn('id_master_barang', $existingProductIds)->count();

        // Dapatkan nama rokok dari tabel master_barang untuk setiap id_master_barang
        foreach ($rokokAgens as $barangAgen) {
            $orderValue = MasterBarang::where('id_master_barang', $barangAgen->id_master_barang)->first();
            if ($orderValue) {
                $namaRokokList[] = $orderValue->nama_rokok;
            } else {
                $namaRokokList[] = null; // Jika tidak ditemukan, tambahkan nilai null
            }
        }
        return view('agen.pengaturanHarga', compact('rokokAgens', 'namaRokokList', 'newProductsCount'));
    }


    public function showAddProduct()
    {
        // Ambil id_user_agen dari sesi
        $id_user_agen = session('id_user_agen');

        // Ambil id_master_barang yang sudah dimiliki agen tertentu
        $existingProductIds = BarangAgen::where('id_user_agen', $id_user_agen)
            ->pluck('id_master_barang')
            ->toArray();

        // Ambil produk baru yang belum dimiliki agen tertentu
        $newAgenProducts = MasterBarang::whereNotIn('id_master_barang', $existingProductIds)->get();

        // Tampilkan data ke view
        return view('agen.produkBaru', compact('newAgenProducts'));
    }

    public function storeSelectedProducts(Request $request)
    {
        $id_user_agen = session('id_user_agen');

        foreach ($request->products as $productId) {
            $product = MasterBarang::find($productId);

            if ($product) {
                BarangAgen::create([
                    'id_master_barang' => $productId,
                    'id_user_agen' => $id_user_agen,
                    'harga_agen' => $product->harga_karton_pabrik, // Gunakan harga yang sudah ada
                    'stok_karton' => 10,
                ]);
            }
        }

        return redirect()->route('pengaturanHarga')->with('success', 'Produk berhasil ditambahkan');
    }




    public function update(Request $request, $id)
    {
        // Validasi input
        $request->validate([
            // 'harga_agen' => 'required|string|max:255'
        ]);

        // Mengambil data sales berdasarkan ID
        $setting = BarangAgen::find($id);

        // Jika data sales tidak ditemukan
        if (!$setting) {
            return redirect()->route('pengaturanHarga')->with('error', 'Akun sales tidak ditemukan.');
        }

        // Mengupdate data sales
        $setting->harga_agen = $request->harga_agen;

        // dd($setting);
        // Menyimpan perubahan
        $setting->save();
        // Redirect dengan pesan sukses
        return redirect()->route('pengaturanHarga')->with('success', 'Akun sales berhasil diperbarui.');
    }

    public function pengaturanHargaAPI(Request $request)
    {
        $idUserAgen = $request->user()->currentAccessToken()->user_id;


        // Ambil semua ID master_barang yang sudah dimiliki agen
        $existingIds = BarangAgen::where('id_user_agen', $idUserAgen)
            ->pluck('id_master_barang');

        $items = BarangAgen::with('masterBarang:id_master_barang,nama_rokok,harga_karton_pabrik,gambar')
            ->where('id_user_agen', $idUserAgen)
            ->get();

        // 3. Hitung produk baru yang belum ada di list distributor
        $existingIds      = $items->pluck('id_master_barang')->all();
        $newProductsCount = MasterBarang::whereNotIn('id_master_barang', $existingIds)->count();

        // 4. Bentuk payload JSON
        $data = $items->map(function ($d) {
            return [
                'id'               => $d->id_barang_agen,
                'id_master_barang' => $d->id_master_barang,
                'harga'            => $d->harga_agen,
                'gambar'      => optional($d->masterBarang)->gambar,
                'harga_pabrik'      => optional($d->masterBarang)->harga_karton_pabrik,
                'nama_rokok'       => optional($d->masterBarang)->nama_rokok,
            ];
        });


        return response()->json([
            'rokokAgens'       => $data,
            'newProductsCount' => $newProductsCount,
        ]);
    }

    public function getNewBarangAPI(Request $request)
    {
        $idUserAgen = $request->user()->currentAccessToken()->user_id;

        // ambil semua id_master_barang yang sudah dipakai distributor ini
        $existingIds = BarangAgen::where('id_user_agen', $idUserAgen)
            ->pluck('id_master_barang');

        // ambil produk yang belum ada, hanya kolom yang diperlukan
        $newProducts = MasterBarang::whereNotIn('id_master_barang', $existingIds)
            ->select('id_master_barang', 'nama_rokok', 'harga_karton_pabrik',) // contoh kolom
            ->get();

        // kembalikan JSON
        return response()->json([
            'success' => true,
            'count'   => $newProducts->count(),
            'data'    => $newProducts
        ], 200);
    }

    public function updateHargaAPI(Request $request, $id)
    {
        $validated = $request->validate([
            'prices' => 'required|integer|min:0',
        ]);

        $id_user_agen = $request->user()->currentAccessToken()->user_id;
        $updated   = [];


        $setting = BarangAgen::where('id_barang_agen', $id)
            ->where('id_user_agen', $id_user_agen)
            ->first();

        if (! $setting) {
            return response()->json([
                'message' => 'Data tidak ditemukan atau tidak punya akses.'
            ], 404);
        }

        $setting->harga_agen = $validated['prices'];
        $setting->save();

        // 4) Kembalikan JSON
        return response()->json([
            'message' => 'Harga berhasil diperbarui.',
            'data'    => [
                'id'                => $setting->id_barang_agen,
                'harga_agen' => $setting->harga_agen,
            ],
        ], 200);
    }

    public function addNewBarangAPI(Request $request)
    {
        // 1. Validasi input
        $validated = $request->validate([
            'products'   => 'required|array',
            'products.*' => 'integer|exists:master_barang,id_master_barang',
        ]);

        $id_user_agen  = $request->user()->currentAccessToken()->user_id;


        // 2. (Opsional) hindari duplikat: cari produk yang sudah terdaftar
        $existing = BarangAgen::where('id_user_agen', $id_user_agen)
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
                'id_user_agen' => $id_user_agen,
                'harga_agen'   => $masterItems[$pid]->harga_karton_pabrik,
                'stok_karton'         => 10,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }

        // 4. Bulk insert
        BarangAgen::insert($records);

        // 5. Response JSON
        return response()->json([
            'message'     => 'Produk berhasil ditambahkan.',
            'added_count' => count($records),
            'data'        => $records,
        ], 201);
    }
}
