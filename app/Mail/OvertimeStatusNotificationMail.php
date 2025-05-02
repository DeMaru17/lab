<?php

namespace App\Mail;

use App\Models\Overtime; // Import model Overtime
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class OvertimeStatusNotificationMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Overtime $overtime; // Objek Overtime
    public string $newStatus;
    public ?User $processor;

    /**
     * Create a new message instance.
     *
     * @param Overtime $overtime
     * @param string $newStatus ('approved' atau 'rejected')
     * @param User|null $processor User yang memproses
     */
    public function __construct(Overtime $overtime, string $newStatus, ?User $processor = null)
    {
        $this->overtime = $overtime;
        $this->newStatus = $newStatus;
        $this->processor = $processor;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = '';
        if ($this->newStatus === 'approved') {
            $subject = 'Pengajuan Lembur Anda Telah Disetujui';
        } elseif ($this->newStatus === 'rejected') {
            $subject = 'Pengajuan Lembur Anda Ditolak';
        } else {
            $subject = 'Update Status Pengajuan Lembur Anda';
        }

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Format durasi
        $durasiFormatted = '-';
        if (!is_null($this->overtime->durasi_menit)) {
            $hours = floor($this->overtime->durasi_menit / 60);
            $minutes = $this->overtime->durasi_menit % 60;
            $durasiFormatted = sprintf('%d jam %02d menit', $hours, $minutes);
        }

        return new Content(
            view: 'emails.overtimes.status_notification', // View HTML yang akan dibuat
            with: [
                'namaKaryawan' => $this->overtime->user->name ?? 'Karyawan',
                'tanggalLembur' => $this->overtime->tanggal_lembur->format('d M Y'),
                'jamMulai' => $this->overtime->jam_mulai->format('H:i'),
                'jamSelesai' => $this->overtime->jam_selesai->format('H:i'),
                'durasi' => $durasiFormatted,
                'uraian' => $this->overtime->uraian_pekerjaan,
                'status' => $this->newStatus,
                'alasanReject' => $this->newStatus === 'rejected' ? $this->overtime->notes : null,
                'jabatanRejecter' => ($this->newStatus === 'rejected' && $this->processor) ? $this->processor->jabatan : null,
                'viewUrl' => route('overtimes.index'), // URL ke daftar lembur
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
