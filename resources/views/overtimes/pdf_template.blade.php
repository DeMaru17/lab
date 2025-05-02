{{-- resources/views/overtimes/pdf_template.blade.php --}}
@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    // Helper function to format duration (optional, bisa juga di model/controller)
    if (!function_exists('formatDuration')) {
        function formatDuration($totalMinutes)
        {
            if (is_null($totalMinutes) || $totalMinutes < 0) {
                return '-';
            }
            $hours = floor($totalMinutes / 60);
            $minutes = $totalMinutes % 60;
            return sprintf('%d Jam %02d Menit', $hours, $minutes);
        }
    }
@endphp
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Surat Perintah Kerja Lembur - {{ $overtime->user->name ?? 'Karyawan' }}</title>
    <style type="text/css">
        /* CSS Anda di sini (Disalin dari template Anda) */
        div {
            font-family: Tahoma, DejaVu Sans, sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: -1px;
            font-family: Tahoma;
        }

        td {
            padding-left: 5px;
            font-size: 10pt;
            vertical-align: top;
        }

        /* Default font size & v-align */
        .title {
            padding-left: 50px;
            font-weight: bold;
            font-size: 11pt;
        }

        /* Sesuaikan size */
        img.logo {
            max-height: 45px;
            height: auto;
            padding-right: 5px;
            float: right;
        }

        /* Gunakan max-height & float */
        .center {
            text-align: center;
        }

        table.bordered {
            border: 2px solid #000;
        }

        table.bordered td,
        table.bordered th {
            border: 1px solid #000;
        }

        /* Tambahkan border untuk sel ttd jika perlu */
        .jamlembur {
            width: 60px;
            text-align: center;
        }

        /* Lebarkan sedikit & center */
        .ttd-cell {
            width: 25%;
            height: 50px;
            vertical-align: middle;
            text-align: center;
        }

        /* Cell untuk TTD */
        .ttd-cell img {
            max-height: 45px;
            height: auto;
        }

        /* Ukuran gambar TTD */
        .name-row td {
            text-align: center;
            font-size: 10pt;
            padding-top: 2px;
        }

        .jabatan-row td {
            text-align: center;
            font-size: 9pt;
            padding-bottom: 10px;
        }

        /* Class dari template sebelumnya (jika masih relevan) */
        .col-left {
            width: 15%;
        }

        /* Sesuaikan lebar jika perlu */
        .col-mid {
            width: 2%;
            text-align: center;
        }

        /* Hapus col-right, col-tglcuti, dll jika tidak dipakai di struktur baru */
    </style>
</head>

