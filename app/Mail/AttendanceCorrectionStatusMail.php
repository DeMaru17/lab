<?php

namespace App\Mail;

use App\Models\AttendanceCorrection;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendanceCorrectionStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public AttendanceCorrection $correction;
    public User $processor;
    public string $viewUrl; // <-- Properti baru untuk URL

    /**
     * Create a new message instance.
     *
     * @param AttendanceCorrection $correction Objek koreksi yang diproses
     * @param User $processor User yang memproses (approver/rejecter)
     * @param string $viewUrl URL yang sudah digenerate untuk view
     */
    public function __construct(AttendanceCorrection $correction, User $processor, string $viewUrl) // <-- Tambah parameter $viewUrl
    {
        $this->correction = $correction;
        $this->processor = $processor;
        $this->viewUrl = $viewUrl; // <-- Simpan URL
        $this->correction->loadMissing('requester:id,name');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // ... (Logika subject tetap sama) ...
        $subject = '';
        $statusText = ucfirst($this->correction->status); // Approved atau Rejected

        if ($this->correction->status === 'approved') {
            $subject = "Pengajuan Koreksi Absensi Anda ({$this->correction->correction_date->format('d M Y')}) Telah Disetujui";
        } elseif ($this->correction->status === 'rejected') {
            $subject = "Pengajuan Koreksi Absensi Anda ({$this->correction->correction_date->format('d M Y')}) Ditolak";
        } else {
            $subject = "Update Status Koreksi Absensi Anda ({$this->correction->correction_date->format('d M Y')})";
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
            view: 'emails.attendance_corrections.status_notification',
            with: [
                'namaKaryawan' => $this->correction->requester->name ?? 'Karyawan',
                'correctionDate' => $this->correction->correction_date->format('d M Y'),
                'requestedClockIn' => $this->correction->requested_clock_in ? \Carbon\Carbon::parse($this->correction->requested_clock_in)->format('H:i') : '-',
                'requestedClockOut' => $this->correction->requested_clock_out ? \Carbon\Carbon::parse($this->correction->requested_clock_out)->format('H:i') : '-',
                'requestedShift' => $this->correction->requestedShift?->name ?? '-',
                'reason' => $this->correction->reason,
                'status' => ucfirst($this->correction->status),
                'processorName' => $this->processor->name,
                'processedAt' => $this->correction->processed_at?->format('d M Y H:i') ?? '-',
                'rejectReason' => $this->correction->status === 'rejected' ? $this->correction->reject_reason : null,
                'viewUrl' => $this->viewUrl, // <-- Gunakan properti URL
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
