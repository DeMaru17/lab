<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemberitahuan Status Timesheet</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border: 1px solid #dddddd;
            border-radius: 5px;
            overflow: hidden;
        }

        .email-header {
            background-color: #007bff;
            /* Biru primer, sesuaikan */
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }

        .email-header h1 {
            margin: 0;
            font-size: 24px;
        }

        .email-body {
            padding: 20px;
        }

        .email-body p {
            margin-bottom: 15px;
        }

        .status-approved {
            color: #28a745;
            /* Hijau untuk approved */
            font-weight: bold;
        }

        .status-rejected {
            color: #dc3545;
            /* Merah untuk rejected */
            font-weight: bold;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            font-size: 16px;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
        }

        .button-primary {
            background-color: #007bff;
            /* Biru */
        }

        .button-success {
            background-color: #28a745;
            /* Hijau */
        }

        .button-error {
            background-color: #dc3545;
            /* Merah */
        }

        .email-footer {
            background-color: #f4f4f4;
            color: #777777;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            border-top: 1px solid #dddddd;
        }

        .highlight {
            font-weight: bold;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .details-table th,
        .details-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .details-table th {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Pemberitahuan Status Timesheet</h1>
        </div>
        <div class="email-body">
            <p>Halo <span class="highlight">{{ $employeeName }}</span>,</p>

            <p>Berikut adalah pembaruan status untuk timesheet bulanan Anda:</p>

            <table class="details-table">
                <tr>
                    <th style="width:30%;">Periode</th>
                    <td>{{ $periodStart }} - {{ $periodEnd }}</td>
                </tr>
                <tr>
                    <th>Status Baru</th>
                    <td>
                        @if ($newStatus === 'approved')
                            <span class="status-approved">DISETUJUI</span>
                        @elseif ($newStatus === 'rejected')
                            <span class="status-rejected">DITOLAK</span>
                        @else
                            <span class="highlight">{{ Str::title($newStatus) }}</span>
                        @endif
                    </td>
                </tr>
                @if ($processor)
                    <tr>
                        <th>Diproses Oleh</th>
                        <td>{{ $processor->name }} ({{ $processor->jabatan }})</td>
                    </tr>
                @endif
                @if ($newStatus === 'approved' && $timesheet->approved_at_manager)
                    <tr>
                        <th>Tanggal Disetujui</th>
                        <td>{{ $timesheet->approved_at_manager->format('d M Y H:i') }}</td>
                    </tr>
                @elseif ($newStatus === 'rejected' && $timesheet->rejected_at)
                    <tr>
                        <th>Tanggal Ditolak</th>
                        <td>{{ $timesheet->rejected_at->format('d M Y H:i') }}</td>
                    </tr>
                @endif
                @if ($newStatus === 'rejected' && $rejectionReason)
                    <tr>
                        <th>Alasan Penolakan</th>
                        <td>{{ $rejectionReason }}</td>
                    </tr>
                @endif
            </table>

            @if ($newStatus === 'rejected')
                <p>Silakan periksa detailnya dan ajukan koreksi absensi jika diperlukan melalui tautan di bawah ini.</p>
            @endif

            <p style="text-align: center; margin-top: 25px; margin-bottom: 25px;">
                <a href="{{ $viewUrl }}"
                    class="button
                       @if ($newStatus === 'approved') button-success
                       @elseif($newStatus === 'rejected') button-error
                       @else button-primary @endif">
                    Lihat Detail Timesheet
                </a>
            </p>

            <p>Jika Anda memiliki pertanyaan, silakan hubungi bagian HR atau atasan Anda.</p>

            <p>Terima kasih,<br>
                Tim HR {{ config('app.name') }}</p>
        </div>
        <div class="email-footer">
            Ini adalah email otomatis. Mohon untuk tidak membalas email ini.
            <br>&copy; {{ date('Y') }} {{ config('app.name') }}. Semua hak cipta dilindungi.
        </div>
    </div>
</body>

</html>
