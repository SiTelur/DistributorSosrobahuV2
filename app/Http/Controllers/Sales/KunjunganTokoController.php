<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\KunjunganToko;
use Illuminate\Http\Request;
use App\Models\DaftarToko;
use Carbon\Carbon;

class KunjunganTokoController extends Controller
{
    public function index($id_toko)
    {
        // Ambil informasi toko jika diperlukan
        $toko = DaftarToko::find($id_toko);

        if (!$toko) {
            return redirect()->back()->with('error', 'Toko tidak ditemukan');
        }

        // Mengambil data kunjungan toko dengan pagination, 5 item per halaman
        $kunjunganToko = KunjunganToko::where('id_daftar_toko', $id_toko)
            ->orderBy('tanggal', 'desc') // Urutkan berdasarkan tanggal terbaru di atas
            ->paginate(5); // Pagination dengan 5 item per halaman

        $gambarTokoList = [];

        // Jika kamu ingin mengubah format tanggal untuk ditampilkan di view
        foreach ($kunjunganToko as $visit) {
            $visit->tanggal = Carbon::parse($visit->tanggal);
            $gambarTokoList[] = $visit->gambar;
        }

        return view('sales.kunjunganToko', [
            'storeName' => $toko->nama_toko, // Nama toko untuk ditampilkan di view
            'kunjunganToko' => $kunjunganToko, // Pastikan ini adalah hasil paginasi
            'id_toko' => $id_toko,
            'gambarTokoList' => $gambarTokoList
        ]);
    }

    // Function untuk menampilkan kunjungan toko berdasarkan id
    public function show($id)
    {
        $kunjunganToko = KunjunganToko::find($id);
        if (!$kunjunganToko) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        return response()->json($kunjunganToko);
    }

    /**
     * Function untuk Menginput data ke database 
     */
    public function store(Request $request)
    {
        // Validasi input
        // $request->validate([
        //     'id_daftar_toko' => 'required|integer',
        //     'tanggal' => 'required|date',
        //     'sisa_produk' => 'required|integer',
        //     'gambar' => 'required|file|image',
        // ]);

        // Menyimpan gambar dengan format yang diinginkan
        if ($request->hasFile('gambar')) {
            $tanggal = \Carbon\Carbon::parse($request->tanggal)->format('d-m-Y');
            $extension = $request->file('gambar')->getClientOriginalExtension();
            $imageName = "dokumentasi-{$tanggal}.{$extension}";
            $path = $request->file('gambar')->storeAs('toko', $imageName, 'public');
        } else {
            $path = null; // Jika tidak ada gambar yang diupload
        }


        $id_user_sales = session('id_user_sales');

        $kunjunganBaru =  KunjunganToko::create([
            'id_daftar_toko' => $request->id_daftar_toko,
            'id_user_sales' => $id_user_sales,
            'tanggal' => $request->tanggal,
            'sisa_produk' => $request->sisa_produk,
            'gambar' => $path, // Simpan nama gambar
        ]);


        // Ambil semua kunjungan untuk toko ini, urutkan berdasarkan tanggal terbaru
        $kunjunganToko = KunjunganToko::where('id_daftar_toko', $request->id_daftar_toko)
            ->orderBy('tanggal', 'desc')
            ->get();

        // Hitung posisi dari kunjungan yang baru ditambahkan
        $index = $kunjunganToko->search(function ($kunjungan) use ($kunjunganBaru) {
            return $kunjungan->id_kunjungan_toko === $kunjunganBaru->id_kunjungan_toko;
        });


        // Hitung nomor halaman untuk kunjungan baru
        $perPage = 5; // Jumlah kunjungan per halaman
        $currentPage = ceil(($index + 1) / $perPage); // +1 untuk offset karena $index dimulai dari 0

        // Redirect ke halaman yang benar
        return redirect()->route('kunjunganToko', [
            'id_daftar_toko' => $request->id_daftar_toko,
            'page' => $currentPage
        ])->with('success', 'Toko berhasil ditambahkan.');
    }


