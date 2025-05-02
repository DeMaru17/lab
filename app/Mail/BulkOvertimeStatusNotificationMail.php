<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection; // Import Collection
use App\Models\User;

class BulkOvertimeStatusNotificationMail extends Mailable implements ShouldQueue // Gunakan Queue
{
    use Queueable, SerializesModels;

    public User $pengaju; // User yang mengajukan (penerima email)
    public User $approver; // User yang melakukan approval (manager)
    public Collection $approvedOvertimes; // Koleksi lembur yang disetujui

    /**
     * Create a new message instance.
     *
     * @param Collection $approvedOvertimes Koleksi objek Overtime yang disetujui.
     * @param User $approver Objek user approver (manager).
     * @param User $pengaju Objek user pengaju (penerima).
     */
    public function __construct(Collection $approvedOvertimes, User $approver, User $pengaju)
    {
        $this->approvedOvertimes = $approvedOvertimes;
        $this->approver = $approver;
        $this->pengaju = $pengaju;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $count = $this->approvedOvertimes->count();
        $subject = "{$count} Pengajuan Lembur Anda Telah Disetujui";

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            // Kita akan buat view markdown ini
            view: 'emails.overtimes.bulk_status_notification_html',
            with: [
                'namaKaryawan' => $this->pengaju->name,
                'approverName' => $this->approver->name,
                'requests' => $this->approvedOvertimes, // Kirim collection
                'viewUrl' => route('overtimes.index'), // Link ke daftar lembur
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
