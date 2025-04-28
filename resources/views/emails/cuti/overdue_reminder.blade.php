{{-- resources/views/emails/cuti/overdue_reminder.blade.php --}}
@component('mail::message')
# Pengingat Persetujuan Cuti

Yth. Bapak/Ibu {{ $approverName }},

Berikut adalah daftar pengajuan cuti yang telah menunggu persetujuan Anda selama 7 hari atau lebih:

@component('mail::table')
| Tgl Pengajuan | Nama Pengaju        | Jenis Cuti                     | Tanggal Cuti                     | Lama Overdue |
| :------------ | :------------------ | :----------------------------- | :------------------------------- | :----------- |
@foreach ($requests as $request)
@php
    // Hitung lama overdue
    $pendingSince = null;
    if ($request->status == 'pending') {
        $pendingSince = $request->created_at;
    } elseif ($request->status == 'pending_manager_approval' && $request->approved_at_asisten) {
        $pendingSince = $request->approved_at_asisten;
    }

    // Pastikan $daysOverdue adalah integer
    $daysOverdue = 'N/A'; // Default jika tidak bisa dihitung
    if ($pendingSince) {
        // diffInDays sudah mengembalikan integer (jumlah hari penuh)
        // Kita bisa pastikan lagi dengan casting (int) atau floor() jika perlu,
        // tapi biasanya diffInDays() sudah cukup.
        // $daysOverdue = $pendingSince->diffInDays(now());
        // Jika ingin pembulatan ke bawah (misal 7.9 hari jadi 7 hari):
        $daysOverdue = floor($pendingSince->floatDiffInDays(now()));
    }
@endphp
| {{ $request->created_at->format('d/m/Y') }} | {{ $request->user->name ?? 'N/A' }} | {{ $request->jenisCuti->nama_cuti ?? 'N/A' }} | {{ $request->mulai_cuti->format('d/m/Y') }} - {{ $request->selesai_cuti->format('d/m/Y') }} | {{ $daysOverdue }} hari |
@endforeach
@endcomponent

Mohon untuk segera meninjau dan memproses pengajuan cuti tersebut melalui tautan di bawah ini:

@component('mail::button', ['url' => $approvalUrl, 'color' => 'primary'])
Lihat Pengajuan Cuti
@endcomponent

Terima kasih atas perhatian dan kerjasamanya.

Hormat kami,<br>
Sistem HR {{ config('app.name') }}
@endcomponent