    /**
     * Function untuk Mengupdate ke database 
     */
    public function update(Request $request, $id_kunjungan_toko)
    {
        $request->validate([
            'id_daftar_toko' => 'required|integer',
            'tanggal' => 'required|date',
            'sisa_produk' => 'required|integer'
        ]);

        // Debugging request
        // dd($request->all(), $request->file('gambar'));   

        $kunjunganToko = KunjunganToko::find($id_kunjungan_toko);
        if (!$kunjunganToko) {
            return response()->json(['message' => 'Data not found'], 404);
        } else {
            $kunjunganToko->tanggal = $request->tanggal;
            $kunjunganToko->sisa_produk = $request->sisa_produk;

            if ($request->hasFile('gambar')) {
                // Mendapatkan tanggal dan memformatnya
                $tanggal = \Carbon\Carbon::parse($request->tanggal)->format('d-m-Y');
                $extension = $request->file('gambar')->getClientOriginalExtension();
                $namaGambar = "dokumentasi-{$tanggal}." . $extension;

                // Menyimpan gambar dengan nama yang ditentukan
                $gambarPath = $request->file('gambar')->storeAs('images', $namaGambar, 'public');
                $kunjunganToko->gambar = $gambarPath;
            }

            $kunjunganToko->save();
        }

        // Ambil parameter halaman saat ini
        $currentPage = $request->input('page', 1);

        // Redirect ke halaman yang sama
        return redirect()->route('kunjunganToko', [
            'id_daftar_toko' => $kunjunganToko->id_daftar_toko,
            'page' => $currentPage
        ])->with('success', 'Kunjungan toko berhasil diperbarui.');
    }


    /**
     * Function untuk Menghapus atau delete ke database
     */

    public function destroy($id_kunjungan_toko)
    {
        $kunjunganToko = KunjunganToko::find($id_kunjungan_toko);

        if ($kunjunganToko) {

            $id_daftar_toko = $kunjunganToko->id_daftar_toko;
            $kunjunganToko->delete();
            return redirect()->route('kunjunganToko', ['id_daftar_toko' => $id_daftar_toko])->with('success', 'Kunjungan toko berhasil dihapus.');
        } else {
            return redirect()->back()->with('error', 'Kunjungan toko tidak ditemukan.');
        }
    }

    public function getKunjunganTokoAPI($id_toko)
    {
        $toko = DaftarToko::find($id_toko);

        if (!$toko) {
            return response()->json(['message' => 'Toko tidak ditemukan'], 404);
        }

        // Mengambil data kunjungan toko dengan pagination, 5 item per halaman
        $kunjunganToko = KunjunganToko::where('id_daftar_toko', $id_toko)
            ->orderBy('tanggal', 'desc')
            ->paginate(5);

        // Mengubah format tanggal dan mengumpulkan gambar
        $kunjunganToko->each(function ($visit) {
            $visit->tanggal = Carbon::parse($visit->tanggal);
        });

        return response()->json([
            'storeName' => $toko->nama_toko,
            'id_toko' => $id_toko,
            'kunjunganToko' => $kunjunganToko,
            'gambarTokoList' => $kunjunganToko->pluck('gambar'),
        ], 200);
    }

    public function insertKunjunganTokoAPI(Request $request, $id_toko)
    {
        $validatedData = $request->validate([
            'tanggal' => 'required|date',
            'sisa_produk' => 'required|integer',
            'gambar' => 'nullable|file|image',
        ]);

        $id_user_sales = $request->user()->currentAccessToken()->user_id;

        // Menyimpan gambar dengan format yang diinginkan
        $path = null;
        if ($request->hasFile('gambar')) {
            $tanggal = Carbon::parse($request->tanggal)->format('d-m-Y');
            $extension = $request->file('gambar')->getClientOriginalExtension();
            $imageName = "dokumentasi-{$tanggal}.{$extension}";
            $path = $request->file('gambar')->storeAs('toko', $imageName, 'public');
        }

        $kunjunganBaru = KunjunganToko::create([
            'id_daftar_toko' => $id_toko,
            'id_user_sales' => $id_user_sales,
            'tanggal' => $validatedData['tanggal'],
            'sisa_produk' => $validatedData['sisa_produk'],
            'gambar' => $path,
        ]);

        return response()->json([
            'message' => 'Kunjungan toko berhasil disimpan!',
            'data' => $kunjunganBaru,
        ], 201);
    }

    public function updateKunjunganTokoAPI(Request $request, $id_kunjungan_toko)
    {
        $validatedData = $request->validate([
            'tanggal' => 'required|date',
            'sisa_produk' => 'required|integer',
        ]);

        $kunjunganToko = KunjunganToko::find($id_kunjungan_toko);
        if (!$kunjunganToko) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        $kunjunganToko->tanggal = $validatedData['tanggal'];
        $kunjunganToko->sisa_produk = $validatedData['sisa_produk'];

        if ($request->hasFile('gambar')) {
            $tanggal = Carbon::parse($request->tanggal)->format('d-m-Y');
            $extension = $request->file('gambar')->getClientOriginalExtension();
            $namaGambar = "dokumentasi-{$tanggal}.{$extension}";
            $gambarPath = $request->file('gambar')->storeAs('toko', $namaGambar, 'public');
            $kunjunganToko->gambar = $gambarPath;
        }

        $kunjunganToko->save();

        return response()->json([
            'message' => 'Kunjungan toko berhasil diperbarui!',
            'data' => $kunjunganToko,
        ], 201);
    }
}
