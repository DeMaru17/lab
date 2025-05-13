<?php

namespace App\Mail;

use App\Models\MonthlyTimesheet;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // Direkomendasikan untuk diaktifkan.
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL; // Untuk membuat URL, misalnya signed URL jika diperlukan.

/**
 * Class MonthlyTimesheetStatusNotification
 *
 * Mailable ini dikirim kepada karyawan untuk memberitahukan status terbaru
 * dari rekap timesheet bulanan mereka, apakah telah disetujui atau ditolak.
 * Email berisi detail timesheet dan informasi dari pemroses.
 *
 * @package App\Mail
 */
class MonthlyTimesheetStatusNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Instance model MonthlyTimesheet yang statusnya berubah.
     *
     * @var \App\Models\MonthlyTimesheet
     */
    public MonthlyTimesheet $timesheet;

    /**
     * Status baru dari timesheet ('approved' atau 'rejected').
     *
     * @var string
     */
    public string $newStatus;

    /**
     * Instance model User yang memproses timesheet (Asisten Manager atau Manager).
     * Bisa null jika notifikasi berasal dari sistem tanpa pemroses spesifik.
     *
     * @var \App\Models\User|null
     */
    public ?User $processor;

    /**
     * URL yang akan disertakan dalam email untuk melihat detail timesheet.
     *
     * @var string
     */
    public string $viewUrl;

    /**
     * Membuat instance message baru.
     *
     * @param \App\Models\MonthlyTimesheet $timesheet Objek timesheet yang telah diproses.
     * @param string $newStatus Status baru dari timesheet ('approved' atau 'rejected').
     * @param \App\Models\User|null $processor Pengguna (Asisten/Manager) yang memproses.
     * @return void
     */
    public function __construct(MonthlyTimesheet $timesheet, string $newStatus, ?User $processor = null)
    {
        // Memuat relasi yang mungkin dibutuhkan di email untuk menghindari N+1 query problem.
        $this->timesheet = $timesheet->loadMissing(['user:id,name,email', 'rejecter:id,name', 'approverManager:id,name']);
        $this->newStatus = $newStatus;
        $this->processor = $processor;
        // Membuat URL ke halaman detail timesheet.
        // Pastikan route 'monthly_timesheets.show' sudah terdefinisi dan menerima parameter $timesheet (atau ID nya).
        $this->viewUrl = URL::route('monthly_timesheets.show', ['timesheet' => $this->timesheet->id]);
    }

    /**
     * Mendapatkan envelope (amplop) pesan email.
     * Mendefinisikan subjek email berdasarkan status timesheet.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        $subject = '';
        $employeeName = $this->timesheet->user?->name ?? 'Karyawan';
        // Membuat format periode timesheet untuk subjek email.
        $period = ($this->timesheet->period_start_date && $this->timesheet->period_end_date)
            ? $this->timesheet->period_start_date->format('M Y') // Contoh: "Mei 2024"
            : 'Periode Tidak Diketahui';

        if ($this->newStatus === 'approved') {
            $subject = "Selamat! Timesheet Anda untuk periode {$period} telah Disetujui";
        } elseif ($this->newStatus === 'rejected') {
            $subject = "Pemberitahuan: Timesheet Anda untuk periode {$period} Ditolak";
        } else {
            // Fallback jika status tidak dikenali.
            $subject = "Update Status Timesheet Anda untuk periode {$period}";
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
            view: 'emails.monthly_timesheets.status_notification_html', // Path ke view Blade.
            with: [
                'timesheet' => $this->timesheet, // Objek timesheet lengkap.
                'newStatus' => $this->newStatus, // Status baru ('approved'/'rejected').
                'processor' => $this->processor, // Objek User pemroses.
                'viewUrl' => $this->viewUrl,     // URL untuk tombol "Lihat Timesheet".
                'employeeName' => $this->timesheet->user?->name ?? 'Karyawan', // Nama karyawan.
                'periodStart' => $this->timesheet->period_start_date ? $this->timesheet->period_start_date->format('d M Y') : '-', // Format tanggal mulai.
                'periodEnd' => $this->timesheet->period_end_date ? $this->timesheet->period_end_date->format('d M Y') : '-',     // Format tanggal selesai.
                'rejectionReason' => ($this->newStatus === 'rejected') ? $this->timesheet->notes : null, // Alasan penolakan (jika ada).
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
