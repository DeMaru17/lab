{{-- resources/views/emails/cuti/overdue_reminder_html.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengingat Persetujuan Cuti</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f8f8f8;
        }

        .text-center {
            text-align: center;
        }


        .italic {
            font-style: italic;
        }
    </style>
</head>

<body>
    <p>Yth. Bapak/Ibu {{ $approverName }},</p>

    <p>Berikut adalah daftar pengajuan cuti yang telah menunggu persetujuan Anda selama 7 hari atau lebih:</p>

    {{-- Tabel HTML Biasa untuk Cuti --}}
    <table>
        <thead>
            <tr style="background-color: #f8f8f8;">
                <th>Tgl Pengajuan</th>
                <th>Nama Pengaju</th>
                <th>Jenis Cuti</th>
                <th>Tanggal Cuti</th>
                <th class="text-center">Lama (Hari Kerja)</th>
                <th class="text-center">Lama Overdue</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($requests as $request)
                {{-- $requests berisi collection Cuti --}}
                @php
                    // Hitung lama overdue
                    $pendingSince = null;
                    if ($request->status == 'pending' && $request->created_at) {
                        $pendingSince = $request->created_at;
                    } elseif ($request->status == 'pending_manager_approval' && $request->approved_at_asisten) {
                        $pendingSince = $request->approved_at_asisten;
                    }
                    $daysOverdue = 'N/A';
                    if ($pendingSince) {
                        $daysOverdue = floor($pendingSince->floatDiffInDays(now()));
                    }
                @endphp
                <tr>
                    <td>{{ $request->created_at ? $request->created_at->format('d/m/Y') : '-' }}</td>
                    <td>{{ $request->user->name ?? 'N/A' }}</td>
                    {{-- Pastikan relasi jenisCuti dimuat --}}
                    <td>{{ $request->jenisCuti->nama_cuti ?? 'N/A' }}</td>
                    <td>{{ $request->mulai_cuti ? $request->mulai_cuti->format('d/m/Y') : '-' }} -
                        {{ $request->selesai_cuti ? $request->selesai_cuti->format('d/m/Y') : '-' }}</td>
                    <td class="text-center">{{ $request->lama_cuti ?? '-' }}</td>
                    <td class="text-center">{{ $daysOverdue }} hari</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center italic">Tidak ada pengajuan cuti overdue saat ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    {{-- Akhir Tabel HTML --}}

    <p>Mohon untuk segera meninjau dan memproses pengajuan cuti tersebut melalui tautan di bawah ini:</p>

    <p style="text-align: center; margin: 20px 0;">
        <!--[if mso]>
          <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $approvalUrl }}" style="height:44px;v-text-anchor:middle;width:200px;" arcsize="10%" strokecolor="#0a58ca" fillcolor="#0d6efd">
            <w:anchorlock/>
            <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;">Lihat Pengajuan Lembur</center>
          </v:roundrect>
        <![endif]-->
        <!--[if !mso]><!-- -->
        <a href="{{ $approvalUrl }}" target="_blank"
            style="background-color:#0d6efd;border:1px solid #0a58ca;border-radius:6px;color:#ffffff;display:inline-block;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;line-height:44px;text-align:center;text-decoration:none;width:200px;-webkit-text-size-adjust:none;">
            Lihat Pengajuan Cuti
        </a>
        <!--<![endif]-->
    </p>

    <p>Terima kasih atas perhatian dan kerjasamanya.</p>

    <p>Hormat kami,<br>
        Sistem HR {{ config('app.name') }}</p>

</body>

</html>
