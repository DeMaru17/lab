{{-- resources/views/emails/overtimes/overdue_reminder.blade.php --}}
@component('mail::message')
    # Pengingat Persetujuan Lembur

    Yth. Bapak/Ibu {{ $approverName }},

    Berikut adalah daftar pengajuan lembur yang telah menunggu persetujuan Anda selama 7 hari atau lebih:

    {{-- Gunakan Tabel HTML Biasa --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #ddd;">
        <thead>
            <tr style="background-color: #f8f8f8;">
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Tgl Pengajuan</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Nama Pengaju</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Tanggal Lembur</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Jam Lembur</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Durasi</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Lama Overdue</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($requests as $request)
                {{-- Gunakan forelse untuk handle jika kosong --}}
                @php
                    // Hitung lama overdue (sama seperti sebelumnya)
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
                    <td style="border: 1px solid #ddd; padding: 8px;">
                        {{ $request->created_at ? $request->created_at->format('d/m/Y') : '-' }}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">{{ $request->user->name ?? 'N/A' }}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">
                        {{ $request->tanggal_lembur ? $request->tanggal_lembur->format('d/m/Y') : '-' }}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">
                        {{ $request->jam_mulai ? $request->jam_mulai->format('H:i') : '-' }} -
                        {{ $request->jam_selesai ? $request->jam_selesai->format('H:i') : '-' }}</td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">
                        @if (!is_null($request->durasi_menit))
                            {{ floor($request->durasi_menit / 60) }}j {{ $request->durasi_menit % 60 }}m
                        @else
                            -
                        @endif
                    </td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">{{ $daysOverdue }} hari</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"
                        style="border: 1px solid #ddd; padding: 8px; text-align: center; font-style: italic;">Tidak ada
                        pengajuan lembur overdue saat ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    {{-- Akhir Tabel HTML --}}

    Mohon untuk segera meninjau dan memproses pengajuan lembur tersebut melalui tautan di bawah ini:

    @component('mail::button', ['url' => $approvalUrl, 'color' => 'primary'])
        Lihat Pengajuan Lembur
    @endcomponent

    Terima kasih atas perhatian dan kerjasamanya.

    Hormat kami,<br>
    Sistem HR {{ config('app.name') }}
@endcomponent
