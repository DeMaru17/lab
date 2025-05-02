<?php

namespace App\Mail;

use App\Models\Cuti; // Import model Cuti
use App\Models\User; // Import model User
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address; // Untuk From address

class LeaveStatusNotificationMail extends Mailable // implements ShouldQueue // Opsional: antrian
{
    use Queueable, SerializesModels;

    public Cuti $cuti; // Objek Cuti yang statusnya berubah
    public string $newStatus; // Status baru ('approved' atau 'rejected')
    public ?User $processor; // User yang melakukan approve/reject

    /**
     * Create a new message instance.
     *
     * @param Cuti $cuti
     * @param string $newStatus ('approved' atau 'rejected')
     * @param User|null $processor User yang memproses (null jika tidak relevan)
     */
    public function __construct(Cuti $cuti, string $newStatus, ?User $processor = null)
    {
        $this->cuti = $cuti;
        $this->newStatus = $newStatus;
        $this->processor = $processor; // Simpan data processor
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = '';
        if ($this->newStatus === 'approved') {
            $subject = 'Pengajuan Cuti Anda Telah Disetujui';
        } elseif ($this->newStatus === 'rejected') {
            $subject = 'Pengajuan Cuti Anda Ditolak';
        } else {
            $subject = 'Update Status Pengajuan Cuti Anda'; // Fallback
        }

        return new Envelope(
            // Atur alamat 'From' jika perlu
            // from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Kita akan gunakan view HTML
        return new Content(
            view: 'emails.cuti.status_notification', // View HTML yang akan dibuat
            with: [
                'namaKaryawan' => $this->cuti->user->name ?? 'Karyawan',
                'jenisCuti' => $this->cuti->jenisCuti->nama_cuti ?? 'N/A',
                'tanggalMulai' => $this->cuti->mulai_cuti->format('d M Y'),
                'tanggalSelesai' => $this->cuti->selesai_cuti->format('d M Y'),
                'durasi' => $this->cuti->lama_cuti,
                'status' => $this->newStatus,
                'alasanReject' => $this->newStatus === 'rejected' ? $this->cuti->notes : null,
                // Kirim jabatan rejecter jika status rejected dan processor ada
                'jabatanRejecter' => ($this->newStatus === 'rejected' && $this->processor) ? $this->processor->jabatan : null,
                // URL ke halaman daftar cuti pengguna
                'viewUrl' => route('cuti.index'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
