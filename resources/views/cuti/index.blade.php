@extends('layout.app') {{-- Pastikan ini layout utama Anda --}}

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Daftar Pengajuan Cuti</h3>
                    <p class="text-subtitle text-muted">
                        @if(Auth::user()->role === 'personil' || Auth::user()->role === 'admin')
                            Riwayat pengajuan cuti Anda.
                        @else {{-- Manajemen --}}
                            Daftar semua pengajuan cuti karyawan.
                        @endif
                    </p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Daftar Cuti</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    {{-- Akhir Header Halaman --}}

     {{-- Tombol Ajukan Cuti Baru (Hanya untuk Personil & Admin) --}}
     @if(in_array(Auth::user()->role, ['personil', 'admin']))
     <div class="mb-3">
         <a href="{{ route('cuti.create') }}" class="btn btn-primary">
             <i class="bi bi-plus-lg"></i> Ajukan Cuti Baru
         </a>
     </div>
     @endif

    {{-- Bagian Tabel Daftar Cuti --}}
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Data Pengajuan Cuti</h4>
                 {{-- TODO: Tambahkan filter berdasarkan status atau tanggal jika diperlukan --}}
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="tableCutiIndex"> {{-- ID jika perlu datatables nanti --}}
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tgl Pengajuan</th>
                                {{-- Tampilkan Nama Pengaju hanya jika bukan personil biasa --}}
                                @if (Auth::user()->role !== 'personil')
                                    <th>Nama Pengaju</th>
                                @endif
                                <th>Jenis Cuti</th>
                                <th>Tanggal Cuti</th>
                                <th>Durasi (Hari Kerja)</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cuti as $index => $item)
                                <tr>
                                    <td>{{ $loop->iteration + $cuti->firstItem() - 1 }}</td>
                                    <td>{{ $item->created_at->format('d/m/Y H:i') }}</td>
                                    {{-- Tampilkan Nama Pengaju hanya jika bukan personil biasa --}}
                                    @if (Auth::user()->role !== 'personil')
                                        <td>{{ $item->user->name ?? 'N/A' }}</td>
                                    @endif
                                    <td>{{ $item->jenisCuti->nama_cuti ?? 'N/A' }}</td>
                                    <td>{{ $item->mulai_cuti->format('d/m/Y') }} - {{ $item->selesai_cuti->format('d/m/Y') }}</td>
                                    <td class="text-center">{{ $item->lama_cuti }}</td>
                                    <td>
                                        {{-- Styling Badge untuk Status --}}
                                        @php
                                            $statusClass = '';
                                            $statusText = Str::title(str_replace('_', ' ', $item->status)); // Ganti _ jadi spasi, buat title case
                                            switch ($item->status) {
                                                case 'pending':
                                                    $statusClass = 'bg-warning';
                                                    break;
                                                case 'pending_manager_approval':
                                                    $statusClass = 'bg-info';
                                                    $statusText = 'Menunggu Approval Manager'; // Teks lebih jelas
                                                    break;
                                                case 'approved':
                                                    $statusClass = 'bg-success';
                                                    break;
                                                case 'rejected':
                                                    $statusClass = 'bg-danger';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-secondary';
                                            }
                                        @endphp
                                        <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                                    </td>
                                    <td>
                                        {{-- Tampilkan Keperluan, dan Alasan Penolakan jika ada --}}
                                        <span data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $item->keperluan }}"> {{-- Title berisi teks lengkap --}}
                                            {{ Str::limit($item->keperluan, 20) }} {{-- Batasi tampilan jadi 50 karakter, tambahkan '...' otomatis --}}
                                        </span>
                                        @if($item->status == 'rejected' && $item->notes)
                                            <br>
                                            <small class="text-danger fst-italic" data-bs-toggle="tooltip" title="Alasan Penolakan">
                                                <i class="bi bi-x-circle-fill"></i> {{ $item->notes }}
                                            </small>
                                        @endif
                                        {{-- Tampilkan Link Surat Sakit jika ada (bisa juga diletakkan di Aksi) --}}
                                        @if($item->surat_sakit)
                                            <br>
                                            <a href="{{ asset('storage/' . $item->surat_sakit) }}" target="_blank" class="text-info" data-bs-toggle="tooltip" title="Lihat Surat Sakit">
                                                <i class="bi bi-paperclip"></i> Lampiran
                                            </a>
                                        @endif
                                    </td>
                                    <td>
                                        {{-- Tombol Edit (jika status rejected) --}}
                                        @if (Auth::id() == $item->user_id && $item->status == 'rejected')
                                        <a href="{{ route('cuti.edit', $item->id) }}" {{-- Sesuaikan route edit nanti --}}
                                        class="btn btn-warning btn-sm d-inline-block" data-bs-toggle="tooltip" title="Edit & Ajukan Ulang">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        @endif

                                        {{-- Tombol Batal jika user pemilik DAN status 'pending' ATAU 'approved' DAN belum mulai --}}
                                        @if (Auth::id() == $item->user_id && in_array($item->status, ['pending', 'approved']) && \Carbon\Carbon::today()->lt($item->mulai_cuti) )
                                        <form action="{{ route('cuti.cancel', $item->id) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pengajuan cuti ini?')">
                                            @csrf
                                            {{-- Method POST sesuai definisi route --}}
                                            <button type="submit" class="btn btn-danger btn-sm" data-bs-toggle="tooltip" title="Batalkan Pengajuan">
                                                <i class="bi bi-x-circle"></i> {{-- Ikon Batal --}}
                                            </button>
                                        </form>
                                        @endif

                                        {{-- Placeholder jika tidak ada aksi lain --}}
                                        @if (! (Auth::id() == $item->user_id && $item->status == 'rejected') &&
                                        ! (Auth::id() == $item->user_id && in_array($item->status, ['pending', 'approved']) && \Carbon\Carbon::today()->lt($item->mulai_cuti)) )
                                        -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    {{-- Sesuaikan colspan berdasarkan role --}}
                                    <td colspan="{{ Auth::user()->role !== 'personil' ? 9 : 8 }}" class="text-center">Tidak ada data pengajuan cuti.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Tampilkan Link Pagination --}}
                <div class="mt-3">
                    {{ $cuti->links() }} {{-- Pastikan Controller mengirim $cuti sebagai Paginator --}}
                </div>
            </div>
        </div>
    </section>
    {{-- Akhir Bagian Tabel --}}

</div>
@endsection

@push('js')
<script>
// Inisialisasi semua tooltip Bootstrap di halaman ini
document.addEventListener('DOMContentLoaded', function() {
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
      var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
      })
});
</script>
@endpush