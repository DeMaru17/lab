<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $table = 'holidays'; // Sesuaikan jika nama tabel berbeda
    protected $primaryKey = 'tanggal'; // Tentukan primary key
    public $incrementing = false; // Karena primary key bukan integer auto-increment
    protected $keyType = 'string'; // Karena primary key adalah date (diperlakukan sbg string oleh Eloquent)

    public $timestamps = false; // Tidak menggunakan created_at/updated_at

    protected $fillable = [
        'tanggal',
        'nama_libur',
    ];

    // Casting tanggal ke objek Carbon (opsional tapi bagus)
    protected $casts = [
        'tanggal' => 'date',
    ];
}
