<?php

namespace App\Mail;

use App\Models\MonthlyTimesheet;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL; // Untuk signed URL jika diperlukan

class MonthlyTimesheetStatusNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public MonthlyTimesheet $timesheet;
    public string $newStatus; // 'approved' atau 'rejected'
    public ?User $processor; // User yang melakukan aksi (Manager/Asisten)
    public string $viewUrl;   // URL untuk melihat detail timesheet

    /**
     * Create a new message instance.
     *
     * @param MonthlyTimesheet $timesheet
     * @param string $newStatus
     * @param User|null $processor
     */
    public function __construct(MonthlyTimesheet $timesheet, string $newStatus, ?User $processor = null)
    {
        $this->timesheet = $timesheet->loadMissing(['user:id,name,email', 'rejecter:id,name', 'approverManager:id,name']); // Eager load
        $this->newStatus = $newStatus;
        $this->processor = $processor;
        // Membuat URL ke halaman detail timesheet
        // Pastikan route 'monthly_timesheets.show' ada dan menerima parameter $timesheet
        $this->viewUrl = URL::route('monthly_timesheets.show', ['timesheet' => $this->timesheet->id]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = '';
        $employeeName = $this->timesheet->user?->name ?? 'Karyawan';
        $period = ($this->timesheet->period_start_date && $this->timesheet->period_end_date)
            ? $this->timesheet->period_start_date->format('M Y')
            : 'Periode Tidak Diketahui';

        if ($this->newStatus === 'approved') {
            $subject = "Selamat! Timesheet Anda untuk periode {$period} telah Disetujui";
        } elseif ($this->newStatus === 'rejected') {
            $subject = "Pemberitahuan: Timesheet Anda untuk periode {$period} Ditolak";
        } else {
            $subject = "Update Status Timesheet Anda untuk periode {$period}"; // Fallback
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
        return new Content(
            view: 'emails.monthly_timesheets.status_notification_html',
            with: [
                'timesheet' => $this->timesheet,
                'newStatus' => $this->newStatus,
                'processor' => $this->processor,
                'viewUrl' => $this->viewUrl,
                'employeeName' => $this->timesheet->user?->name ?? 'Karyawan',
                'periodStart' => $this->timesheet->period_start_date ? $this->timesheet->period_start_date->format('d M Y') : '-',
                'periodEnd' => $this->timesheet->period_end_date ? $this->timesheet->period_end_date->format('d M Y') : '-',
                'rejectionReason' => ($this->newStatus === 'rejected') ? $this->timesheet->notes : null,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
