<!DOCTYPE html>
    <html>
    <head>
        <title>Update Status Pengajuan Koreksi Absensi</title>
        <style>
            body { font-family: sans-serif; line-height: 1.6; color: #333; }
            .container { padding: 20px; border: 1px solid #e0e0e0; max-width: 600px; margin: 20px auto; }
            .header { font-size: 1.2em; font-weight: bold; margin-bottom: 15px; color: #0d6efd; }
            .status-approved { color: #198754; font-weight: bold; }
            .status-rejected { color: #dc3545; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            th, td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
            th { background-color: #f8f9fa; }
            .footer { margin-top: 20px; font-size: 0.9em; color: #6c757d; }
            a { color: #0d6efd; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">Update Status Pengajuan Koreksi Absensi</div>

            <p>Halo {{ $namaKaryawan }},</p>

            <p>Pengajuan koreksi absensi Anda untuk tanggal <strong>{{ $correctionDate }}</strong> telah diproses dengan status:
                @if ($status === 'Approved')
                    <strong class="status-approved">{{ $status }}</strong>
                @elseif ($status === 'Rejected')
                    <strong class="status-rejected">{{ $status }}</strong>
                @else
                    <strong>{{ $status }}</strong>
                @endif
            </p>

            <table>
                <tr>
                    <th>Tanggal Koreksi</th>
                    <td>{{ $correctionDate }}</td>
                </tr>
                <tr>
                    <th>Jam Masuk Diajukan</th>
                    <td>{{ $requestedClockIn }}</td>
                </tr>
                 <tr>
                    <th>Jam Keluar Diajukan</th>
                    <td>{{ $requestedClockOut }}</td>
                </tr>
                 <tr>
                    <th>Shift Diajukan</th>
                    <td>{{ $requestedShift }}</td>
                </tr>
                <tr>
                    <th>Alasan Pengajuan</th>
                    <td>{{ $reason }}</td>
                </tr>
                 <tr>
                    <th>Diproses Oleh</th>
                    <td>{{ $processorName }}</td>
                </tr>
                 <tr>
                    <th>Tanggal Proses</th>
                    <td>{{ $processedAt }}</td>
                </tr>
                @if ($status === 'Rejected' && $rejectReason)
                <tr>
                    <th>Alasan Penolakan</th>
                    <td><strong>{{ $rejectReason }}</strong></td>
                </tr>
                @endif
            </table>

            @if ($status === 'Approved')
            <p>Data absensi Anda untuk tanggal tersebut telah diperbarui sesuai dengan pengajuan ini.</p>
            @endif

            <p>Anda dapat melihat riwayat pengajuan koreksi Anda melalui tautan berikut:</p>
            <p><a href="{{ $viewUrl }}">Lihat Daftar Koreksi Saya</a></p>

            <div class="footer">
                <p>Ini adalah email otomatis. Mohon untuk tidak membalas email ini.</p>
                <p>Sistem Manajemen Absensi</p>
            </div>
        </div>
    </body>
    </html>
