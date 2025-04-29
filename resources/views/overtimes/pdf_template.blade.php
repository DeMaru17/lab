{{-- resources/views/overtimes/pdf_template.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Formulir Pengajuan Lembur - {{ $overtime->user->name ?? 'Karyawan' }}</title>
    {{-- Salin CSS dari template PDF Cuti Anda, sesuaikan jika perlu --}}
    <style type="text/css">
        body {
            font-family: Tahoma, DejaVu Sans, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
        }

        @page {
            margin: 1cm 1.5cm;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .header .company-name {
            font-size: 14pt;
            font-weight: bold;
        }

        .header .form-title {
            font-size: 12pt;
            font-weight: bold;
            text-decoration: underline;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .main-layout-table td {
            vertical-align: top;
            padding: 0;
        }

        .main-layout-table .left-column {
            width: 55%;
            padding-right: 10px;
        }

        .main-layout-table .right-column {
            width: 45%;
            padding-left: 10px;
        }

        .detail-table {
            width: 100%;
            margin-bottom: 5px;
        }

        .detail-table td {
            padding: 1px 0;
        }

        .detail-table td.label {
            width: 35%;
        }

        .detail-table td.separator {
            width: 2%;
            text-align: center;
        }

        .detail-table td.value {
            width: 63%;
        }

        /* Status Box (bisa disesuaikan) */
        .status-box {
            border: 1px solid #ccc;
            padding: 8px;
            margin-top: 10px;
            background-color: #f9f9f9;
            font-size: 9pt;
        }

        .status-box strong {
            font-size: 1em;
        }

        .status-approved {
            color: #28a745;
        }

        .status-rejected {
            color: #dc3545;
        }

        .status-pending {
            color: #ffc107;
        }

        .status-cancelled {
            color: #6c757d;
        }

        .notes {
            font-style: italic;
            color: #555;
        }

        /* Approval Section */
        .approval-header {
            font-size: 12pt;
            text-align: center;
            font-weight: bold;
            border: 2px solid #000;
            border-bottom: 1px solid #000;
            padding: 5px;
            margin-top: 20px;
        }

        .approval-section {
            width: 100%;
            border: 2px solid #000;
            border-top: none;
            margin-top: 0;
        }

        .approval-section td {
            text-align: center;
            vertical-align: top;
            height: 80px;
            padding: 5px;
        }

        .approval-section .label-row td {
            height: auto;
            font-weight: bold;
            font-size: 10pt;
            padding-bottom: 2px;
            border-bottom: 1px solid #000;
        }

        .approval-section .signature-row td {
            height: 60px;
            vertical-align: middle;
        }

        .approval-section .signature-row img {
            max-height: 55px;
            height: auto;
        }

        .approval-section .name-row td {
            height: auto;
            font-size: 10pt;
            border-top: 1px solid #000;
            padding-top: 2px;
        }

        .approval-section td:not(:last-child) {
            border-right: 1px solid #000;
        }

        .footer-note {
            text-align: right;
            font-size: 8pt;
            margin-top: 5px;
        }

        .logo {
            max-width: 80px;
            max-height: 40px;
            float: right;
        }
    </style>
</head>

<body>
    <div class="container">
        {{-- Header Utama --}}
        <table style="border: 2px solid #000; margin-bottom: 5px;">
            <tr>
                <td style="width: 80%; vertical-align: middle; font-size: 16pt; text-align: center; font-weight: bold;">
                    FORMULIR PENGAJUAN LEMBUR</td>
                <td style="width: 20%; text-align: right; vertical-align: middle;">
                    {{-- Logika Logo Vendor --}}
                    @php
                        $logoPath = null;
                        $defaultText = 'CSI INDONESIA';
                        if (
                            $overtime->user?->vendor?->logo_path &&
                            file_exists(public_path('storage/' . $overtime->user->vendor->logo_path))
                        ) {
                            $logoPath = public_path('storage/' . $overtime->user->vendor->logo_path);
                        }
                    @endphp
                    @if ($logoPath)
                        <img src="{{ $logoPath }}" class="logo" alt="Logo Vendor">
                    @else
                        {{ $defaultText }}
                    @endif
                </td>
            </tr>
        </table>

        {{-- Detail dalam Border Box --}}
        <div
            style="padding: 10px 10px 10px 10px; border-left: 2px solid #000; border-right: 2px solid #000; border-bottom: 2px solid #000;">
            {{-- Informasi Karyawan --}}
            <table class="detail-table">
                <tr>
                    <td class="label" style="width: 20%;">Nama</td>
                    <td class="separator" style="width: 2%;">:</td>
                    <td style="width: 78%;">{{ $overtime->user->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Jabatan</td>
                    <td class="separator">:</td>
                    <td>{{ $overtime->user->jabatan ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Unit Bisnis / Vendor</td>
                    <td class="separator">:</td>
                    <td>{{ $overtime->user->vendor->name ?? 'Geomin (Internal)' }}</td> {{-- Tampilkan Vendor atau default --}}
                </tr>
            </table>

            <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">

            {{-- Detail Lembur --}}
            <table class="detail-table">
                <tr>
                    <td class="label" style="width: 20%;">Tanggal Lembur</td>
                    <td class="separator" style="width: 2%;">:</td>
                    <td style="width: 78%;">
                        {{ $overtime->tanggal_lembur ? $overtime->tanggal_lembur->format('d F Y') : '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Jam Mulai</td>
                    <td class="separator">:</td>
                    <td>{{ $overtime->jam_mulai ? $overtime->jam_mulai->format('H:i') : '-' }} WIB</td>
                </tr>
                <tr>
                    <td class="label">Jam Selesai</td>
                    <td class="separator">:</td>
                    <td>{{ $overtime->jam_selesai ? $overtime->jam_selesai->format('H:i') : '-' }} WIB</td>
                </tr>
                <tr>
                    <td class="label">Durasi Lembur</td>
                    <td class="separator">:</td>
                    <td>
                        @if (!is_null($overtime->durasi_menit))
                            {{ floor($overtime->durasi_menit / 60) }} jam {{ $overtime->durasi_menit % 60 }} menit
                        @else
                            -
                        @endif
                        ({{ $overtime->durasi_menit ?? 0 }} menit)
                    </td>
                </tr>
                <tr>
                    <td class="label">Uraian Pekerjaan</td>
                    <td class="separator">:</td>
                    <td>{{ $overtime->uraian_pekerjaan ?? '-' }}</td>
                </tr>
            </table>

            {{-- Status Persetujuan --}}
            <div class="status-box">
                @php
                    $statusClass = '';
                    $statusText = Str::title(str_replace('_', ' ', $overtime->status));
                    switch ($overtime->status) {
                        case 'pending':
                            $statusClass = 'status-pending';
                            $statusText = 'Menunggu Approval Asisten';
                            break;
                        case 'pending_manager_approval':
                            $statusClass = 'status-pending';
                            $statusText = 'Menunggu Approval Manager';
                            break;
                        case 'approved':
                            $statusClass = 'status-approved';
                            break;
                        case 'rejected':
                            $statusClass = 'status-rejected';
                            break;
                        case 'cancelled':
                            $statusClass = 'status-cancelled';
                            $statusText = 'Dibatalkan oleh Pemohon';
                            break;
                    }
                @endphp
                Status Saat Ini: <strong class="{{ $statusClass }}">{{ $statusText }}</strong>
                <br>
                {{-- Detail Approval / Rejection --}}
                @if ($overtime->approved_by_asisten_id)
                    <span class="notes">Disetujui Level 1 oleh {{ $overtime->approverAsisten->name ?? 'N/A' }} pada
                        {{ $overtime->approved_at_asisten ? $overtime->approved_at_asisten->format('d/m/Y H:i') : '-' }}</span><br>
                @endif
                @if ($overtime->approved_by_manager_id)
                    <span class="notes">Disetujui Level 2 oleh {{ $overtime->approverManager->name ?? 'N/A' }} pada
                        {{ $overtime->approved_at_manager ? $overtime->approved_at_manager->format('d/m/Y H:i') : '-' }}</span><br>
                @endif
                @if ($overtime->rejected_by_id)
                    <span class="notes">Ditolak oleh {{ $overtime->rejecter->name ?? 'N/A' }} pada
                        {{ $overtime->rejected_at ? $overtime->rejected_at->format('d/m/Y H:i') : '-' }}</span><br>
                    <span class="notes">Alasan: {{ $overtime->notes ?? '-' }}</span><br>
                @endif
            </div>

        </div> {{-- End Border Box --}}

        {{-- Kolom Persetujuan Header --}}
        <div class="approval-header">KOLOM PERSETUJUAN</div>

        {{-- Bagian Tanda Tangan --}}
        <table class="approval-section">
            {{-- Baris Label TTD --}}
            <tr class="col-ttd label-row">
                <td class="col-33">PEMOHON</td>
                <td class="col-33">ATASAN BERIKUTNYA</td> {{-- Asisten Manager --}}
                <td class="col-33">USER ANTAM</td> {{-- Manager --}}
            </tr>
            {{-- Baris Gambar TTD --}}
            <tr class="signature-row">
                <td class="ttd-area">
                    @if (
                        $overtime->user &&
                            $overtime->user->signature_path &&
                            file_exists(public_path('storage/' . $overtime->user->signature_path)))
                        <img src="{{ public_path('storage/' . $overtime->user->signature_path) }}" alt="TTD Pemohon">
                    @endif
                </td>
                <td class="ttd-area">
                    @if (
                        $overtime->approved_by_asisten_id &&
                            $overtime->approverAsisten &&
                            $overtime->approverAsisten->signature_path &&
                            file_exists(public_path('storage/' . $overtime->approverAsisten->signature_path)))
                        <img src="{{ public_path('storage/' . $overtime->approverAsisten->signature_path) }}"
                            alt="TTD Asisten">
                    @endif
                </td>
                <td class="ttd-area">
                    @if (
                        $overtime->approved_by_manager_id &&
                            $overtime->approverManager &&
                            $overtime->approverManager->signature_path &&
                            file_exists(public_path('storage/' . $overtime->approverManager->signature_path)))
                        <img src="{{ public_path('storage/' . $overtime->approverManager->signature_path) }}"
                            alt="TTD Manager">
                    @endif
                </td>
            </tr>
            {{-- Baris Nama --}}
            <tr class="ttd-name">
                <td>({{ $overtime->user->name ?? '....................' }})</td>
                <td>({{ $overtime->approverAsisten->name ?? '....................' }})</td>
                <td>({{ $overtime->approverManager->name ?? '....................' }})</td>
            </tr>
        </table>

        {{-- Footer Note (jika perlu) --}}
        {{-- <div class="footer-note">
             Catatan Tambahan...
         </div> --}}

    </div> {{-- End Container --}}
</body>

</html>
