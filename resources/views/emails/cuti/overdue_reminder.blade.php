{{-- resources/views/emails/cuti/overdue_reminder.blade.php --}}
@component('mail::message')
    # Pengingat Persetujuan Cuti

    Yth. Bapak/Ibu {{ $approverName }},

    Berikut adalah daftar pengajuan cuti yang telah menunggu persetujuan Anda selama 7 hari atau lebih:

    {{-- Gunakan Tabel HTML Biasa --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #ddd;">
        <thead>
            <tr style="background-color: #f8f8f8;">
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Tgl Pengajuan</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Nama Pengaju</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Jenis Cuti</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Tanggal Cuti</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Lama (Hari Kerja)</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Lama Overdue</th>
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
                        // Gunakan floor(floatDiffInDays()) untuk memastikan integer
                        $daysOverdue = floor($pendingSince->floatDiffInDays(now()));
                    }
                @endphp
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;">
                        {{ $request->created_at ? $request->created_at->format('d/m/Y') : '-' }}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">{{ $request->user->name ?? 'N/A' }}</td>
                    {{-- Pastikan relasi jenisCuti dimuat oleh command --}}
                    <td style="border: 1px solid #ddd; padding: 8px;">{{ $request->jenisCuti->nama_cuti ?? 'N/A' }}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">
                        {{ $request->mulai_cuti ? $request->mulai_cuti->format('d/m/Y') : '-' }} -
                        {{ $request->selesai_cuti ? $request->selesai_cuti->format('d/m/Y') : '-' }}</td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">{{ $request->lama_cuti ?? '-' }}
                    </td> {{-- lama_cuti sudah hari kerja --}}
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">{{ $daysOverdue }} hari</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"
                        style="border: 1px solid #ddd; padding: 8px; text-align: center; font-style: italic;">Tidak ada
                        pengajuan cuti overdue saat ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    {{-- Akhir Tabel HTML --}}

    Mohon untuk segera meninjau dan memproses pengajuan cuti tersebut melalui tautan di bawah ini:

    @component('mail::button', ['url' => $approvalUrl, 'color' => 'primary'])
        Lihat Pengajuan Cuti
    @endcomponent

    Terima kasih atas perhatian dan kerjasamanya.

    Hormat kami,<br>
    Sistem HR {{ config('app.name') }}
@endcomponent
