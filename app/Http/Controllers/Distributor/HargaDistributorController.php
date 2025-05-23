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
        $items = BarangDistributor::with('masterBarang:id_master_barang,nama_rokok,harga_karton_pabrik')
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
                'harga_pabrik'      => optional($d->masterBarang)->harga_karton_pabrik,
                'nama_rokok'       => optional($d->masterBarang)->nama_rokok,
            ];
        });

        return response()->json([
            'distributors'       => $data,
            'new_products_count' => $newProductsCount,
        ], 200);
    }

    public function updateHargaAPI(Request $request)
    {
        $validated = $request->validate([
            'prices'       => 'required|array',
            'prices.*'     => 'numeric|min:0',              // setiap nilai harus angka ≥ 0
            'prices_keys'  => 'prohibited',                 // pastikan client tidak kirim kunci terpisah
        ]);

        $pricesMap = $validated['prices'];
        $id_user_distributor = $request->user()->currentAccessToken()->user_id;
        $updated   = [];

        foreach ($pricesMap as $settingId => $newPrice) {
            // Cari record milik user ini
            $setting = BarangDistributor::where('id_barang_distributor', $settingId)
                ->where('id_user_distributor', $id_user_distributor)
                ->first();

            if (! $setting) {

                continue;
            }

            $setting->harga_distributor = $newPrice;
            $setting->save();

            $updated[] = [
                'id'               => $setting->id_barang_distributor,
                'nama_rokok'       => optional($setting->masterBarang)->nama_rokok,
                'harga_distributor' => $setting->harga_distributor,
            ];
        }

        return response()->json([
            'updated_count' => count($updated),
            'data'          => $updated,
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
            ->select('id_master_barang', 'nama_rokok', 'harga_karton_pabrik') // contoh kolom
            ->get();

        // kembalikan JSON
        return response()->json([
            'success' => true,
            'count'   => $newProducts->count(),
            'data'    => $newProducts
        ], 200);
    }
}
