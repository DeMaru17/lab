<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // Implement jika ingin antri
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use App\Models\User;

// class OverdueLeaveReminderMail extends Mailable implements ShouldQueue // Implementasi antrian (opsional)
class OverdueLeaveReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $approver;
    public Collection $overdueRequests;

    /**
     * Create a new message instance.
     */
    public function __construct(Collection $overdueRequests, User $approver)
    {
        $this->overdueRequests = $overdueRequests;
        $this->approver = $approver;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $requestCount = $this->overdueRequests->count();
        $subject = "Pengingat: {$requestCount} Pengajuan Cuti Menunggu Persetujuan Anda";

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
            markdown: 'emails.cuti.overdue_reminder',
            with: [
                'approverName' => $this->approver->name,
                'requests' => $this->overdueRequests,
                'approvalUrl' => $this->getApprovalUrl(),
            ],
        );
    }

    /**
     * Helper untuk menentukan URL halaman approval.
     */
    protected function getApprovalUrl(): string
    {
        if ($this->approver->jabatan === 'manager') {
            return route('cuti.approval.manager.list');
        } else {
            return route('cuti.approval.asisten.list');
        }
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
