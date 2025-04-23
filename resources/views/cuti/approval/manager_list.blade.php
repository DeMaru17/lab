@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@section('content')
<div id="main">
    {{-- Bagian Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Persetujuan Cuti Manager</h3>
                    <p class="text-subtitle text-muted">Daftar pengajuan cuti yang menunggu persetujuan final Anda.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Persetujuan Cuti Manager</li>
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
                <h4 class="card-title">Menunggu Persetujuan Final</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="tableApprovalManager"> {{-- ID berbeda jika perlu --}}
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tgl Pengajuan</th>
                                <th>Nama Pengaju</th>
                                <th>Jabatan</th>
                                <th>Jenis Cuti</th>
                                <th>Tanggal Cuti</th>
                                <th>Lama (Hari Kerja)</th>
                                <th>Sisa Kuota</th>
                                <th>Disetujui AM Oleh</th> {{-- Info Approver L1 --}}
                                <th>Tgl Appr. AM</th>     {{-- Info Approver L1 --}}
                                <th>Keperluan</th>
                                <th style="min-width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pendingCutiManager as $item)
                                {{-- Hitung sisa kuota untuk ditampilkan --}}
                                @php
                                    $quotaKey = $item->user_id . '_' . $item->jenis_cuti_id;
                                    $quotaInfo = $relevantQuotas->get($quotaKey);
                                    $sisaKuotaDisplay = '-';
                                    if (strtolower($item->jenisCuti->nama_cuti ?? '') === 'cuti sakit') {
                                         $sisaKuotaDisplay = 'N/A';
                                    } elseif ($quotaInfo) {
                                         $sisaKuotaDisplay = $quotaInfo->durasi_cuti . ' hari';
                                    } else {
                                         $sisaKuotaDisplay = 'Data?';
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration + $pendingCutiManager->firstItem() - 1 }}</td>
                                    <td>{{ $item->created_at->format('d/m/Y') }}</td>
                                    <td>{{ $item->user->name ?? 'N/A' }}</td>
                                    <td>{{ $item->user->jabatan ?? 'N/A' }}</td>
                                    <td>{{ $item->jenisCuti->nama_cuti ?? 'N/A' }}</td>
                                    <td>{{ $item->mulai_cuti->format('d/m/Y') }} - {{ $item->selesai_cuti->format('d/m/Y') }}</td>
                                    <td class="text-center">{{ $item->lama_cuti }}</td>
                                    <td class="text-center">{{ $sisaKuotaDisplay }}</td>
                                    <td>{{ $item->approverAsisten->name ?? 'N/A' }}</td> {{-- Tampilkan Nama Approver L1 --}}
                                    <td>{{ $item->approved_at_asisten ? $item->approved_at_asisten->format('d/m/Y H:i') : '-' }}</td> {{-- Tampilkan Tgl Approver L1 --}}
                                    <td>
                                        <span data-bs-toggle="tooltip" title="{{ $item->keperluan }}">
                                            {{ Str::limit($item->keperluan, 40) }}
                                        </span>
                                         {{-- Link Surat Sakit jika ada --}}
                                         @if($item->surat_sakit)
                                             <br>
                                             <a href="{{ asset('storage/' . $item->surat_sakit) }}" target="_blank" class="text-info" data-bs-toggle="tooltip" title="Lihat Surat Sakit">
                                                 <i class="bi bi-paperclip"></i> Lampiran
                                             </a>
                                         @endif
                                    </td>
                                    <td class="text-nowrap">
                                        {{-- Tombol Approve Final (L2) --}}
                                        {{-- Perlu route 'cuti.approval.manager.approve' nanti --}}
                                        <button type="button" class="btn btn-success btn-sm me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#approveModal"
                                                data-cuti-id="{{ $item->id }}"
                                                data-user-name="{{ $item->user->name ?? 'N/A' }}"
                                                data-approval-level="manager" {{-- Level approval berbeda --}}
                                                data-bs-toggle="tooltip" title="Setujui (Final)">
                                            <i class="bi bi-check-lg"></i>
                                        </button>

                                        {{-- Tombol Tolak (Trigger Modal yang sama) --}}
                                         <button type="button" class="btn btn-danger btn-sm d-inline-block me-1" 
                                                 data-bs-toggle="modal" 
                                                 data-bs-target="#rejectModal" 
                                                 data-cuti-id="{{ $item->id }}" 
                                                 data-user-name="{{ $item->user->name ?? 'N/A' }}" 
                                                 data-bs-toggle="tooltip" title="Tolak Pengajuan">
                                             <i class="bi bi-x-lg"></i>
                                         </button>

                                        {{-- Tombol Detail (Opsional) --}}
                                        {{-- <a href="{{ route('cuti.show', $item->id) }}" class="btn btn-info btn-sm d-inline-block" data-bs-toggle="tooltip" title="Lihat Detail Lengkap">
                                            <i class="bi bi-eye"></i>
                                        </a> --}}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center">Tidak ada pengajuan cuti yang menunggu persetujuan final Anda.</td> {{-- Sesuaikan colspan --}}
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Link Pagination --}}
                <div class="mt-3">
                    {{ $pendingCutiManager->links() }}
                </div>
            </div>
        </div>
    </section>
    {{-- Akhir Bagian Tabel --}}

    <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title" id="approveModalLabel">Konfirmasi Persetujuan Cuti</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            {{-- Form di dalam modal --}}
            <form id="approveForm" method="POST" action=""> {{-- Action diisi oleh JS --}}
              @csrf
              @method('PATCH') {{-- Method untuk route approve --}}
              <div class="modal-body">
                <p>Anda akan menyetujui pengajuan cuti untuk karyawan: <strong id="approveUserName">Nama Karyawan</strong>.</p>
                {{-- Pesan tambahan khusus untuk Manager --}}
                <p id="approveQuotaWarning" class="text-danger fw-bold" style="display: none;">
                  PENTING: Menyetujui cuti ini akan mengurangi sisa kuota cuti karyawan.
                </p>
                {{-- Bisa tambahkan textarea opsional untuk catatan approval jika perlu --}}
                {{-- <div class="mb-3">
                  <label for="approveNotes" class="form-label">Catatan Persetujuan (Opsional)</label>
                  <textarea class="form-control" id="approveNotes" name="approval_notes" rows="3"></textarea>
                </div> --}}
                <p>Apakah Anda yakin ingin melanjutkan?</p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success">Ya, Setujui</button>
              </div>
            </form>
          </div>
        </div>
      </div>

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
    {{-- Script untuk modal reject dan tooltip (sama seperti di asisten_list) --}}
    {{-- Pastikan script ini dimasukkan --}}
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var rejectModal = document.getElementById('rejectModal');
        var rejectForm = document.getElementById('rejectForm');
        var rejectUserName = document.getElementById('rejectUserName');
        var rejectNotes = document.getElementById('rejectNotes');

        if(rejectModal) {
            rejectModal.addEventListener('show.bs.modal', function (event) {
              var button = event.relatedTarget;
              var cutiId = button.getAttribute('data-cuti-id');
              var userName = button.getAttribute('data-user-name');

              rejectUserName.textContent = userName;
              var actionUrl = `{{ url('/cuti-approval') }}/${cutiId}/reject`; // URL route reject
              rejectForm.action = actionUrl;
              rejectNotes.value = '';
              rejectNotes.classList.remove('is-invalid');
            });
        }

        if(rejectForm) {
             rejectForm.addEventListener('submit', function(event) {
                 if (!rejectNotes.value || rejectNotes.value.trim() === '') {
                     rejectNotes.classList.add('is-invalid');
                     event.preventDefault();
                     rejectNotes.focus();
                 } else {
                      rejectNotes.classList.remove('is-invalid');
                 }
             });
         }

          var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
          var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
          })

          // Di dalam listener DOMContentLoaded, setelah script modal reject

        var approveModal = document.getElementById('approveModal');
        var approveForm = document.getElementById('approveForm');
        var approveUserName = document.getElementById('approveUserName');
        var approveQuotaWarning = document.getElementById('approveQuotaWarning'); // Ambil elemen warning kuota

        if(approveModal) {
        approveModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var cutiId = button.getAttribute('data-cuti-id');
        var userName = button.getAttribute('data-user-name');
        var level = button.getAttribute('data-approval-level'); // Ambil level approval

        // Set nama user di modal
        approveUserName.textContent = userName;

        // Tentukan URL action berdasarkan level
        var actionUrl = "";
        if (level === 'asisten') {
            actionUrl = `{{ url('/cuti-approval/asisten') }}/${cutiId}/approve`;
            approveQuotaWarning.style.display = 'none'; // Sembunyikan warning kuota untuk Asisten
        } else if (level === 'manager') {
            actionUrl = `{{ url('/cuti-approval/manager') }}/${cutiId}/approve`;
            approveQuotaWarning.style.display = 'block'; // Tampilkan warning kuota untuk Manager
        } else {
            console.error('Level approval tidak dikenal:', level);
            // Jangan set action jika level tidak jelas
        }

        if (actionUrl) {
            approveForm.action = actionUrl;
        }
       // Reset optional notes field if you add it later
       // const approveNotes = document.getElementById('approveNotes');
       // if(approveNotes) approveNotes.value = '';

    });
}

// ... (kode tooltip init tetap ada) ...
    });
    </script>
@endpush

{{-- Opsional: Buat Partial untuk Modal Reject --}}
{{-- Buat file baru misal: resources/views/cuti/approval/_reject_modal.blade.php --}}
{{-- Pindahkan kode HTML <div class="modal fade" id="rejectModal" ...> ... </div> ke file ini --}}
{{-- Lalu di view asisten_list dan manager_list, ganti kode modal dengan: @include('cuti.approval._reject_modal') --}}