<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Timesheet Bulanan - {{ $timesheet->user?->name ?? 'Karyawan' }} - {{ $timesheet->period_start_date?->format('M Y') ?? 'Periode' }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            margin: 0; /* Hapus margin default browser */
        }
        .container {
            padding: 20px; /* Atau sesuaikan */
            width: 100%;
            box-sizing: border-box;
        }
        .header-table, .content-table, .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .header-table td {
            padding: 5px;
            vertical-align: top;
        }
        .content-table th, .content-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        .content-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }
        .content-table td.text-center { text-align: center; }
        .content-table td.text-right { text-align: right; }

        .logo-container {
            width: 100px; /* Sesuaikan ukuran logo */
            text-align: left;
        }
        .logo-container img {
            max-width: 100%;
            height: auto;
        }
        .company-info {
            text-align: right;
        }
        .title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 5px;
            text-decoration: underline;
        }
        .summary-table td {
            padding: 3px 0;
        }
        .summary-label {
            font-weight: bold;
            width: 40%; /* Sesuaikan */
        }

        .signature-table td {
            height: 70px; /* Ruang untuk tanda tangan */
            vertical-align: bottom;
            text-align: center;
            border: 1px solid #eee; /* Opsional: border tipis untuk guide */
            padding: 5px;
            width: 33.33%; /* Bagi rata untuk 3 kolom tanda tangan */
        }
        .signature-label {
            font-size: 9px;
            margin-top: 5px;
        }
        .footer {
            text-align: center;
            font-size: 8px;
            position: fixed;
            bottom: 0;
            width: 100%;
            left: 0;
        }
        /* Page break jika diperlukan (dompdf support) */
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- KOP SURAT / HEADER --}}
        <table class="header-table">
            <tr>
                <td class="logo-container">
                    {{-- Ganti dengan path logo perusahaan Anda --}}
                    {{-- Jika logo dari public storage: <img src="{{ public_path('path/to/your/company_logo.png') }}" alt="Logo Perusahaan"> --}}
                    {{-- Jika logo vendor dari $timesheet->user->vendor->logo_path (pastikan path lengkap atau resolve): --}}
                    @if($timesheet->user?->vendor?->logo_path && file_exists(public_path('storage/' . $timesheet->user->vendor->logo_path)))
                        <img src="{{ public_path('storage/' . $timesheet->user->vendor->logo_path) }}" alt="Logo Vendor" style="max-height: 50px;">
                    @else
                        {{-- <img src="{{ public_path('images/default_logo.png') }}" alt="Logo Default" style="max-height: 50px;"> --}}
                        <p><strong>{{ $timesheet->user?->vendor?->name ?? 'PT. LAB ABC INDONESIA' }}</strong></p> {{-- Placeholder jika tidak ada logo --}}
                    @endif
                </td>
                <td class="company-info">
                    {{-- Info Perusahaan Anda --}}
                    {{-- <p><strong>PT. LAB ABC INDONESIA</strong><br>
                    Jl. Contoh Alamat No. 123<br>
                    Jakarta, Indonesia<br>
                    Telp: (021) 1234567</p> --}}
                </td>
            </tr>
        </table>

        <div class="title">REKAPITULASI TIMESHEET BULANAN</div>

        {{-- INFORMASI KARYAWAN & PERIODE --}}
        <table class="summary-table" style="width: 60%; margin-bottom: 15px;">
            <tr>
                <td class="summary-label">Nama Karyawan</td>
                <td>: {{ $timesheet->user?->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="summary-label">Jabatan</td>
                <td>: {{ $timesheet->user?->jabatan ?? '-' }}</td>
            </tr>
            <tr>
                <td class="summary-label">Vendor</td>
                <td>: {{ $timesheet->user?->vendor?->name ?? 'Internal' }}</td>
            </tr>
             <tr>
                <td class="summary-label">Periode Timesheet</td>
                <td>: {{ $timesheet->period_start_date?->format('d M Y') }} s/d {{ $timesheet->period_end_date?->format('d M Y') }}</td>
            </tr>
            <tr>
                <td class="summary-label">Status</td>
                <td>: <strong>{{ Str::title(str_replace('_', ' ', $timesheet->status)) }}</strong></td>
            </tr>
        </table>

        {{-- RINGKASAN TOTAL --}}
        <div class="section-title">Ringkasan</div>
        <table class="content-table" style="font-size: 9px;">
            <thead>
                <tr>
                    <th>Hari Kerja Efektif</th>
                    <th>Total Hadir</th>
                    <th>Total Terlambat</th>
                    <th>Total Pulang Cepat</th>
                    <th>Total Alpha</th>
                    <th>Total Cuti/Sakit</th>
                    <th>Total Dinas Luar</th>
                    <th>Total Lembur (Jam)</th>
                    <th>Lembur di Hari Libur</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">{{ $timesheet->total_work_days }}</td>
                    <td class="text-center">{{ $timesheet->total_present_days }}</td>
                    <td class="text-center">{{ $timesheet->total_late_days }}</td>
                    <td class="text-center">{{ $timesheet->total_early_leave_days }}</td>
                    <td class="text-center">{{ $timesheet->total_alpha_days }}</td>
                    <td class="text-center">{{ $timesheet->total_leave_days }}</td>
                    <td class="text-center">{{ $timesheet->total_duty_days }}</td>
                    <td class="text-center">{{ $timesheet->total_overtime_formatted }}</td>
                    <td class="text-center">{{ $timesheet->total_holiday_duty_days }}</td>
                </tr>
            </tbody>
        </table>

        {{-- DETAIL ABSENSI HARIAN --}}
        <div class="section-title">Detail Kehadiran Harian</div>
        <table class="content-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Tanggal</th>
                    <th style="width: 15%;">Shift</th>
                    <th style="width: 10%;">Masuk</th>
                    <th style="width: 10%;">Keluar</th>
                    <th style="width: 15%;">Status Harian</th>
                    <th style="width: 35%;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($dailyAttendances as $attendance)
                    <tr>
                        <td>{{ $attendance->attendance_date?->format('d/m/Y (D)') }}</td>
                        <td>{{ $attendance->shift?->name ?? '-' }}</td>
                        <td class="text-center">{{ $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i') : '-' }}</td>
                        <td class="text-center">{{ $attendance->clock_out_time ? $attendance->clock_out_time->format('H:i') : '-' }}</td>
                        <td>{{ $attendance->attendance_status ?? 'N/A' }}</td>
                        <td>{{ $attendance->notes ?? '-' }} {{ $attendance->is_corrected ? '(Dikoreksi)' : '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">Tidak ada detail data absensi untuk periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- TANDA TANGAN --}}
        @if($timesheet->status === 'approved')
            <div class="section-title" style="margin-top: 30px;">Persetujuan</div>
            <table class="signature-table">
                <tr>
                    <td>
                        Disiapkan oleh,<br><br><br><br><br>
                        ( {{ $timesheet->user?->name ?? 'Karyawan' }} )<br>
                        <span class="signature-label">{{ $timesheet->user?->jabatan ?? 'Karyawan' }}</span>
                    </td>
                    <td>
                        Disetujui oleh (Asisten Manager),<br>
                        @if($timesheet->approverAsisten?->signature_path && file_exists(public_path('storage/' . $timesheet->approverAsisten->signature_path)))
                            <img src="{{ public_path('storage/' . $timesheet->approverAsisten->signature_path) }}" alt="TTD Asisten" style="max-height: 50px; margin-top:5px; margin-bottom:5px;">
                        @else
                            <br><br><br><br><br>
                        @endif
                        ( {{ $timesheet->approverAsisten?->name ?? '..............................' }} )<br>
                        <span class="signature-label">{{ $timesheet->approverAsisten?->jabatan ?? 'Asisten Manager' }}</span>
                    </td>
                    <td>
                        Disetujui oleh (Manager),<br>
                         @if($timesheet->approverManager?->signature_path && file_exists(public_path('storage/' . $timesheet->approverManager->signature_path)))
                            <img src="{{ public_path('storage/' . $timesheet->approverManager->signature_path) }}" alt="TTD Manager" style="max-height: 50px; margin-top:5px; margin-bottom:5px;">
                        @else
                            <br><br><br><br><br>
                        @endif
                        ( {{ $timesheet->approverManager?->name ?? '..............................' }} )<br>
                        <span class="signature-label">{{ $timesheet->approverManager?->jabatan ?? 'Manager' }}</span>
                    </td>
                </tr>
            </table>
        @endif

        {{-- FOOTER (Dicetak di setiap halaman jika PDF multi-halaman) --}}
        {{-- <div class="footer">
            Dokumen ini dicetak oleh sistem pada {{ now()->format('d M Y H:i:s') }}
        </div> --}}
    </div>
</body>
</html>
