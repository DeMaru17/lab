<?php

namespace App\Mail;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // Opsional, bisa diaktifkan jika ingin email dikirim via antrian.
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Class AttendanceCorrectionReminderMail
 *
 * Mailable ini dikirim kepada karyawan untuk mengingatkan mereka
 * agar melengkapi data absensi yang tidak lengkap (misalnya, hanya check-in atau hanya check-out).
 * Email ini berisi link ke halaman pengajuan koreksi absensi.
 *
 * Implementasi `ShouldQueue` (opsional) akan membuat email ini dikirim melalui queue
 * untuk meningkatkan performa aplikasi, terutama jika pengiriman email memakan waktu.
 *
 * @package App\Mail
 */
class AttendanceCorrectionReminderMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Instance model Attendance yang datanya tidak lengkap.
     *
     * @var \App\Models\Attendance
     */
    public Attendance $attendance;

    /**
     * Instance model User (karyawan) penerima email pengingat.
     *
     * @var \App\Models\User
     */
    public User $recipientUser;

    /**
     * Membuat instance message baru.
     *
     * @param \App\Models\Attendance $attendance Data absensi yang tidak lengkap.
     * @param \App\Models\User $recipientUser Pengguna (karyawan) penerima email ini.
     * @return void
     */
    public function __construct(Attendance $attendance, User $recipientUser)
    {
        $this->attendance = $attendance;
        $this->recipientUser = $recipientUser;
    }

    /**
     * Mendapatkan envelope (amplop) pesan email.
     * Mendefinisikan subjek email.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pemberitahuan: Data Absensi Tidak Lengkap',
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
        // Membuat URL ke halaman pengajuan koreksi absensi.
        // Parameter 'attendance_date' akan digunakan untuk pra-mengisi tanggal di form koreksi.
        $correctionUrl = route('attendance_corrections.create', ['attendance_date' => $this->attendance->attendance_date->format('Y-m-d')]);

        return new Content(
            view: 'emails.attendances.correction_reminder', // Path ke view Blade untuk email ini.
            with: [
                'namaKaryawan' => $this->recipientUser->name,
                'tanggalAbsensi' => $this->attendance->attendance_date->format('d M Y'),
                'statusAbsensi' => $this->attendance->attendance_status, // Status saat ini (misal: Alpha)
                'catatan' => $this->attendance->notes, // Catatan dari sistem (misal: "Tidak ada Check-out")
                'correctionUrl' => $correctionUrl, // URL untuk link "Ajukan Koreksi"
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
        return []; // Tidak ada lampiran.
    }
}
