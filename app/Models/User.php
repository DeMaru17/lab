<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\JenisCuti;
use App\Models\CutiQuota;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'jenis_kelamin',
        'password',
        'role',
        'jabatan',
        'tanggal_mulai_bekerja',
        'vendor_id',
        'signature_path',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'tanggal_mulai_bekerja' => 'date', // Pastikan ini ada
        'password' => 'hashed',
    ];

    public function cutiQuotas()
    {
        return $this->hasMany(CutiQuota::class);
    }

    // Di dalam class User
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Hitung lama bekerja dalam bulan.
     *
     * @return int
     */
    public function getLamaBekerjaAttribute()
    {
        if (!$this->tanggal_mulai_bekerja) {
            return 0; // Jika tanggal mulai bekerja belum diisi
        }

        return Carbon::parse($this->tanggal_mulai_bekerja)->diffInMonths(now());
    }

    /**
     * Event yang dijalankan saat user dibuat.
     */
    protected static function booted()
    {
        static::created(function ($user) {
            if (!$user->tanggal_mulai_bekerja) {
                return; // Jika tanggal mulai bekerja tidak diisi, lewati
            }

            $jenisCuti = JenisCuti::all();

            foreach ($jenisCuti as $cuti) {
                // Periksa apakah jenis cuti adalah "Cuti Tahunan"
                if ($cuti->nama_cuti === 'Cuti Tahunan') {
                    // Periksa apakah karyawan telah bekerja selama lebih dari 12 bulan
                    if ($user->lama_bekerja < 12) {
                        continue; // Lewati jika belum memenuhi syarat masa kerja
                    }
                }

                // Tentukan durasi default untuk jenis cuti
                $durasiCuti = $cuti->durasi_default;

                // Periksa apakah kuota sudah ada
                $existingQuota = CutiQuota::where('user_id', $user->id)
                    ->where('jenis_cuti_id', $cuti->id)
                    ->first();

                if (!$existingQuota) {
                    // Buat kuota cuti jika belum ada
                    CutiQuota::create([
                        'user_id' => $user->id,
                        'jenis_cuti_id' => $cuti->id,
                        'durasi_cuti' => $durasiCuti,
                    ]);
                }
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
