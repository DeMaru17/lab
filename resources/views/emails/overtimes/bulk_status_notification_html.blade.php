{{-- resources/views/emails/overtimes/bulk_status_notification_html.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Persetujuan Pengajuan Lembur</title>
    <style>
        /* Styling dasar email */
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; line-height: 1.5; color: #333; margin: 0; padding: 0; }
        .email-container { padding: 20px; max-width: 600px; margin: auto; border: 1px solid #eee; }
        .greeting { font-size: 11pt; margin-bottom: 15px; }
        .content-block { margin-bottom: 15px; }
        /* Styling tabel */
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #ddd; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 9pt; } /* Kecilkan font tabel */
        th { background-color: #f8f8f8; font-weight: bold; }
        .text-center { text-align: center; }
        /* Styling tombol VML+HTML */
        .button-container { text-align: center; margin: 25px 0; }
        .button { display: inline-block; padding: 10px 25px; font-size: 11pt; font-weight: bold; color: #ffffff !important; background-color: #0d6efd; text-decoration: none; border-radius: 5px; border: 1px solid #0a58ca; }
        /* Footer */
        .footer { margin-top: 20px; font-size: 9pt; color: #777; }
    </style>
</head>
<body>
    <div class="email-container">
        <p class="greeting">Yth. Bapak/Ibu {{ $namaKaryawan }},</p>

        <div class="content-block">
            <p>Dengan senang hati kami memberitahukan bahwa <strong>{{ $requests->count() }} pengajuan lembur</strong> Anda berikut ini telah <strong>disetujui</strong> oleh {{ $approverName }}:</p>
        </div>

        <div class="content-block">
            {{-- Tabel HTML Biasa --}}
            <table>
                <thead>
                    <tr style="background-color: #f8f8f8;">
                        <th>Tanggal Lembur</th>
                        <th>Jam Lembur</th>
                        <th class="text-center">Durasi</th>
                        <th>Uraian Pekerjaan (Singkat)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $request) {{-- $requests berisi collection Overtime --}}
                        <tr>
                            <td>{{ $request->tanggal_lembur ? $request->tanggal_lembur->format('d/m/Y') : '-' }}</td>
                            <td>{{ $request->jam_mulai ? $request->jam_mulai->format('H:i') : '-' }} - {{ $request->jam_selesai ? $request->jam_selesai->format('H:i') : '-' }}</td>
                            <td class="text-center">@if(!is_null($request->durasi_menit)){{ floor($request->durasi_menit / 60) }}j {{ $request->durasi_menit % 60 }}m @else - @endif</td>
                            <td>{{ Str::limit($request->uraian_pekerjaan, 50) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center" style="font-style: italic;">Tidak ada detail lembur yang disertakan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            {{-- Akhir Tabel HTML --}}
        </div>

        <div class="content-block">
            <p>Anda dapat melihat riwayat pengajuan lembur Anda melalui tautan di bawah ini:</p>
        </div>

        {{-- Tombol VML + HTML --}}
        <div class="button-container">
             <a href="{{ $viewUrl }}" target="_blank" class="button"
                style="background-color:#0d6efd;border:1px solid #0a58ca;border-radius:6px;color:#ffffff !important;display:inline-block;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;line-height:44px;text-align:center;text-decoration:none;width:200px;-webkit-text-size-adjust:none;">
                Lihat Riwayat Lembur
             </a>
             </div>

        <p class="footer">Ini adalah email otomatis. Mohon untuk tidak membalas email ini.</p>
        <p class="footer">Hormat kami,<br>
        Sistem HR {{ config('app.name') }}</p>
    </div>
</body>
</html>
