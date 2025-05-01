<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use App\Models\User; // Import User model

// Anda bisa implement ShouldQueue jika ingin email dikirim via antrian
class OverdueOvertimeReminderMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;

    // Properti publik untuk data email
    public User $approver;
    public Collection $overdueRequests; // Collection berisi objek Overtime

    /**
     * Create a new message instance.
     *
     * @param Collection $overdueRequests Koleksi objek Overtime yang overdue.
     * @param User $approver Objek user approver penerima email.
     */
    public function __construct(Collection $overdueRequests, User $approver)
    {
        $this->overdueRequests = $overdueRequests;
        $this->approver = $approver;
    }

    /**
     * Get the message envelope.
     * Mendefinisikan subjek email.
     */
    public function envelope(): Envelope
    {
        $requestCount = $this->overdueRequests->count();
        // Sesuaikan subjek untuk lembur
        $subject = "Pengingat: {$requestCount} Pengajuan Lembur Menunggu Persetujuan Anda";

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     * Menentukan view dan data untuk email.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.overtimes.overdue_reminder_html', // Path ke view email lembur
            with: [
                'approverName' => $this->approver->name,
                'requests' => $this->overdueRequests,
                'approvalUrl' => $this->getApprovalUrl(), // URL halaman approval
            ],
        );
    }

    /**
     * Helper untuk menentukan URL halaman approval lembur.
     */
    protected function getApprovalUrl(): string
    {
        // Gunakan nama route yang sama seperti di Cuti
        if ($this->approver->jabatan === 'manager') {
            // Pastikan nama route ini benar
            return route('overtimes.approval.manager.list');
        } else {
            // Pastikan nama route ini benar
            return route('overtimes.approval.asisten.list');
        }
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return []; // Tidak ada attachment
    }
}
