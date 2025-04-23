<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Formulir Pengajuan Cuti - {{ $cuti->user->name ?? 'Karyawan' }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; line-height: 1.3; }
        @page { margin: 1cm 1.5cm; }
        .header { text-align: center; margin-bottom: 15px; }
        .header .company-name { font-size: 14pt; font-weight: bold; }
        .header .form-title { font-size: 12pt; font-weight: bold; text-decoration: underline; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; }
        .main-table td { vertical-align: top; padding: 0 5px; }
        .main-table .left-col { width: 55%; }
        .main-table .right-col { width: 45%; }
        .detail-table { width: 100%; margin-bottom: 8px; }
        .detail-table td { padding: 2px 0; }
        .detail-table td.label { width: 35%; font-weight: normal; }
        .detail-table td.separator { width: 2%; text-align: center; }
        .detail-table td.value { width: 63%; }
        .hrd-table { width: 100%; margin-top: 5px; border: 1px solid #000; }
        .hrd-table td { padding: 2px 5px; }
        .hrd-table .hrd-header { text-align: center; font-weight: bold; font-size: 8pt; border-bottom: 1px solid #000; }
        .checkbox { display: inline-block; width: 10px; height: 10px; border: 1px solid #000; margin-right: 5px; text-align: center; line-height: 10px; font-size: 8pt; font-weight: bold;}
        .approval-section { margin-top: 20px; width: 100%; border-collapse: separate; border-spacing: 5px;}
        .approval-section td { border: 1px solid #000; text-align: center; padding: 5px; vertical-align: top; height: 80px; }
        .approval-section .label-row td { height: auto; font-weight: bold; font-size: 9pt; border: none; padding-bottom: 2px;}
        .approval-section .name-row td { height: auto; font-size: 10pt; border: none; padding-top: 50px; }
        .footer-note { text-align: right; font-size: 8pt; margin-top: -10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="company-name">CSI INDONESIA</div>
            <div class="form-title">FORMULIR PENGAJUAN CUTI</div>
        </div>

        <table class="main-table">
            <tr>
                {{-- KOLOM KIRI --}}
                <td class="left-col">
                    <table class="detail-table">
                        <tr>
                            <td class="label">Nama</td>
                            <td class="separator">:</td>
                            <td class="value">{{ $cuti->user->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Unit Bisnis</td>
                            <td class="separator">:</td>
                            <td class="value">Geomin</td> {{-- Sesuai instruksi --}}
                        </tr>
                        <tr>
                            <td class="label">Tanggal Cuti</td>
                            <td class="separator">:</td>
                            <td class="value">{{ $cuti->mulai_cuti ? $cuti->mulai_cuti->format('d/m/Y') : '-' }} s/d {{ $cuti->selesai_cuti ? $cuti->selesai_cuti->format('d/m/Y') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Jumlah Hari Cuti</td>
                            <td class="separator">:</td>
                            <td class="value">{{ $cuti->lama_cuti ?? '0' }} hari (kerja)</td>
                        </tr>
                         <tr>
                            <td class="label">Jenis Cuti</td>
                            <td class="separator">:</td>
                            <td class="value">
                                @php
                                    $isTahunan = str_contains(strtolower($cuti->jenisCuti->nama_cuti ?? ''), 'tahunan');
                                @endphp
                                <span class="checkbox">{{ $isTahunan ? 'x' : '' }}</span> Tahunan
                                <span style="display:inline-block; width: 20px;"></span>
                                <span class="checkbox">{{ !$isTahunan ? 'x' : '' }}</span> Khusus {{-- Jika bukan tahunan, anggap khusus --}}
                                {{-- Tampilkan nama jika tidak ada kata tahunan/khusus? --}}
                                {{-- @if(!$isTahunan && !str_contains(strtolower($cuti->jenisCuti->nama_cuti ?? ''), 'khusus'))
                                     ({{ $cuti->jenisCuti->nama_cuti ?? '' }})
                                @endif --}}
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Keperluan</td>
                            <td class="separator">:</td>
                            <td class="value">{{ $cuti->keperluan ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Alamat Selama Cuti</td>
                            <td class="separator">:</td>
                            <td class="value">{{ $cuti->alamat_selama_cuti ?? '-' }}</td>
                        </tr>
                         <tr>
                            <td class="label">No. Handphone</td>
                            <td class="separator">:</td>
                            <td class="value">-</td> {{-- Sesuai instruksi --}}
                        </tr>
                    </table>
                </td>
                {{-- KOLOM KANAN --}}
                <td class="right-col">
                    <table class="detail-table">
                        <tr>
                            <td class="label">NIP</td>
                            <td class="separator">:</td>
                            <td class="value">-</td> {{-- Sesuai instruksi --}}
                        </tr>
                         <tr>
                            <td class="label">Jabatan</td>
                            <td class="separator">:</td>
                            <td class="value">{{ $cuti->user->jabatan ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="label">TMK</td>
                            <td class="separator">:</td>
                            <td class="value">-</td> {{-- Sesuai instruksi --}}
                        </tr>
                         <tr>
                            <td class="label">Area</td>
                            <td class="separator">:</td>
                            <td class="value">Laboratorium Pulogadung</td> {{-- Sesuai instruksi --}}
                        </tr>
                    </table>
                    {{-- Bagian HRD --}}
                    <table class="hrd-table">
                        <tr><td class="hrd-header">diisi oleh HRD</td></tr>
                        <tr>
                            <td>
                                <table class="detail-table" style="margin-bottom: 0;">
                                    <tr>
                                        <td class="label">Hak Cuti</td>
                                        <td class="separator">:</td>
                                        <td class="value">........... hari</td> {{-- Sesuai instruksi --}}
                                    </tr>
                                    <tr>
                                        <td class="label">Sisa Cuti</td>
                                        <td class="separator">:</td>
                                        <td class="value">........... hari</td> {{-- Sesuai instruksi --}}
                                    </tr>
                                     <tr>
                                        <td class="label">Jumlah Cuti</td>
                                        <td class="separator">:</td>
                                        <td class="value">{{ $cuti->lama_cuti ?? '0' }} hari</td> {{-- Sesuai instruksi --}}
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- KOLOM PERSETUJUAN / TANDA TANGAN --}}
        <table class="approval-section">
            {{-- Baris Label --}}
            <tr class="label-row">
                <td>PEMOHON</td>
                <td>KOLOM PERSETUJUAN</td> {{-- Ini untuk Asisten Manager --}}
                <td>ATASAN BERIKUTNYA</td> {{-- Ini untuk Manager --}}
                <td>PARAF HRD</td>
            </tr>
            {{-- Baris Ruang Tanda Tangan --}}
            <tr>
                <td>
                    {{-- Tampilkan TTD Pemohon jika status approved dan TTD ada? --}}
                    {{-- Contoh Placeholder: <img src="path/to/signature/pemohon.png" width="80"> --}}
                </td>
                <td>
                    {{-- Tampilkan TTD Asisten jika status approved dan TTD ada? --}}
                </td>
                <td>
                    {{-- Tampilkan TTD Manager jika status approved dan TTD ada? --}}
                </td>
                <td>
                    {{-- TTD HRD? --}}
                </td>
            </tr>
             {{-- Baris Nama --}}
             <tr class="name-row">
                 <td>({{ $cuti->user->name ?? '....................' }})</td>
                 {{-- Tampilkan nama hanya jika sudah pernah diapprove L1 --}}
                 <td>({{ $cuti->approverAsisten ? $cuti->approverAsisten->name : '....................' }})</td>
                  {{-- Tampilkan nama hanya jika sudah diapprove L2 --}}
                 <td>({{ $cuti->approverManager ? $cuti->approverManager->name : '....................' }})</td>
                 <td>(....................)</td> {{-- Nama HRD? --}}
             </tr>
        </table>
         <div class="footer-note">
              USER ANTAM {{-- Sesuai PDF Contoh --}}
         </div>

    </div> {{-- End Container --}}
</body>
</html>