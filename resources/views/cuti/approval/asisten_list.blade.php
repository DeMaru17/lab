@extends('layout.app') {{-- Pastikan ini adalah layout utama Anda --}}

@section('content')
<div id="main">
    {{-- Bagian Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Persetujuan Cuti Asisten Manager</h3>
                    <p class="text-subtitle text-muted">Daftar pengajuan cuti yang menunggu persetujuan Anda.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Persetujuan Cuti Asisten</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    {{-- Akhir Header Halaman --}}

    {{-- Bagian Tabel Daftar Pengajuan --}}
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Daftar Pengajuan Cuti </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    {{-- Tabel - Beri ID jika ingin menggunakan DataTables client-side nanti --}}
                    <table class="table table-striped table-hover" id="tableApprovalAsisten">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tgl Pengajuan</th>
                                <th>Nama Pengaju</th>
                                <th>Jabatan</th>
                                <th>Jenis Cuti</th>
                                <th>Tanggal Cuti</th>
                                <th>Lama Cuti (Hari Kerja)</th>
                                <th>Sisa Kuota </th> {{-- Kolom Baru --}}
                                <th>Keperluan</th>
                                <th style="min-width: 100px;">Aksi</th> {{-- Beri min-width agar tombol tidak wrap --}}
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Loop data cuti yang pending --}}
                            @forelse ($pendingCuti as $item)
                                {{-- Hitung sisa kuota untuk ditampilkan --}}
                                @php
                                    $quotaKey = $item->user_id . '_' . $item->jenis_cuti_id;
                                    $quotaInfo = $relevantQuotas->get($quotaKey); // Ambil dari collection yg dikirim Controller

                                    $sisaKuotaDisplay = '-'; // Default
                                    // Cek case-insensitive untuk nama cuti sakit
                                    if (strtolower($item->jenisCuti->nama_cuti ?? '') === 'cuti sakit') {
                                         $sisaKuotaDisplay = 'N/A'; // Tidak relevan
                                    } elseif ($quotaInfo) {
                                         $sisaKuotaDisplay = $quotaInfo->durasi_cuti . ' hari';
                                    } else {
                                         // Jika data kuota tidak ditemukan (seharusnya tidak terjadi)
                                         $sisaKuotaDisplay = 'Data?';
                                    }
                                @endphp
                                {{-- Baris data --}}
                                <tr>
                                    {{-- Penomoran dengan pagination --}}
                                    <td>{{ $loop->iteration + $pendingCuti->firstItem() - 1 }}</td>
                                    {{-- Data Pengajuan --}}
                                    <td>{{ $item->created_at->format('d/m/Y') }}</td>
                                    <td>{{ $item->user->name ?? 'N/A' }}</td>
                                    <td>{{ $item->user->jabatan ?? 'N/A' }}</td>
                                    <td>{{ $item->jenisCuti->nama_cuti ?? 'N/A' }}</td>
                                    <td>{{ $item->mulai_cuti->format('d/m/Y') }} - {{ $item->selesai_cuti->format('d/m/Y') }}</td>
                                    <td class="text-center">{{ $item->lama_cuti }}</td> {{-- Hari Kerja --}}
                                    <td class="text-center">{{ $sisaKuotaDisplay }}</td> {{-- Sisa Kuota --}}
                                    <td>
                                        {{-- Tampilkan tooltip jika text panjang --}}
                                        <span data-bs-toggle="tooltip" title="{{ $item->keperluan }}">
                                            {{ Str::limit($item->keperluan, 40) }}
                                        </span>
                                    </td>
                                    {{-- Kolom Aksi --}}
                                    <td>
                                        @if ($item->surat_sakit)
                                            <a href="{{ asset('storage/' . $item->surat_sakit) }}" target="_blank" class="btn btn-secondary btn-sm d-inline-block" data-bs-toggle="tooltip" title="Lihat Surat Sakit">
                                                <i class="bi bi-paperclip"></i> {{-- Ikon paperclip --}}
                                            </a>
                                        @endif
                                        {{-- Tombol Approve L1 --}}
                                        <form action="{{ route('cuti.approval.asisten.approve', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Konfirmasi Persetujuan:\n\nAnda yakin ingin MENYETUJUI pengajuan cuti ini?\nPengajuan akan diteruskan ke Manager.')">
                                            @csrf
                                            @method('PATCH') {{-- Method PATCH sesuai definisi route --}}
                                            <button type="submit" class="btn btn-success btn-sm" data-bs-toggle="tooltip" title="Setujui">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>

                                        {{-- Tombol Tolak (Trigger Modal) --}}
                                         <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal" data-cuti-id="{{ $item->id }}" data-user-name="{{ $item->user->name ?? 'N/A' }}" data-bs-toggle="tooltip" title="Tolak Pengajuan">
                                             <i class="bi bi-x-lg"></i>
                                         </button>

                                        {{-- Tombol Detail (Opsional) --}}
                                        {{-- <a href="{{ route('cuti.show', $item->id) }}" class="btn btn-info btn-sm" data-bs-toggle="tooltip" title="Lihat Detail">
                                            <i class="bi bi-eye"></i>
                                        </a> --}}
                                    </td>
                                </tr>
                            @empty
                                {{-- Pesan jika tidak ada data --}}
                                <tr>
                                    <td colspan="10" class="text-center">Tidak ada pengajuan cuti yang menunggu persetujuan Anda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Tampilkan Link Pagination --}}
                <div class="mt-3">
                    {{ $pendingCuti->links() }}
                </div>
            </div>
        </div>
    </section>
    {{-- Akhir Bagian Tabel --}}


    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white"> {{-- Beri style header --}}
            <h5 class="modal-title" id="rejectModalLabel">Tolak Pengajuan Cuti</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          {{-- Form di dalam modal --}}
          <form id="rejectForm" method="POST" action=""> {{-- Action diisi oleh JS --}}
            @csrf {{-- CSRF Token --}}
            {{-- Method tidak perlu dispoofing karena route pakai POST --}}
            <div class="modal-body">
              <p>Anda akan menolak pengajuan cuti untuk karyawan: <strong id="rejectUserName">Nama Karyawan</strong>.</p>
              <div class="mb-3">
                <label for="rejectNotes" class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                <textarea class="form-control" id="rejectNotes" name="notes" rows="4" required placeholder="Masukkan alasan mengapa pengajuan ditolak..."></textarea>
                 <div class="invalid-feedback">Alasan penolakan wajib diisi.</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-danger">Tolak Pengajuan</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    </div>
