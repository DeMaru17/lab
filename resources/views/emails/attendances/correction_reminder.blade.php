<!DOCTYPE html>
<html>

<head>
    <title>Pemberitahuan Data Absensi Tidak Lengkap</title>
</head>

<body>
    <p>Halo {{ $namaKaryawan }},</p>

    <p>Sistem kami mendeteksi bahwa data absensi Anda pada tanggal <strong>{{ $tanggalAbsensi }}</strong> tercatat tidak
        lengkap.</p>
    <p>Detail:</p>
    <ul>
        <li>Status Tercatat: {{ $statusAbsensi ?? 'N/A' }}</li>
        <li>Catatan Sistem: {{ $catatan ?? 'Tidak ada catatan tambahan.' }}</li>
    </ul>

    <p>Mohon untuk segera melakukan pengajuan koreksi absensi agar data kehadiran Anda akurat.</p>

    {{-- Jika halaman koreksi sudah ada, gunakan link ini --}}
    <p><a href="{{ $correctionUrl }}">Ajukan Koreksi Sekarang</a></p>

    <p>Jika Anda merasa ini adalah kesalahan atau memiliki pertanyaan, silakan hubungi Admin.</p>

    <p>Terima kasih atas perhatian dan kerjasamanya.</p>
    <br>
    <p>Salam,</p>
    <p>Sistem Manajemen Absensi</p>
</body>

</html>
