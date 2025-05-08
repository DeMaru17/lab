<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Timesheet Bulanan - {{ $timesheet->user->name ?? 'N/A' }} - {{ $timesheet->period_start_date->format('M Y') }}</title>
    <style>
        /* Basic styling for PDF */
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px; /* Ukuran font lebih kecil untuk PDF */
            line-height: 1.4;
            color: #333;
        }
        .container {
            width: 100%;
            margin: 0 auto;
            padding: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #000;
        }
        .header p {
            margin: 2px 0;
            font-size: 12px;
        }
        .employee-info table, .summary-table table, .detail-table table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .employee-info th, .employee-info td,
        .summary-table th, .summary-table td,
        .detail-table th, .detail-table td {
            border: 1px solid #ddd;
            padding: 5px; /* Padding lebih kecil */
            text-align: left;
        }
        .employee-info th {
            width: 150px; /* Lebar kolom label info karyawan */
            background-color: #f8f9fa;
        }
         .summary-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }
         .summary-table td {
            text-align: center;
        }
        .detail-table th {
            background-color: #e9ecef;
            font-weight: bold;
             text-align: center;
        }
        .detail-table td {
            /* text-align: left; */ /* Biarkan default */
        }
        .detail-table .text-center {
            text-align: center;
        }
        .signatures {
            margin-top: 40px;
            width: 100%;
        }
        .signatures table {
            width: 100%;
            border: none;
        }
        .signatures td {
            width: 33.33%;
            text-align: center;
            padding: 30px 10px 0 10px; /* Padding atas untuk ruang tanda tangan */
            font-size: 10px;
            border: none; /* Hapus border untuk signature */
            vertical-align: bottom; /* Teks di bawah */
        }
        .signatures .signer-name {
            font-weight: bold;
            text-decoration: underline;
            margin-top: 40px; /* Jarak untuk TTD */
            display: inline-block; /* Agar underline pas */
        }
        .signatures .signer-title {
            font-size: 9px;
        }
        .page-break {
            page-break-after: always;
        }
        /* Status colors (optional, might not render perfectly in all PDF viewers) */
        .status-hadir { /* Tidak ada warna khusus */ }
        .status-terlambat, .status-terlambat-pulang-cepat { color: #ffc107; font-weight: bold; } /* Kuning/Oranye */
        .status-pulang-cepat { color: #0dcaf0; font-weight: bold; } /* Biru muda */
        .status-alpha { color: #dc3545; font-weight: bold; } /* Merah */
        .status-cuti, .status-sakit { color: #0d6efd; } /* Biru */
        .status-dinas-luar { color: #6f42c1; } /* Ungu */
        .status-lembur { color: #198754; font-weight: bold; } /* Hijau */
        .status-libur { color: #6c757d; } /* Abu-abu */
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {{-- TODO: Tambahkan Nama Perusahaan/Departemen jika perlu --}}
            <h1>Rekap Timesheet Bulanan</h1>
            <p>Periode: {{ $timesheet->period_start_date->format('d F Y') }} - {{ $timesheet->period_end_date->format('d F Y') }}</p>
        </div>

        <div class="employee-info">
            <table>
                <tr>
                    <th>Nama Karyawan</th>
                    <td>{{ $timesheet->user->name ?? 'N/A' }}</td>
                    <th>Vendor</th>
                    <td>{{ $timesheet->vendor->name ?? 'Internal' }}</td>
                </tr>
                <tr>
                    <th>Jabatan</th>
                    <td>{{ $timesheet->user->jabatan ?? 'N/A' }}</td>
                    <th>Status Approval</th>
                    <td><strong>{{ Str::title(str_replace('_', ' ', $timesheet->status)) }}</strong></td>
                </tr>
            </table>
        </div>

        <div class="summary-table">
             <h3>Ringkasan Kehadiran & Lembur</h3>
            <table>
                <thead>
                    <tr>
                        <th>Hari Kerja</th>
                        <th>Hadir</th>
                        <th>Telat</th>
                        <th>Plg Cepat</th>
                        <th>Alpha</th>
                        <th>Cuti/Sakit</th>
                        <th>Dinas Luar</th>
                        <th>Lembur (Libur)</th>
                        <th>Total Lembur</th>
                        <th>Jml Lembur</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $timesheet->total_work_days }}</td>
                        <td>{{ $timesheet->total_present_days }}</td>
                        <td>{{ $timesheet->total_late_days }}</td>
                        <td>{{ $timesheet->total_early_leave_days }}</td>
                        <td>{{ $timesheet->total_alpha_days }}</td>
                        <td>{{ $timesheet->total_leave_days }}</td>
                        <td>{{ $timesheet->total_duty_days }}</td>
                        <td>{{ $timesheet->total_holiday_duty_days }}</td>
                        <td>{{ $timesheet->total_overtime_formatted }}</td>
                        <td>{{ $timesheet->total_overtime_occurrences }}x</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="detail-table">
            <h3>Detail Absensi Harian</h3>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Hari</th>
                        <th>Shift</th>
                        <th>Jam Masuk</th>
                        <th>Jam Keluar</th>
                        <th>Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @php $currentDate = null; @endphp
                    @forelse ($attendances as $att)
                        @php
                            // Tentukan class CSS untuk status
                            $statusClass = 'status-' . Str::slug(strtolower($att->attendance_status ?? ''));
                        @endphp
                        <tr>
                            <td class="text-center">{{ $att->attendance_date->format('d/m/Y') }}</td>
                            <td class="text-center">{{ $att->attendance_date->isoFormat('dddd') }}</td> {{-- Nama hari Indonesia --}}
                            <td class="text-center">{{ $att->shift?->name ?? '-' }}</td>
                            <td class="text-center">{{ $att->clock_in_time ? \Carbon\Carbon::parse($att->clock_in_time)->format('H:i:s') : '-' }}</td>
                            <td class="text-center">{{ $att->clock_out_time ? \Carbon\Carbon::parse($att->clock_out_time)->format('H:i:s') : '-' }}</td>
                            <td class="text-center {{ $statusClass }}">
                                {{ $att->attendance_status ?? 'N/A' }}
                                @if($att->is_corrected)
                                    <small>(Koreksi)</small>
                                @endif
                            </td>
                            <td>{{ $att->notes }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">Tidak ada data absensi detail untuk periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="signatures">
            <table>
                <tr>
                    <td>
                        Disiapkan Oleh,<br>
                        {{-- Kosongkan untuk TTD Karyawan --}}
                        <br><br><br><br>
                        <span class="signer-name">{{ $timesheet->user->name ?? '(_________________)' }}</span><br>
                        <span class="signer-title">{{ $timesheet->user->jabatan ?? 'Karyawan' }}</span>
                    </td>
                    <td>
                        Disetujui Oleh,<br>
                        (Asisten Manajer)<br>
                         {{-- Tampilkan nama jika sudah diapprove L1 --}}
                         @if($timesheet->approverAsisten)
                            <br><br><br><br> {{-- Beri ruang lebih jika ada nama --}}
                            <span class="signer-name">{{ $timesheet->approverAsisten->name }}</span><br>
                            <span class="signer-title">{{ $timesheet->approverAsisten->jabatan }}</span>
                         @else
                            <br><br><br><br>
                            <span class="signer-name">(_________________)</span><br>
                            <span class="signer-title">Asisten Manajer Terkait</span>
                         @endif
                    </td>
                    <td>
                        Disetujui Oleh,<br>
                        (Manager)<br>
                        {{-- Tampilkan nama jika sudah diapprove L2 --}}
                         @if($timesheet->approverManager)
                             <br><br><br><br>
                            <span class="signer-name">{{ $timesheet->approverManager->name }}</span><br>
                            <span class="signer-title">{{ $timesheet->approverManager->jabatan }}</span>
                         @else
                            <br><br><br><br>
                            <span class="signer-name">(_________________)</span><br>
                            <span class="signer-title">Manager</span>
                         @endif
                    </td>
                </tr>
            </table>
        </div>

    </div>
</body>
</html>
