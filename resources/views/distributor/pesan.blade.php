@extends('distributor.default')

@section('content')
    <section class="container mx-auto py-6 my-20">
        <div class="bg-white shadow-lg rounded-lg p-3">
            <h2 class="text-2xl font-bold mb-2 text-center text-gray-800">Pilih Produk dalam Ukuran Karton</h2>
            <p class="text-center text-gray-600 mb-6">Silakan pilih produk dalam ukuran karton yang ingin Anda pesan.</p>

            <!-- Pemberitahuan Kuantitas Per Karton -->
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-2 mb-6 rounded-lg">
                <p class="font-medium">Perhatian!</p>
                <p>Harga yang tertera adalah harga produk per karton. Atur kuantitas setelah klik "Lanjut Pesanan".</p>
            </div>

            <form action="{{ route('detailPesananDistributor') }}" method="POST">
                @csrf
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 py-3">
                    {{-- Card Produk --}}
                    @foreach ($barangPabriks as $index => $barang)
                       
                            <label class="relative block cursor-pointer">
                                <input type="checkbox" class="absolute opacity-0 peer"
                                    id="product{{ $barang->id_master_barang }}" name="products[]"
                                    value="{{ $barang->id_master_barang }}">
                                <div
                                    class="bg-white p-3 rounded-lg border border-gray-200 shadow-md transition-colors duration-150 peer-checked:bg-gray-300 peer-checked:border-green-500 peer-checked:border-2 peer-checked:shadow-lg w-full max-w-[180px] mx-auto">
                                    <div class="relative mb-2">
                                        <img src="{{ asset('storage/produk/' . $gambarRokokList[$index]) }}"
                                            alt="{{ $namaRokokList[$index] }}"
                                            class="w-full h-[200px] object-cover rounded-md border border-gray-200">
                                    </div>
                                    <div class="text-center">
                                        <h2 class="text-sm font-bold text-gray-800">{{ $namaRokokList[$index] }}</h2>
                                        <p class="text-gray-600 text-sm">Rp
                                            {{ number_format($barang->harga_karton_pabrik, 0, ',', '.') }}</p>
                                    </div>
                                </div>
                            </label>
                
                    @endforeach
                </div>

                <div class="sticky bottom-0 bg-white w-full flex justify-center p-4">
                    <button type="submit"
                        class="bg-gray-800 text-white font-bold py-3 px-6 rounded-md hover:bg-gray-600 transition duration-300 w-2/3 lg:w-1/4">
                        Lanjut Pesanan <i class="fa-solid fa-forward ml-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(event) {
                const checkboxes = document.querySelectorAll('input[name="products[]"]:checked');
                if (checkboxes.length === 0) {
                    event.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Produk Belum Dipilih',
                        text: 'Silakan pilih produk terlebih dahulu sebelum melanjutkan pemesanan.',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });
    </script>
@endsection