@endsection

@push('js')
<script>
// Script untuk mengisi action form reject modal dan inisialisasi tooltip
document.addEventListener('DOMContentLoaded', function() {
    var rejectModal = document.getElementById('rejectModal');
    var rejectForm = document.getElementById('rejectForm');
    var rejectUserName = document.getElementById('rejectUserName');
    var rejectNotes = document.getElementById('rejectNotes'); // Ambil textarea

    if(rejectModal) { // Hanya jalankan jika modal ada
        rejectModal.addEventListener('show.bs.modal', function (event) {
          var button = event.relatedTarget;
          var cutiId = button.getAttribute('data-cuti-id');
          var userName = button.getAttribute('data-user-name');

          rejectUserName.textContent = userName;
          // Buat URL action (Pastikan base URL benar)
          var actionUrl = `{{ url('/cuti/approval') }}/${cutiId}/reject`; // Gunakan url() helper
          rejectForm.action = actionUrl;
          // Reset textarea dan hapus state invalid jika ada
          rejectNotes.value = '';
          rejectNotes.classList.remove('is-invalid');
        });
    }

     // Client-side validation sebelum submit modal
     if(rejectForm) {
         rejectForm.addEventListener('submit', function(event) {
             if (!rejectNotes.value || rejectNotes.value.trim() === '') {
                 // Tampilkan pesan error sederhana atau gunakan style Bootstrap
                 rejectNotes.classList.add('is-invalid'); // Tambah class invalid
                 console.error('Alasan penolakan kosong!'); // Log error
                 event.preventDefault(); // Hentikan submit
                 rejectNotes.focus();
             } else {
                  rejectNotes.classList.remove('is-invalid'); // Hapus class jika valid
             }
         });
     }

     // Inisialisasi semua tooltip Bootstrap di halaman ini
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
      var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        // Tambahkan opsi agar tooltip bisa berisi HTML jika perlu
        // return new bootstrap.Tooltip(tooltipTriggerEl, { html: true })
        return new bootstrap.Tooltip(tooltipTriggerEl)
      })
});
</script>
@endpush