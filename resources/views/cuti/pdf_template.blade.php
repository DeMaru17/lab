{{-- resources/views/cuti/pdf_template.blade.php --}}
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/> {{-- Penting --}}
    <title>Formulir Pengajuan Cuti - {{ $cuti->user->name ?? 'Karyawan' }}</title>
</head>
<style type="text/css">
    /* CSS Anda di sini (Disalin dari template Anda) */
    div { font-family: Tahoma, DejaVu Sans, sans-serif; } /* Tambah fallback unicode */
    table { width: 100%; border-collapse: collapse; }
    td { margin-top: 2px; margin-bottom: 2px; margin-left: 10px; font-size: 10pt; vertical-align: top;} /* Atur font size & v-align */
    .col-judul { font-size: 16pt; text-align: center; font-weight: bold; height: 50px }
    .col-subjudul { font-size: 12pt; text-align: center; font-weight: bold; }
    .col-ttd { font-size: 10pt; text-align: center; font-weight: bold; }
    .col-left { width: 20%; }
    .col-mid { width: 2%; text-align: center; } /* Pusatkan titik dua */
    .col-right { width: 33%; }
    .col-tglcuti { width: 18%; }
    .col-sd { width: 5%; text-align: center; } /* Class untuk "s/d" */
    .col-15 { width: 15%; }
    .col-11 { width: 11%; }
    .col-33 { width: 33%; }
    /* CSS untuk bagian HRD yang baru */
    .hrd-section-table td { padding: 1px 0; } /* Kurangi padding di tabel HRD */
    .hrd-label { width: 25%; } /* Lebar label Hak/Jumlah Cuti */
    .hrd-separator { width: 2%; text-align: center; }
    .hrd-value { width: 23%; } /* Lebar value Hak/Jumlah Cuti */
    .hrd-label-sisa { width: 25%; } /* Lebar label Sisa Cuti */
    .hrd-value-sisa { width: 23%; } /* Lebar value Sisa Cuti */
    .hrd-box { border: 2px solid #000; width: 25%; /* Sesuaikan lebar box paraf */ text-align: center; vertical-align: top;}
    .hrd-box-header { font-weight: bold; font-size: 9pt; border-bottom: 1px solid #000; padding: 2px; }
    .hrd-box-space { height: 50px; } /* Ruang untuk paraf */
    .hrd-note { font-style: italic; font-weight: bold; font-size: 9pt; margin-bottom: 5px; display: block;}

    .logo { max-width: 80px; max-height: 35px;}
    .checkbox { font-family: DejaVu Sans, sans-serif; display: inline-block; width: 12px; height: 12px; border: 1px solid #000; margin-right: 5px; text-align: center; line-height: 12px; font-size: 10pt; font-weight: bold;}
    .ttd-area { height: 60px; vertical-align: middle; text-align: center; }
    .ttd-area img { max-height: 55px; height: auto; }
    .ttd-name { font-size: 10pt; text-align: center; }
    .ttd-jabatan { font-size: 10pt; text-align: center;}
    .footer-note { text-align: right; font-size: 8pt; margin-top: 5px; }
    /* Tambahkan style untuk border tabel TTD */
    .ttd-table { border: 2px solid #000; margin-top: 0; border-top: none; }
    .ttd-table td { border: none; } /* Hapus border default cell */
    .ttd-table .label-row td, .ttd-table .ttd-name td { border-top: 1px solid #000; } /* Garis atas untuk baris nama & label */
    .ttd-table td:not(:last-child) { border-right: 1px solid #000; } /* Garis kanan antar kolom */
</style>
<body>
    <div style="padding: 10px;">
        {{-- Header Utama --}}
        <table style="border: 2px solid #000;">
            <tr>
                <td class="col-judul" style="width: 80%; vertical-align: middle;">FORMULIR PENGAJUAN CUTI</td>
                <td style="width: 20%; text-align: right; vertical-align: middle;">
                    {{-- Logika Logo Vendor --}}
                    @php
                        $logoPath = null;
                        $defaultText = 'CSI INDONESIA';
                        if ($cuti->user?->vendor?->logo_path && file_exists(public_path('storage/' . $cuti->user->vendor->logo_path))) {
                            $logoPath = public_path('storage/' . $cuti->user->vendor->logo_path);
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
        <div style="padding: 10px 10px 0 10px; border-left: 2px solid #000; border-right: 2px solid #000; border-bottom: 2px solid #000;">
            {{-- ... (Tabel Nama s/d No Handphone seperti sebelumnya) ... --}}
            <table><tr><td class="col-left">Nama</td><td class="col-mid">:</td><td class="col-right">{{ $cuti->user->name ?? '-' }}</td><td class="col-left">Unit Bisnis</td><td class="col-mid">:</td><td>Geomin</td></tr></table>
            <table><tr><td class="col-left">NIP</td><td class="col-mid">:</td><td class="col-right">-</td><td class="col-left">Area</td><td class="col-mid">:</td><td>Laboratorium Pulogadung</td></tr></table>
            <table><tr><td class="col-left">Jabatan</td><td class="col-mid">:</td><td class="col-right">{{ $cuti->user->jabatan ?? '-' }}</td><td class="col-left">TMK</td><td class="col-mid">:</td><td>-</td></tr></table>
            <table><tr><td class="col-left">Tanggal Cuti</td><td class="col-mid">:</td><td class="col-tglcuti">{{ $cuti->mulai_cuti ? $cuti->mulai_cuti->format('d/m/Y') : '-' }}</td><td class="col-sd">s/d</td><td class="col-tglcuti">{{ $cuti->selesai_cuti ? $cuti->selesai_cuti->format('d/m/Y') : '-' }}</td><td></td></tr></table>
            <table><tr><td class="col-left">Jumlah Hari Cuti</td><td class="col-mid">:</td><td>{{ $cuti->lama_cuti ?? '0' }} hari (kerja)</td></tr></table>
            <table>@php $isTahunan = str_contains(strtolower($cuti->jenisCuti->nama_cuti ?? ''), 'tahunan'); @endphp<tr><td class="col-left">Jenis Cuti</td><td class="col-mid">:</td><td class="col-mid"><span class="checkbox">{{ $isTahunan ? '✔' : '' }}</span></td><td class="col-left" style="width: auto;">Tahunan</td><td class="col-mid"><span class="checkbox">{{ !$isTahunan ? '✔' : '' }}</span></td><td class="">Khusus</td></tr></table>
            <table><tr><td class="col-left">Keperluan</td><td class="col-mid">:</td><td>{{ $cuti->keperluan ?? '-' }}</td></tr></table>
            <table><tr><td class="col-left">Alamat Selama Cuti</td><td class="col-mid">:</td><td>{{ $cuti->alamat_selama_cuti ?? '-' }}</td></tr></table>
            <table><tr><td class="col-left">No Handphone</td><td class="col-mid">:</td><td>-</td></tr></table>

            {{-- === AWAL BAGIAN HRD (REVISI) === --}}
            <table style="width: 100%; margin-top: 10px;">
                <tr>
                    {{-- Kolom Kiri untuk Detail --}}
                    <td style="width: 70%; vertical-align: top;">
                        <span class="hrd-note"><i>diisi oleh HRD</i></span>
                        <table class="hrd-section-table">
                            <tr>
                                <td class="hrd-label">Hak Cuti</td>
                                <td class="hrd-separator">:</td>
                                <td class="hrd-value">........... hari</td>
                                <td class="hrd-label-sisa">Sisa Cuti</td>
                                <td class="hrd-separator">:</td>
                                <td class="hrd-value-sisa">........... hari</td>
                            </tr>
                            <tr>
                                <td class="hrd-label">Jumlah Cuti</td>
                                <td class="hrd-separator">:</td>
                                <td class="hrd-value">{{ $cuti->lama_cuti ?? '0' }} hari</td>
                                <td colspan="3"></td> {{-- Span sisa kolom --}}
                            </tr>
                        </table>
                    </td>
                    {{-- Kolom Kanan untuk Box Paraf --}}
                    <td class="hrd-box">
                        <div class="hrd-box-header">PARAF HRD</div>
                        <div class="hrd-box-space">&nbsp;</div> {{-- Ruang kosong untuk paraf --}}
                    </td>
                </tr>
            </table>
            {{-- === AKHIR BAGIAN HRD (REVISI) === --}}

        </div> {{-- End Border Box --}}

        {{-- Kolom Persetujuan Header --}}
        <table>
            <tr>
                <td class="col-subjudul" style="border-left: 2px solid #000;border-right: 2px solid #000; border-top: 2px solid #000; border-bottom: 1px solid #000;">KOLOM PERSETUJUAN</td>
            </tr>
        </table>

        {{-- Bagian Tanda Tangan (Tetap sama) --}}
        <table class="ttd-table">
            {{-- Baris Label TTD --}}
            <tr class="col-ttd label-row">
                <td class="col-33">PEMOHON</td>
                <td class="col-33">ATASAN BERIKUTNYA</td>
                <td class="col-33">USER ANTAM</td>
            </tr>
             {{-- Baris Gambar TTD --}}
            <tr class="signature-row">
                <td class="ttd-area">
                     @if ($cuti->user && $cuti->user->signature_path && file_exists(public_path('storage/' . $cuti->user->signature_path)))
                         <img src="{{ public_path('storage/' . $cuti->user->signature_path) }}" alt="TTD Pemohon">
                     @endif
                </td>
                <td class="ttd-area">
                     @if ($cuti->approved_by_asisten_id && $cuti->approverAsisten && $cuti->approverAsisten->signature_path && file_exists(public_path('storage/' . $cuti->approverAsisten->signature_path)))
                         <img src="{{ public_path('storage/' . $cuti->approverAsisten->signature_path) }}" alt="TTD Asisten">
                     @endif
                </td>
                <td class="ttd-area">
                     @if ($cuti->approved_by_manager_id && $cuti->approverManager && $cuti->approverManager->signature_path && file_exists(public_path('storage/' . $cuti->approverManager->signature_path)))
                          <img src="{{ public_path('storage/' . $cuti->approverManager->signature_path) }}" alt="TTD Manager">
                     @endif
                </td>
            </tr>
             {{-- Baris Nama --}}
            <tr class="ttd-name">
                 <td>({{ $cuti->user->name ?? '....................' }})</td>
                 <td>({{ $cuti->approverAsisten->name ?? '....................' }})</td>
                 <td>({{ $cuti->approverManager->name ?? '....................' }})</td>
            </tr>
            <tr class="ttd-jabatan">
                <td>{{$cuti->user->jabatan}}</td>
                <td>{{$cuti->approverAsisten->jabatan}}</td>
                <td>{{$cuti->approverManager->jabatan}}</td>
            </tr>

        </table>

    </div> {{-- End Padding Wrapper --}}
</body>
</html>
