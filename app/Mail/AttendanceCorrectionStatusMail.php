<?php

namespace App\Mail;

use App\Models\AttendanceCorrection;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // Direkomendasikan untuk diaktifkan agar email dikirim via antrian.
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Class AttendanceCorrectionStatusMail
 *
 * Mailable ini dikirim kepada karyawan untuk memberitahukan status terbaru
 * dari pengajuan koreksi absensi mereka, apakah disetujui atau ditolak.
 * Email berisi detail koreksi dan informasi dari pemroses (approver/rejecter).
 *
 * @package App\Mail
 */
class AttendanceCorrectionStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Instance model AttendanceCorrection yang statusnya berubah.
     *
     * @var \App\Models\AttendanceCorrection
     */
    public AttendanceCorrection $correction;

    /**
     * Instance model User yang memproses pengajuan (approver atau rejecter).
     *
     * @var \App\Models\User
     */
    public User $processor;

    /**
     * URL yang akan disertakan dalam email untuk melihat detail pengajuan.
     *
     * @var string
     */
    public string $viewUrl;

    /**
     * Membuat instance message baru.
     *
     * @param \App\Models\AttendanceCorrection $correction Objek koreksi yang telah diproses.
     * @param \App\Models\User $processor Pengguna (Asisten Manager) yang memproses pengajuan.
     * @param string $viewUrl URL yang sudah digenerate untuk tombol "Lihat Detail" di email.
     * @return void
     */
    public function __construct(AttendanceCorrection $correction, User $processor, string $viewUrl)
    {
        $this->correction = $correction;
        $this->processor = $processor;
        $this->viewUrl = $viewUrl;
        // Memuat relasi 'requester' jika belum ada untuk memastikan nama pengaju tersedia di email.
        $this->correction->loadMissing('requester:id,name');
    }

    /**
     * Mendapatkan envelope (amplop) pesan email.
     * Mendefinisikan subjek email berdasarkan status koreksi.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        $subject = '';
        // Menggunakan ucfirst untuk membuat huruf pertama status menjadi kapital (misal: "Approved").
        $statusText = ucfirst($this->correction->status);
        $correctionDateFormatted = $this->correction->correction_date->format('d M Y');

        if ($this->correction->status === 'approved') {
            $subject = "Pengajuan Koreksi Absensi Anda ({$correctionDateFormatted}) Telah Disetujui";
        } elseif ($this->correction->status === 'rejected') {
            $subject = "Pengajuan Koreksi Absensi Anda ({$correctionDateFormatted}) Ditolak";
        } else {
            // Fallback jika status tidak dikenali (seharusnya tidak terjadi).
            $subject = "Update Status Koreksi Absensi Anda ({$correctionDateFormatted})";
        }

        return new Envelope(
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
        return new Content(
            view: 'emails.attendance_corrections.status_notification', // Path ke view Blade.
            with: [
                'namaKaryawan' => $this->correction->requester->name ?? 'Karyawan',
                'correctionDate' => $this->correction->correction_date->format('d M Y'),
                // Format jam jika ada, jika tidak tampilkan '-'.
                'requestedClockIn' => $this->correction->requested_clock_in ? \Carbon\Carbon::parse($this->correction->requested_clock_in)->format('H:i') : '-',
                'requestedClockOut' => $this->correction->requested_clock_out ? \Carbon\Carbon::parse($this->correction->requested_clock_out)->format('H:i') : '-',
                'requestedShift' => $this->correction->requestedShift?->name ?? '-', // Nama shift yang diajukan.
                'reason' => $this->correction->reason, // Alasan pengajuan koreksi.
                'status' => ucfirst($this->correction->status), // Status akhir (Approved/Rejected).
                'processorName' => $this->processor->name, // Nama Asisten Manager yang memproses.
                'processedAt' => $this->correction->processed_at?->format('d M Y H:i') ?? '-', // Waktu diproses.
                'rejectReason' => $this->correction->status === 'rejected' ? $this->correction->reject_reason : null, // Alasan penolakan.
                'viewUrl' => $this->viewUrl, // URL untuk tombol "Lihat Detail Pengajuan".
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