<body>
    {{-- Tabel Header --}}
    <table class="center bordered">
        <tr>
            <td class="title"
                style="width: 85%; border-right: 2px solid #000; border-bottom: none; padding: 5px 0 0 50px;">
                SURAT PERINTAH KERJA LEMBUR <br>
                {{ $overtime->user->vendor->name ?? 'CSI INDONESIA (Internal)' }} <br> {{-- Isi Nama Vendor --}}
                UNIT BISNIS / LOKASI : Geomin / Laboratorium Pulogadung {{-- Isi Nama Unit Bisnis / Lokasi --}}
            </td>
            <td style="width: 15%; border-left: none; border-bottom: none; text-align: center; vertical-align: middle;">
                {{-- Logika Logo Vendor --}}
                @php
                    $logoPath = null;
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
                    {{-- Tampilkan teks default atau logo default --}}
                    {{-- CSI INDONESIA --}}
                    &nbsp; {{-- Kosongkan jika tidak ada logo --}}
                @endif
            </td>
        </tr>
    </table>

    {{-- Tabel Detail --}}
    <table class="bordered">
        <tr style="height: 30px;">
            <td colspan="3" style="border: none; padding-top: 10px;">Bersama ini menugaskan kepada:</td>
        </tr>
        <tr>
            <td style="width: 15%; border: none;">Nama</td>
            <td style="width: 2%; border: none;">:</td>
            <td style="border: none;">{{ $overtime->user->name ?? '-' }}</td> {{-- Isi Nama --}}
        </tr>
        <tr>
            <td style="border: none;">NIP</td>
            <td style="border: none;">:</td>
            <td style="border: none;">-</td> {{-- Isi NIP (Kosong) --}}
        </tr>
        <tr>
            <td style="border: none;">Jabatan</td>
            <td style="border: none;">:</td>
            <td style="border: none;">{{ $overtime->user->jabatan ?? '-' }}</td> {{-- Isi Jabatan --}}
        </tr>
        <tr>
            <td style="border: none;">Hari/Tanggal</td>
            <td style="border: none;">:</td>
            {{-- Format Hari, Tanggal Bulan Tahun --}}
            <td style="border: none;">
                {{ $overtime->tanggal_lembur ? $overtime->tanggal_lembur->locale('id_ID')->isoFormat('dddd, D MMMM YYYY') : '-' }}
            </td>
        </tr>
        <tr style="height: 30px;">
            <td colspan="3" style="border: none;">Untuk melaksanakan lembur pada:</td>
        </tr>
        <tr>
            {{-- Tabel Lembur Nested --}}
            <td colspan="3" style="border: none; padding-left: 50px; padding-right: 50px;"> {{-- Beri padding agar tidak terlalu lebar --}}
                <table class="center bordered">
                    <thead>
                        <tr>
                            <th colspan="3">Jam Lembur</th>
                            <th rowspan="2" style="vertical-align: middle;">Uraian Pekerjaan</th>
                        </tr>
                        <tr>
                            <th class="jamlembur">Mulai</th>
                            <th class="jamlembur">Selesai</th>
                            <th class="jamlembur">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="height: 50px;"> {{-- Beri tinggi agar uraian muat --}}
                            <td class="jamlembur">{{ $overtime->jam_mulai ? $overtime->jam_mulai->format('H:i') : '-' }}
                            </td> {{-- Isi Mulai Lembur --}}
                            <td class="jamlembur">
                                {{ $overtime->jam_selesai ? $overtime->jam_selesai->format('H:i') : '-' }}</td>
                            {{-- Isi Selesai Lembur --}}
                            <td class="jamlembur">{{ formatDuration($overtime->durasi_menit) }}</td>
                            {{-- Isi Total Lembur --}}
                            <td style="text-align: left;">{{ $overtime->uraian_pekerjaan ?? '-' }}</td>
                            {{-- Isi Uraian Pekerjaan --}}
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        <tr style="height: 30px;">
            <td colspan="3" style="border: none;">Demikian surat perintah ini dibuat untuk keperluan sebagaimana
                mestinya.</td>
        </tr>
    </table>

    {{-- Tabel Tanda Tangan --}}
    <table style="border: 2px solid #000; border-collapse: collapse;">
        <tr>
            <td style="padding-top: 10px; border: none;" colspan="2">Pulo Gadung,
                {{ now()->isoFormat('D MMMM YYYY') }}</td> {{-- Isi Tanggal Hari Ini --}}
            <td style="border: none;" colspan="2"></td>
        </tr>
        <tr style="border-bottom: 1px solid #000;">
            <td style="border: none; text-align: center; padding-bottom: 5px;">Dibuat Oleh,</td>
            <td style="border: none; text-align: center; padding-bottom: 5px;">Diperiksa Oleh,</td>
            <td style="border: none; text-align: center; padding-bottom: 5px;" colspan="2">Diketahui Oleh,</td>
        </tr>
        <tr>
            <td class="ttd-cell"> {{-- TTD Pemohon --}}
                @if (
                    $overtime->user &&
                        $overtime->user->signature_path &&
                        file_exists(public_path('storage/' . $overtime->user->signature_path)))
                    <img src="{{ public_path('storage/' . $overtime->user->signature_path) }}" alt="TTD Pemohon">
                @endif
            </td>
            <td class="ttd-cell"> {{-- TTD Pemeriksa (Kosong) --}}
            </td>
            <td class="ttd-cell"> {{-- TTD Asisten Manager --}}
                @if (
                    $overtime->approved_by_asisten_id &&
                        $overtime->approverAsisten &&
                        $overtime->approverAsisten->signature_path &&
                        file_exists(public_path('storage/' . $overtime->approverAsisten->signature_path)))
                    <img src="{{ public_path('storage/' . $overtime->approverAsisten->signature_path) }}"
                        alt="TTD Asisten">
                @endif
            </td>
            <td class="ttd-cell"> {{-- TTD Manager --}}
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
        <tr class="name-row">
            <td>({{ $overtime->user->name ?? '....................' }})</td> {{-- Isi Nama Pemohon --}}
            <td>(....................)</td> {{-- Isi Nama Pemeriksa (Kosong) --}}
            <td>({{ $overtime->approverAsisten->name ?? '....................' }})</td> {{-- Isi Nama Asisten Manager --}}
            <td>({{ $overtime->approverManager->name ?? '....................' }})</td> {{-- Isi Nama Manager --}}
        </tr>
        <tr class="jabatan-row">
            <td>{{ $overtime->user->jabatan ?? '....................' }}</td> {{-- Isi Jabatan Pemohon --}}
            <td>....................</td> {{-- Isi Jabatan Pemeriksa (Kosong) --}}
            <td>{{ $overtime->approverAsisten->jabatan ?? 'Asisten Manager' }}</td> {{-- Isi Jabatan Asisten Manager --}}
            <td>{{ $overtime->approverManager->jabatan ?? 'Manager' }}</td> {{-- Isi Jabatan Manager --}}
        </tr>
    </table>
    <div class="footer-note">
        <b>Note:</b>
        <p><small>* Surat ini harus terlebih dahulu mendapatkan approval dari Pengawas Pekerjaan/User, apabila tidak
                mendapatkan persetujuan maka pekerjaan
                lembur dianggap tidak sah</small></p>
    </div>
</body>

</html>
