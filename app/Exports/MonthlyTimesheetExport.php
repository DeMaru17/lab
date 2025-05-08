<?php

namespace App\Exports\Sheets;

use App\Models\MonthlyTimesheet;
use Maatwebsite\Excel\Concerns\FromView; // Menggunakan View Blade
use Maatwebsite\Excel\Concerns\WithTitle; // Memberi nama sheet
use Maatwebsite\Excel\Concerns\WithStyles; // Untuk styling
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Contracts\View\View; // Import View

class TimesheetSummarySheet implements FromView, WithTitle, WithStyles
{
    private MonthlyTimesheet $timesheet;

    public function __construct(MonthlyTimesheet $timesheet)
    {
        $this->timesheet = $timesheet;
    }

    /**
     * Mengembalikan view Blade yang akan dirender untuk sheet ini.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function view(): View
    {
        // Buat view Blade terpisah untuk summary Excel
        return view('monthly_timesheets.export_excel_summary', [
            'timesheet' => $this->timesheet
        ]);
    }

    /**
     * Memberikan judul untuk sheet ini.
     *
     * @return string
     */
    public function title(): string
    {
        return 'Ringkasan'; // Nama sheet
    }

    /**
     * Menerapkan styling ke sheet.
     *
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        // Contoh styling dasar: buat header bold
        $sheet->getStyle('A1:D1')->getFont()->setBold(true); // Sesuaikan range header info
        $sheet->getStyle('A3:K3')->getFont()->setBold(true); // Sesuaikan range header tabel summary
        $sheet->getStyle('A3:K' . ($sheet->getHighestRow()))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER); // Pusatkan data summary
        $sheet->getStyle('A4:K' . ($sheet->getHighestRow()))->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER); // Format angka

        // Auto size kolom berdasarkan konten
        foreach (range('A', 'K') as $columnID) { // Sesuaikan range kolom
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        return []; // Bisa tambahkan style array di sini jika perlu
    }
}
