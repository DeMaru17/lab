<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // Direkomendasikan untuk diaktifkan.
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection; // Menggunakan Collection untuk daftar lembur.
use App\Models\User;

/**
 * Class BulkOvertimeStatusNotificationMail
 *
 * Mailable ini dikirim kepada karyawan untuk memberitahukan bahwa sejumlah
 * pengajuan lembur mereka telah disetujui secara massal (bulk approve) oleh Manager.
 * Email ini berisi ringkasan dari beberapa pengajuan lembur yang disetujui.
 *
 * @package App\Mail
 */
class BulkOvertimeStatusNotificationMail extends Mailable implements ShouldQueue // Menggunakan antrian.
{
    use Queueable, SerializesModels;

    /**
     * Pengguna (karyawan) yang mengajukan lembur dan akan menerima email ini.
     *
     * @var \App\Models\User
     */
    public User $pengaju;

    /**
     * Pengguna (Manager) yang melakukan proses persetujuan massal.
     *
     * @var \App\Models\User
     */
    public User $approver;

    /**
     * Koleksi dari objek Overtime yang telah disetujui secara massal.
     *
     * @var \Illuminate\Support\Collection
     */
    public Collection $approvedOvertimes;

    /**
     * Membuat instance message baru.
     *
     * @param \Illuminate\Support\Collection $approvedOvertimes Koleksi objek Overtime yang disetujui.
     * @param \App\Models\User $approver Objek User approver (Manager).
     * @param \App\Models\User $pengaju Objek User pengaju (karyawan penerima email).
     * @return void
     */
    public function __construct(Collection $approvedOvertimes, User $approver, User $pengaju)
    {
        $this->approvedOvertimes = $approvedOvertimes;
        $this->approver = $approver;
        $this->pengaju = $pengaju;
    }

    /**
     * Mendapatkan envelope (amplop) pesan email.
     * Mendefinisikan subjek email.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        $count = $this->approvedOvertimes->count();
        $subject = "{$count} Pengajuan Lembur Anda Telah Disetujui";

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
            // Path ke view Blade untuk email ini.
            // View ini akan menampilkan daftar ringkasan dari beberapa pengajuan lembur.
            view: 'emails.overtimes.bulk_status_notification_html',
            with: [
                'namaKaryawan' => $this->pengaju->name,
                'approverName' => $this->approver->name,
                'requests' => $this->approvedOvertimes, // Mengirim koleksi objek Overtime ke view.
                'viewUrl' => route('overtimes.index'),  // URL untuk tombol "Lihat Daftar Lembur".
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
