<?php

namespace App\Exports;

use App\Models\Overtime;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\View\View; // Import View contract
use Maatwebsite\Excel\Concerns\FromView; // Import FromView
use Maatwebsite\Excel\Concerns\WithTitle; // Import WithTitle
use Maatwebsite\Excel\Concerns\ShouldAutoSize; // Import ShouldAutoSize (opsional)
use Carbon\Carbon;
use Illuminate\Http\Request; // Import Request
use Illuminate\Support\Collection; // Import Collection

class OvertimeRecapExport implements FromView, WithTitle, ShouldAutoSize
{
    // Properti untuk menyimpan data filter dan hasil query
    protected Request $request;
    protected Collection $recapData;
    protected string $startDate;
    protected string $endDate;

    /**
     * Constructor untuk menerima filter dan data
     */
    public function __construct(Request $request, Collection $recapData)
    {
        $this->request = $request;
        $this->recapData = $recapData;
        $this->startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $this->endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
    }

    /**
     * Menentukan view Blade yang akan digunakan sebagai template Excel.
     *
     * @return View
     */
    public function view(): View
    {
        // Kirim data yang sudah diproses ke view Excel
        return view('overtimes.recap.excel_template', [
            'recapData' => $this->recapData,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            // Kirim juga filter lain jika perlu ditampilkan di header Excel
             'selectedUserName' => $this->request->filled('user_id') ? User::find($this->request->input('user_id'))->name ?? 'Semua' : 'Semua',
             'selectedVendorName' => $this->getVendorNameFromFilter($this->request->input('vendor_id')),
             'selectedStatus' => $this->request->input('status', 'approved') ?: 'Semua',
        ]);
    }

    /**
     * Menentukan judul/nama sheet Excel.
     *
     * @return string
     */
    public function title(): string
    {
        // Buat judul sheet berdasarkan rentang tanggal
        $start = Carbon::parse($this->startDate)->format('d M Y');
        $end = Carbon::parse($this->endDate)->format('d M Y');
        return "Rekap Lembur {$start} - {$end}";
    }

    /**
     * Helper untuk mendapatkan nama vendor dari filter ID.
     */
    private function getVendorNameFromFilter($vendorId): string
    {
        if (!$vendorId) {
            return 'Semua';
        }
        if ($vendorId === 'is_null') {
            return 'Internal Karyawan';
        }
        return Vendor::find($vendorId)->name ?? 'Vendor Tidak Ditemukan';
    }
}
