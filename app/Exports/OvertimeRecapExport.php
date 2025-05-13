<?php

namespace App\Exports;

use App\Models\Overtime; // Model Overtime, meskipun tidak langsung digunakan di sini, relevan dengan konteks data.
use App\Models\User;     // Model User, untuk mendapatkan nama user berdasarkan filter.
use App\Models\Vendor;   // Model Vendor, untuk mendapatkan nama vendor berdasarkan filter.
use Illuminate\Contracts\View\View; // Interface View, untuk menentukan view Blade yang akan dirender.
use Maatwebsite\Excel\Concerns\FromView; // Concern dari Maatwebsite/Excel untuk export dari Blade view.
use Maatwebsite\Excel\Concerns\WithTitle; // Concern untuk menentukan judul/nama sheet Excel.
use Maatwebsite\Excel\Concerns\ShouldAutoSize; // Concern (opsional) untuk mengatur ukuran kolom otomatis.
use Carbon\Carbon; // Untuk manipulasi dan format tanggal.
use Illuminate\Http\Request; // Objek Request untuk mengakses filter yang dikirim dari controller.
use Illuminate\Support\Collection; // Menggunakan Collection untuk tipe data $recapData.

/**
 * Class OvertimeRecapExport
 *
 * Kelas ini bertanggung jawab untuk menghasilkan file Excel berisi rekapitulasi data lembur.
 * Menggunakan package Maatwebsite/Excel, kelas ini mengimplementasikan beberapa concern
 * untuk mengontrol bagaimana data diformat dan ditampilkan dalam file Excel.
 * Data dirender dari sebuah view Blade, memungkinkan layout yang fleksibel.
 *
 * @package App\Exports
 */
class OvertimeRecapExport implements FromView, WithTitle, ShouldAutoSize
{
    /**
     * Objek Request yang berisi parameter filter dari pengguna.
     * Digunakan untuk menentukan rentang tanggal, user, vendor, dan status yang akan diekspor.
     *
     * @var \Illuminate\Http\Request
     */
    protected Request $request;

    /**
     * Koleksi data rekapitulasi lembur yang sudah diproses dan akan diekspor.
     * Struktur data ini biasanya disiapkan oleh controller yang memanggil export ini.
     *
     * @var \Illuminate\Support\Collection
     */
    protected Collection $recapData;

    /**
     * Tanggal mulai untuk periode rekapitulasi lembur (format YYYY-MM-DD).
     *
     * @var string
     */
    protected string $startDate;

    /**
     * Tanggal selesai untuk periode rekapitulasi lembur (format YYYY-MM-DD).
     *
     * @var string
     */
    protected string $endDate;

    /**
     * Constructor untuk kelas OvertimeRecapExport.
     * Menerima data filter dari Request dan koleksi data rekapitulasi yang akan diekspor.
     *
     * @param \Illuminate\Http\Request $request Objek Request yang berisi filter.
     * @param \Illuminate\Support\Collection $recapData Koleksi data lembur yang sudah diproses.
     * @return void
     */
    public function __construct(Request $request, Collection $recapData)
    {
        $this->request = $request;
        $this->recapData = $recapData;
        // Mengambil tanggal mulai dan selesai dari request, dengan default jika tidak ada.
        $this->startDate = $request->input('start_date', Carbon::now(config('app.timezone', 'Asia/Jakarta'))->startOfMonth()->toDateString());
        $this->endDate = $request->input('end_date', Carbon::now(config('app.timezone', 'Asia/Jakarta'))->endOfMonth()->toDateString());
    }

    /**
     * Menentukan view Blade yang akan digunakan sebagai template untuk konten file Excel.
     * Data yang diperlukan oleh view (seperti $recapData, $startDate, $endDate, dan filter lainnya)
     * akan dikirimkan ke view ini.
     *
     * @return \Illuminate\Contracts\View\View Instance view Blade.
     */
    public function view(): View
    {
        // Mengirim data yang sudah diproses dan parameter filter ke view Blade 'excel_template'.
        // View ini akan merender tabel HTML yang kemudian dikonversi menjadi sheet Excel.
        return view('overtimes.recap.excel_template', [
            'recapData' => $this->recapData, // Data utama rekap lembur.
            'startDate' => $this->startDate, // Tanggal mulai periode.
            'endDate' => $this->endDate,     // Tanggal selesai periode.
            // Mengirim informasi filter tambahan untuk ditampilkan di header Excel jika diperlukan.
            'selectedUserName' => $this->request->filled('user_id')
                                 ? (User::find($this->request->input('user_id'))->name ?? 'Semua User')
                                 : 'Semua User',
            'selectedVendorName' => $this->getVendorNameFromFilter($this->request->input('vendor_id')),
            'selectedStatus' => $this->request->input('status', 'approved') ?: 'Semua Status', // Jika status kosong, anggap 'Semua'.
        ]);
    }

    /**
     * Menentukan judul atau nama untuk sheet di dalam file Excel.
     * Judul ini akan ditampilkan sebagai nama tab sheet.
     *
     * @return string Judul sheet Excel.
     */
    public function title(): string
    {
        // Membuat judul sheet yang dinamis berdasarkan rentang tanggal rekap.
        $start = Carbon::parse($this->startDate)->format('d M Y');
        $end = Carbon::parse($this->endDate)->format('d M Y');
        return "Rekap Lembur {$start} - {$end}";
    }

    /**
     * Helper method (private) untuk mendapatkan nama vendor dari ID vendor yang ada di filter.
     * Ini digunakan untuk menampilkan nama vendor yang difilter di header Excel.
     *
     * @param string|int|null $vendorId ID vendor dari filter. Bisa berupa ID, 'is_null', atau null.
     * @return string Nama vendor yang sesuai, atau "Semua" / "Internal Karyawan".
     */
    private function getVendorNameFromFilter($vendorId): string
    {
        if (!$vendorId) {
            return 'Semua Vendor'; // Jika tidak ada filter vendor_id.
        }
        if ($vendorId === 'is_null') {
            // 'is_null' adalah nilai khusus yang digunakan di filter untuk menandakan karyawan internal.
            return 'Internal Karyawan';
        }
        // Cari nama vendor berdasarkan ID. Jika tidak ditemukan, kembalikan pesan default.
        return Vendor::find($vendorId)->name ?? 'Vendor Tidak Ditemukan';
    }

    // Concern `ShouldAutoSize` tidak memerlukan implementasi method tambahan di sini.
    // Dengan mengimplementasikannya, Maatwebsite/Excel akan mencoba mengatur lebar kolom secara otomatis.
}
