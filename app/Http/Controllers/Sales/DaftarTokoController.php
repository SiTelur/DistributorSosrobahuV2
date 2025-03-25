<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\DaftarToko;
use App\Models\KunjunganToko;
use Illuminate\Http\Request;

class DaftarTokoController extends Controller
{
    /**
     * Function untuk menampilkan semua daftar toko
     */
    public function index()
    {
        $id_user_sales = session('id_user_sales');

        $toko = DaftarToko::where('id_user_sales', $id_user_sales)
            ->paginate(5); // Mengambil 5 data per halaman
        return view('sales.tokoSales', compact('toko'));
    }

    /**
     * Function untuk menampilkan kunjungan toko berdasarkan id daftar toko
     */
    public function getByDaftarToko($id_daftar_toko)
    {
        $kunjunganToko = KunjunganToko::where('id_daftar_toko', $id_daftar_toko)->get();
        return response()->json($kunjunganToko);
    }

    /**
     * Function untuk menampilkan toko berdasarkan id
     */
    public function show($id)
    {
        $daftarToko = DaftarToko::find($id);
        if (!$daftarToko) {
            return redirect()->route('daftar_toko.index')->with('error', 'Toko tidak ditemukan.');
        }
        return view('daftar_toko.show', compact('daftarToko'));
    }

    // Function untuk memanggil halaman/view input
    public function create()
    {
        return view('daftar_toko.create');
    }

    /**
     * Function untuk input ke database
     */
    public function store(Request $request)
    {
        // Validasi inputan
        $request->validate([
            'nama_toko' => 'required|string|max:255',
            'lokasi' => 'required|string|max:255',
            'nama_pemilik' => 'required|string|max:255',
            'no_telp' => 'required|string|max:100',
        ]);

        // Ambil id_user_sales dari session
        $id_user_sales = session('id_user_sales');

        // Tambahkan id_user_sales ke dalam inputan data
        $data = $request->all();
        $data['id_user_sales'] = $id_user_sales;

        // Simpan data toko baru
        $newToko = DaftarToko::create($data);

        // Mengambil jumlah toko yang ada untuk user sales yang sedang login
        $totalTokoSales = DaftarToko::where('id_user_sales', $id_user_sales)->count();

        // Tentukan jumlah toko per halaman
        $perPage = 5; // Misalnya 5 toko per halaman

        // Hitung halaman tempat toko baru berada
        $newPage = ceil($totalTokoSales / $perPage);

        // Redirect ke halaman yang berisi toko baru
        return redirect()->route('tokoSales',  ['page' => $newPage])->with('success', 'Toko berhasil ditambahkan.');
    }


    // Function untuk memanggil halaman/view toko
    public function showtoko(DaftarToko $daftarToko)
    {
        return view('daftar_toko.show', compact('daftarToko'));
    }


    // Function untuk memanggil halaman/view edit
    public function edit(DaftarToko $daftarToko)
    {
        return view('daftar_toko.edit', compact('daftarToko'));
    }

    /**
     * Function untuk Mengupdate ke database 
     */

    public function update(Request $request, $id_daftar_toko)
    {
        $request->validate([
            'nama_toko' => 'required|string|max:255',
            'lokasi' => 'required|string|max:255',
            'nama_pemilik' => 'required|string|max:255',
            'no_telp' => 'required|string|max:100',
        ]);


        $daftarToko = DaftarToko::find($id_daftar_toko);
        if (!$daftarToko) {
            return response()->json(['message' => 'Data not found'], 404);
        } else {
            $daftarToko->nama_toko = $request->nama_toko;
            $daftarToko->lokasi = $request->lokasi;
            $daftarToko->nama_pemilik = $request->nama_pemilik;
            $daftarToko->no_telp = $request->no_telp;
            $daftarToko->save();
        }
        // Ambil parameter halaman saat ini
        $currentPage = $request->input('page', 1); // Default ke halaman 1 jika tidak ada parameter page

        // Redirect ke halaman yang sama
        return redirect()->route('tokoSales', ['page' => $currentPage])->with('success', 'Toko berhasil diperbarui.');
    }

    /**
     * Function untuk Menghapus atau delete ke database
     */

    public function destroy($id_daftar_toko)
    {
        $daftarToko = DaftarToko::find($id_daftar_toko);

        if ($daftarToko) {
            // Hapus semua kunjungan terkait toko ini
            $daftarToko->kunjunganToko()->delete();

            // Hapus toko
            $daftarToko->delete();

            return redirect()->route('tokoSales')->with('success', 'Toko dan kunjungan terkait berhasil dihapus.');
        } else {
            return redirect()->route('tokoSales')->with('error', 'Toko tidak ditemukan.');
        }
    }

    public function daftarTokoAPI(Request $request)
    {
        $id_user_sales = $request->user()->currentAccessToken()->user_id;

        // Mengambil daftar toko berdasarkan id_user_sales dengan pagination
        $toko = DaftarToko::where('id_user_sales', $id_user_sales)
            ->paginate(5);

        // Mengembalikan response dalam format JSON
        return response()->json([
            'message' => 'Daftar toko berhasil diambil',
            'stores' => $toko,
        ], 200);
    }

    public function storeTokoAPI(Request $request)
    {
        $validatedData = $request->validate([
            'nama_toko' => 'required|string|max:255',
            'lokasi' => 'required|string|max:255',
            'nama_pemilik' => 'required|string|max:255',
            'no_telp' => 'required|string|max:100',
        ]);

        // Ambil id_user_sales dari token
        $id_user_sales = $request->user()->currentAccessToken()->user_id;

        // Tambahkan id_user_sales ke dalam data yang divalidasi
        $validatedData['id_user_sales'] = $id_user_sales;

        // Simpan data toko baru
        $newToko = DaftarToko::create($validatedData);

        // Mengembalikan response JSON
        return response()->json([
            'message' => 'Toko berhasil ditambahkan!',
            'store' => $newToko,
        ], 201);
    }

    public function updateTokoAPI(Request $request, $id_daftar_toko)
    {
        $validatedData = $request->validate([
            'nama_toko' => 'required|string|max:255',
            'lokasi' => 'required|string|max:255',
            'nama_pemilik' => 'required|string|max:255',
            'no_telp' => 'required|string|max:100',
        ]);

        $id_user_sales = $request->user()->currentAccessToken()->user_id;

        $daftarToko = DaftarToko::where('id_daftar_toko', $id_daftar_toko)->where('id_user_sales', $id_user_sales)->first();

        if (!$daftarToko) {
            return response()->json(['message' => 'Data not found or unauthorized'], 404);
        }

        $daftarToko->update($validatedData);

        return response()->json([
            'message' => 'Toko berhasil diperbarui!',
            'store' => $daftarToko,
        ], 200);
    }

    public function deleteTokoAPI($id_daftar_toko)
    {

        $daftarToko = DaftarToko::where('id_daftar_toko', $id_daftar_toko)->first();

        if (!$daftarToko) {
            return response()->json(['message' => 'Toko tidak ditemukan atau tidak memiliki akses'], 404);
        }

        $daftarToko->kunjunganToko()->delete(); // Hapus semua kunjungan terkait
        $daftarToko->delete(); // Hapus toko

        return response()->json(['message' => 'Toko dan kunjungan terkait berhasil dihapus.'], 200);
    }
}
