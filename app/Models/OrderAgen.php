<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderAgen extends Model
{
    use HasFactory;

    // Nama tabel yang digunakan model
    protected $table = 'order_agen';

    // Primary key dari tabel
    protected $primaryKey = 'id_order';

    // Mengatur timestamps (created_at dan updated_at)
    public $timestamps = true;

    // Kolom yang dapat diisi
    protected $fillable = [
        'id_user_agen',
        'id_user_distributor',
        'jumlah',
        'total',
        'tanggal',
        'bukti_transfer',
        'status_pemesanan'
    ];

    public function userAgen()
    {
        return $this->belongsTo(UserAgen::class, 'id_user_agen', 'id_user_agen');
    }

    public function detailAgen()
    {
        return $this->hasMany(OrderDetailAgen::class, 'id_order', 'id_order');
    }

    public function distributor()
    {
        return $this->belongsTo(UserDistributor::class, 'id_user_distributor');
    }

    public function agen()
    {
        return $this->belongsTo(UserAgen::class, 'id_user_agen');
    }
}
