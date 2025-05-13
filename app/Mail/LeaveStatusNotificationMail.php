<?php

namespace App\Mail;

use App\Models\Cuti; // Import model Cuti.
use App\Models\User; // Import model User.
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // Opsional, bisa diaktifkan jika ingin email dikirim via antrian.
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address; // Untuk mengatur alamat 'From' jika diperlukan.

/**
 * Class LeaveStatusNotificationMail
 *
 * Mailable ini dikirim kepada karyawan untuk memberitahukan status terbaru
 * dari pengajuan cuti mereka, apakah disetujui atau ditolak.
 * Email berisi detail pengajuan cuti dan informasi dari pemroses (approver/rejecter).
 *
 * @package App\Mail
 */
class LeaveStatusNotificationMail extends Mailable // implements ShouldQueue // Opsional: aktifkan untuk antrian.
{
    use Queueable, SerializesModels;

    /**
     * Instance model Cuti yang statusnya berubah.
     *
     * @var \App\Models\Cuti
     */
    public Cuti $cuti;

    /**
     * Status baru dari pengajuan cuti ('approved' atau 'rejected').
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
     * @param \App\Models\Cuti $cuti Objek Cuti yang telah diproses.
     * @param string $newStatus Status baru dari pengajuan ('approved' atau 'rejected').
     * @param \App\Models\User|null $processor Pengguna (Approver/Rejecter) yang memproses.
     * @return void
     */
    public function __construct(Cuti $cuti, string $newStatus, ?User $processor = null)
    {
        $this->cuti = $cuti;
        $this->newStatus = $newStatus;
        $this->processor = $processor; // Menyimpan data pemroses.
    }

    /**
     * Mendapatkan envelope (amplop) pesan email.
     * Mendefinisikan subjek email dan alamat pengirim (jika perlu di-override).
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        $subject = '';
        if ($this->newStatus === 'approved') {
            $subject = 'Pengajuan Cuti Anda Telah Disetujui';
        } elseif ($this->newStatus === 'rejected') {
            $subject = 'Pengajuan Cuti Anda Ditolak';
        } else {
            // Fallback jika status tidak dikenali.
            $subject = 'Update Status Pengajuan Cuti Anda';
        }

        return new Envelope(
            // Opsi untuk mengatur alamat 'From' secara eksplisit jika berbeda dari konfigurasi default.
            // from: new Address(config('mail.from.address'), config('mail.from.name')),
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
        // Menggunakan view Blade untuk konten email.
        return new Content(
            view: 'emails.cuti.status_notification', // Path ke view Blade (misal: resources/views/emails/cuti/status_notification.blade.php).
            with: [
                'namaKaryawan' => $this->cuti->user->name ?? 'Karyawan', // Nama karyawan pengaju.
                'jenisCuti' => $this->cuti->jenisCuti->nama_cuti ?? 'N/A', // Nama jenis cuti.
                'tanggalMulai' => $this->cuti->mulai_cuti->format('d M Y'), // Tanggal mulai cuti.
                'tanggalSelesai' => $this->cuti->selesai_cuti->format('d M Y'), // Tanggal selesai cuti.
                'durasi' => $this->cuti->lama_cuti, // Durasi cuti dalam hari kerja.
                'status' => $this->newStatus, // Status baru ('approved' atau 'rejected').
                'alasanReject' => $this->newStatus === 'rejected' ? $this->cuti->notes : null, // Alasan penolakan.
                // Mengirim jabatan pemroses (rejecter) jika status 'rejected' dan data pemroses ada.
                'jabatanRejecter' => ($this->newStatus === 'rejected' && $this->processor) ? $this->processor->jabatan : null,
                // URL untuk tombol "Lihat Pengajuan Cuti" di email.
                'viewUrl' => route('cuti.index'),
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
