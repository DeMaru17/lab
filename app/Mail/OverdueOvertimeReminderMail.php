<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // Opsional: Implementasikan jika ingin email dikirim via antrian.
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection; // Untuk menampung daftar pengajuan.
use App\Models\User; // Model User untuk data approver.

/**
 * Class OverdueOvertimeReminderMail
 *
 * Mailable ini dikirim kepada approver (Asisten Manager atau Manager)
 * untuk mengingatkan bahwa terdapat sejumlah pengajuan lembur yang telah melewati
 * batas waktu tertentu (overdue) dan masih menunggu persetujuan mereka.
 *
 * @package App\Mail
 */
// class OverdueOvertimeReminderMail extends Mailable implements ShouldQueue // Opsional: implementasi antrian.
class OverdueOvertimeReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Instance model User dari approver yang akan menerima email ini.
     *
     * @var \App\Models\User
     */
    public User $approver;

    /**
     * Koleksi dari objek Overtime yang overdue dan menunggu persetujuan approver ini.
     *
     * @var \Illuminate\Support\Collection
     */
    public Collection $overdueRequests;

    /**
     * Membuat instance message baru.
     *
     * @param \Illuminate\Support\Collection $overdueRequests Koleksi objek Overtime yang overdue.
     * @param \App\Models\User $approver Objek User approver penerima email.
     * @return void
     */
    public function __construct(Collection $overdueRequests, User $approver)
    {
        $this->overdueRequests = $overdueRequests;
        $this->approver = $approver;
    }

    /**
     * Mendapatkan envelope (amplop) pesan email.
     * Mendefinisikan subjek email.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        $requestCount = $this->overdueRequests->count();
        // Subjek disesuaikan untuk pengajuan lembur.
        $subject = "Pengingat: {$requestCount} Pengajuan Lembur Menunggu Persetujuan Anda";

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
            view: 'emails.overtimes.overdue_reminder_html', // Path ke view Blade untuk email lembur.
            with: [
                'approverName' => $this->approver->name,
                'requests' => $this->overdueRequests, // Koleksi pengajuan lembur overdue.
                'approvalUrl' => $this->getApprovalUrl(), // URL ke halaman approval lembur yang relevan.
            ],
        );
    }

    /**
     * Helper method untuk menentukan URL halaman approval lembur yang sesuai
     * berdasarkan jabatan approver.
     *
     * @return string URL ke halaman approval lembur.
     */
    protected function getApprovalUrl(): string
    {
        // Pastikan nama route ini sesuai dengan yang didefinisikan di file routes/web.php untuk lembur.
        if ($this->approver->jabatan === 'manager') {
            return route('overtimes.approval.manager.list'); // URL untuk approval Manager.
        } else { // Asumsi selain manager adalah Asisten Manager.
            return route('overtimes.approval.asisten.list'); // URL untuk approval Asisten Manager.
        }
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
