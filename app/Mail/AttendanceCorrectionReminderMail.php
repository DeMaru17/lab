<?php

namespace App\Mail;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // Opsional, bisa diaktifkan nanti
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendanceCorrectionReminderMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Attendance $attendance;
    public User $recipientUser;

    /**
     * Create a new message instance.
     *
     * @param Attendance $attendance Data absensi yang tidak lengkap
     * @param User $recipientUser Pengguna penerima email
     */
    public function __construct(Attendance $attendance, User $recipientUser)
    {
        $this->attendance = $attendance;
        $this->recipientUser = $recipientUser;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pemberitahuan: Data Absensi Tidak Lengkap',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Link ke halaman pengajuan koreksi (buat placeholder dulu)
        // Nanti akan diganti dengan route ke form koreksi jika sudah dibuat
        $correctionUrl = route('attendance_corrections.create', ['attendance_date' => $this->attendance->attendance_date->format('Y-m-d')]);

        return new Content(
            view: 'emails.attendances.correction_reminder', // View Blade yang akan kita buat
            with: [
                'namaKaryawan' => $this->recipientUser->name,
                'tanggalAbsensi' => $this->attendance->attendance_date->format('d M Y'),
                'statusAbsensi' => $this->attendance->attendance_status,
                'catatan' => $this->attendance->notes,
                'correctionUrl' => $correctionUrl,
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
