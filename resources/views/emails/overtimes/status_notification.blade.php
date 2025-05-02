{{-- resources/views/emails/overtimes/status_notification.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Update Status Pengajuan Lembur</title>
    <style>
        /* Basic Email Styling */
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; line-height: 1.5; color: #333; margin: 0; padding: 0; }
        .email-container { padding: 20px; }
        .greeting { font-size: 11pt; margin-bottom: 15px; }
        .content-block { margin-bottom: 15px; }
        .status-info { margin-top: 15px; padding: 10px; border: 1px solid #eee; background-color: #f9f9f9; }
        .status-info strong { font-size: 1.05em; }
        .status-approved { color: #198754; } /* Green */
        .status-rejected { color: #dc3545; } /* Red */
        .reject-reason { margin-top: 5px; font-style: italic; color: #6c757d; }
        .detail-table { width: 100%; max-width: 500px; border-collapse: collapse; margin-bottom: 15px; }
        .detail-table td { padding: 5px 0; vertical-align: top; }
        .detail-table td.label { font-weight: bold; width: 150px; } /* Fixed width for label */
        .detail-table td.separator { width: 10px; text-align: center; }
        /* Button Styling (Fallback HTML) */
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
            @if ($status === 'approved')
                <p>Dengan senang hati kami memberitahukan bahwa pengajuan lembur Anda telah <strong>disetujui</strong>.</p>
            @elseif ($status === 'rejected')
                <p>Dengan berat hati kami memberitahukan bahwa pengajuan lembur Anda telah <strong>ditolak</strong>.</p>
            @else
                <p>Ada pembaruan status untuk pengajuan lembur Anda.</p> {{-- Fallback --}}
            @endif
        </div>

        <div class="content-block">
            <p>Berikut adalah rincian pengajuan lembur Anda:</p>
            <table class="detail-table">
                <tr>
                    <td class="label">Tanggal Lembur</td>
                    <td class="separator">:</td>
                    <td>{{ $tanggalLembur ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Jam Lembur</td>
                    <td class="separator">:</td>
                    <td>{{ $jamMulai ?? '-' }} s/d {{ $jamSelesai ?? '-' }}</td>
                </tr>
                 <tr>
                    <td class="label">Durasi</td>
                    <td class="separator">:</td>
                    <td>{{ $durasi ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Uraian Pekerjaan</td>
                    <td class="separator">:</td>
                    <td>{{ $uraian ?? '-' }}</td>
                </tr>
            </table>
        </div>

        {{-- Tampilkan Status dan Alasan Penolakan jika ditolak --}}
        <div class="status-info">
            @if ($status === 'approved')
                Status: <strong class="status-approved">Disetujui</strong>
            @elseif ($status === 'rejected')
                Status: <strong class="status-rejected">Ditolak</strong>
                @if ($alasanReject)
                    <div class="reject-reason">
                        <strong>Alasan Penolakan:</strong> {{ $alasanReject }}
                        @if($jabatanRejecter)
                            <br>(Oleh: {{ Str::title($jabatanRejecter) }})
                        @endif
                    </div>
                @endif
            @else
                 Status: {{ Str::title($status) }}
            @endif
        </div>

        {{-- Tombol untuk melihat detail (opsional) --}}
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
