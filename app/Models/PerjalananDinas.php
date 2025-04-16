<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PerjalananDinas extends Model
{
    use HasFactory;

    // Nama tabel (opsional jika nama tabel sesuai dengan konvensi Laravel)
    protected $table = 'perjalanan_dinas';

    // Kolom yang dapat diisi (mass assignable)
    protected $fillable = [
        'user_id',
        'tanggal_berangkat',
        'perkiraan_tanggal_pulang',
        'tanggal_pulang',
        'jurusan',
        'lama_dinas',
        'status',
    ];

    // Casting kolom ke tipe data tertentu
    protected $casts = [
        'tanggal_berangkat' => 'date',
        'perkiraan_tanggal_pulang' => 'date',
        'tanggal_pulang' => 'date',
    ];

    // Relasi ke model User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Hitung lama dinas secara otomatis
    protected static function booted()
    {
        static::saving(function ($dinas) {
            $endDate = $dinas->tanggal_pulang ?? $dinas->perkiraan_tanggal_pulang;

            if ($endDate && $dinas->tanggal_berangkat) {
                $start = Carbon::parse($dinas->tanggal_berangkat);
                $end = Carbon::parse($endDate);

                // Pastikan tidak kebalik arah perhitungannya
                if ($end->greaterThanOrEqualTo($start)) {
                    $dinas->lama_dinas = $start->diffInDays($end) + 1;
                } else {
                    $dinas->lama_dinas = null; // Atau 0, atau bisa kasih warning kalau tanggalnya tidak logis
                }
            } else {
                $dinas->lama_dinas = null;
            }
        });

        // Tambahkan kuota cuti perjalanan dinas setelah perjalanan dinas selesai
        static::saved(function ($dinas) {
            // Periksa apakah perjalanan dinas sudah diproses
            if ($dinas->is_processed) {
                return; // Jika sudah diproses, lewati
            }

            if ($dinas->lama_dinas && $dinas->status === 'selesai') {
                // Hitung kuota cuti perjalanan dinas (1 hari cuti untuk setiap 10 hari dinas)
                $cutiTambahan = floor($dinas->lama_dinas / 10);

                if ($cutiTambahan > 0) {
                    // Ambil jenis cuti perjalanan dinas
                    $jenisCuti = JenisCuti::where('nama_cuti', 'Cuti Khusus Perjalanan Dinas')->first();

                    if ($jenisCuti) {
                        // Periksa apakah kuota sudah ada
                        $cutiQuota = CutiQuota::firstOrCreate(
                            [
                                'user_id' => $dinas->user_id,
                                'jenis_cuti_id' => $jenisCuti->id,
                            ],
                            [
                                'durasi_cuti' => 0,
                            ]
                        );

                        // Tambahkan kuota cuti perjalanan dinas
                        $cutiQuota->durasi_cuti += $cutiTambahan;
                        $cutiQuota->save();

                        // Tandai perjalanan dinas sebagai sudah diproses
                        $dinas->is_processed = true;
                        $dinas->save();
                    }
                }
            }
        });
    }
}
