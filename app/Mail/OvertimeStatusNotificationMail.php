<?php

namespace App\Mail;

use App\Models\Overtime; // Import model Overtime.
use App\Models\User;     // Import model User.
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // Opsional, bisa diaktifkan jika ingin email dikirim via antrian.
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address; // Untuk mengatur alamat 'From' jika diperlukan.

/**
 * Class OvertimeStatusNotificationMail
 *
 * Mailable ini dikirim kepada karyawan untuk memberitahukan status terbaru
 * dari pengajuan lembur mereka, apakah disetujui atau ditolak.
 * Email berisi detail pengajuan lembur dan informasi dari pemroses (approver/rejecter).
 *
 * @package App\Mail
 */
class OvertimeStatusNotificationMail extends Mailable // implements ShouldQueue // Opsional: aktifkan untuk antrian.
{
    use Queueable, SerializesModels;

    /**
     * Instance model Overtime yang statusnya berubah.
     *
     * @var \App\Models\Overtime
     */
    public Overtime $overtime;

    /**
     * Status baru dari pengajuan lembur ('approved' atau 'rejected').
     *
     * @var string
     */
    public string $newStatus;

    /**
     * Instance model User yang memproses pengajuan (approver atau rejecter).
     * Bisa null jika tidak relevan (misalnya, notifikasi sistem).
     *
     * @var \App\Models\User|null
     */
    public ?User $processor;

    /**
     * Membuat instance message baru.
     *
     * @param \App\Models\Overtime $overtime Objek Overtime yang telah diproses.
     * @param string $newStatus Status baru dari pengajuan ('approved' atau 'rejected').
     * @param \App\Models\User|null $processor Pengguna (Approver/Rejecter) yang memproses.
     * @return void
     */
    public function __construct(Overtime $overtime, string $newStatus, ?User $processor = null)
    {
        $this->overtime = $overtime;
        $this->newStatus = $newStatus;
        $this->processor = $processor; // Menyimpan data pemroses.
    }

    /**
     * Mendapatkan envelope (amplop) pesan email.
     * Mendefinisikan subjek email.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        $subject = '';
        if ($this->newStatus === 'approved') {
            $subject = 'Pengajuan Lembur Anda Telah Disetujui';
        } elseif ($this->newStatus === 'rejected') {
            $subject = 'Pengajuan Lembur Anda Ditolak';
        } else {
            // Fallback jika status tidak dikenali.
            $subject = 'Update Status Pengajuan Lembur Anda';
        }

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Mendapatkan definisi konten pesan email.
     * Menentukan view Blade yang akan digunakan untuk merender konten email HTML
     * dan data yang akan dikirimkan ke view tersebut.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content(): Content
    {
        // Menyiapkan format durasi lembur untuk ditampilkan di email.
        $durasiFormatted = '-';
        if (!is_null($this->overtime->durasi_menit)) {
            $hours = floor($this->overtime->durasi_menit / 60);
            $minutes = $this->overtime->durasi_menit % 60;
            $durasiFormatted = sprintf('%d jam %02d menit', $hours, $minutes);
        }

        return new Content(
            view: 'emails.overtimes.status_notification', // Path ke view Blade untuk email ini.
            with: [
                'namaKaryawan' => $this->overtime->user->name ?? 'Karyawan',
                'tanggalLembur' => $this->overtime->tanggal_lembur->format('d M Y'),
                'jamMulai' => $this->overtime->jam_mulai->format('H:i'), // Asumsi jam_mulai adalah objek Carbon.
                'jamSelesai' => $this->overtime->jam_selesai->format('H:i'), // Asumsi jam_selesai adalah objek Carbon.
                'durasi' => $durasiFormatted, // Durasi yang sudah diformat.
                'uraian' => $this->overtime->uraian_pekerjaan,
                'status' => $this->newStatus, // Status baru ('approved' atau 'rejected').
                'alasanReject' => $this->newStatus === 'rejected' ? $this->overtime->notes : null, // Alasan penolakan.
                // Mengirim jabatan pemroses (rejecter) jika status 'rejected' dan data pemroses ada.
                'jabatanRejecter' => ($this->newStatus === 'rejected' && $this->processor) ? $this->processor->jabatan : null,
                // URL untuk tombol "Lihat Daftar Lembur" di email.
                'viewUrl' => route('overtimes.index'),
            ],
        );
    }

    /**
     * Mendapatkan lampiran (attachments) untuk pesan email.
     * Mailable ini tidak memiliki lampiran.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
