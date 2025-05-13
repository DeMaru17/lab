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
 * Class OverdueLeaveReminderMail
 *
 * Mailable ini dikirim kepada approver (Asisten Manager atau Manager)
 * untuk mengingatkan bahwa terdapat sejumlah pengajuan cuti yang telah melewati
 * batas waktu tertentu (overdue) dan masih menunggu persetujuan mereka.
 *
 * @package App\Mail
 */
// class OverdueLeaveReminderMail extends Mailable implements ShouldQueue // Opsional: implementasi antrian.
class OverdueLeaveReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Instance model User dari approver yang akan menerima email ini.
     *
     * @var \App\Models\User
     */
    public User $approver;

    /**
     * Koleksi dari objek Cuti yang overdue dan menunggu persetujuan approver ini.
     *
     * @var \Illuminate\Support\Collection
     */
    public Collection $overdueRequests;

    /**
     * Membuat instance message baru.
     *
     * @param \Illuminate\Support\Collection $overdueRequests Koleksi objek Cuti yang overdue.
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
        $subject = "Pengingat: {$requestCount} Pengajuan Cuti Menunggu Persetujuan Anda";

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
            view: 'emails.cuti.overdue_reminder_html', // Path ke view Blade untuk email ini.
            with: [
                'approverName' => $this->approver->name,
                'requests' => $this->overdueRequests, // Koleksi pengajuan cuti overdue.
                'approvalUrl' => $this->getApprovalUrl(), // URL ke halaman approval yang relevan.
            ],
        );
    }

    /**
     * Helper method untuk menentukan URL halaman approval cuti yang sesuai
     * berdasarkan jabatan approver.
     *
     * @return string URL ke halaman approval.
     */
    protected function getApprovalUrl(): string
    {
        // Pastikan nama route ini sesuai dengan yang didefinisikan di file routes/web.php.
        if ($this->approver->jabatan === 'manager') {
            return route('cuti.approval.manager.list');
        } else { // Asumsi selain manager adalah Asisten Manager.
            return route('cuti.approval.asisten.list');
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
