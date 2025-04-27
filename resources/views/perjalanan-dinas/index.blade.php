{{-- resources/views/perjalanan_dinas/index.blade.php --}}
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Data Perjalanan Dinas</h3>
                    <p class="text-subtitle text-muted">
                        @if(in_array(Auth::user()->role, ['admin', 'manajemen']))
                            Daftar semua perjalanan dinas karyawan.
                        @else
                            Riwayat perjalanan dinas Anda.
                        @endif
                    </p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Perjalanan Dinas</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    {{-- Akhir Header Halaman --}}

    {{-- Tombol Tambah Baru (Hanya untuk Admin & Personil) --}}
    @can('create', App\Models\PerjalananDinas::class) {{-- Cek Policy --}}
     <div class="mb-3">
         <a href="{{ route('perjalanan-dinas.create') }}" class="btn btn-primary">
             <i class="bi bi-plus-lg"></i> Tambah Data Perjalanan Dinas
         </a>
     </div>
     @endcan

    {{-- Bagian Tabel Daftar Perjalanan Dinas --}}
    <section class="section">
        <div class="card">
            <div class="card-header">
                 <div class="row">
                    <div class="col-md-8 col-12">
                        <h4 class="card-title">Daftar Data</h4>
                    </div>
                    {{-- Hanya tampilkan search form jika bukan personil --}}
                    @if(Auth::user()->role !== 'personil')
                    <div class="col-md-4 col-12">
                        {{-- Form Pencarian --}}
                        <form action="{{ route('perjalanan-dinas.index') }}" method="GET">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Cari Nama/Jurusan..." name="search" value="{{ request('search') }}">
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-search"></i> Cari
                                </button>
                            </div>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="tablePerjalananDinas">
                        <thead>
                            <tr>
                                <th>No</th>
                                @if (Auth::user()->role !== 'personil')
                                    <th>Nama Karyawan</th>
                                @endif
                                <th>Jurusan / Tujuan</th>
                                <th>Tgl Berangkat</th>
                                <th>Tgl Pulang (Est/Real)</th>
                                <th>Lama Dinas (Hari)</th>
                                <th>Status</th>
                                <th>Cuti PD Diproses?</th> {{-- Kolom untuk is_processed --}}
                                <th style="min-width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($perjalananDinas as $index => $dinas)
                                <tr>
                                    <td>{{ $loop->iteration + $perjalananDinas->firstItem() - 1 }}</td>
                                    @if (Auth::user()->role !== 'personil')
                                        <td>{{ $dinas->user->name ?? 'N/A' }}</td>
                                    @endif
                                    <td>{{ $dinas->jurusan }}</td>
                                    <td>{{ $dinas->tanggal_berangkat ? $dinas->tanggal_berangkat->format('d/m/Y') : '-' }}</td>
                                    <td>
                                        @if($dinas->tanggal_pulang)
                                            {{ $dinas->tanggal_pulang->format('d/m/Y') }} (Real)
                                        @else
                                            {{ $dinas->perkiraan_tanggal_pulang ? $dinas->perkiraan_tanggal_pulang->format('d/m/Y') : '-' }} (Est)
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $dinas->lama_dinas ?? '-' }}</td>
                                    <td>
                                        @php
                                            $statusClass = $dinas->status == 'selesai' ? 'bg-success' : 'bg-info';
                                        @endphp
                                        <span class="badge {{ $statusClass }}">{{ Str::title($dinas->status) }}</span>
                                    </td>
                                     <td class="text-center">
                                        @if($dinas->is_processed)
                                            <span class="badge bg-light-success" data-bs-toggle="tooltip" title="Kuota Cuti PD sudah ditambahkan"><i class="bi bi-check-circle-fill"></i> Ya</span>
                                        @else
                                             <span class="badge bg-light-secondary" data-bs-toggle="tooltip" title="Kuota Cuti PD belum diproses"><i class="bi bi-hourglass-split"></i> Tidak</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        @php $aksiDitampilkan = false; @endphp
                                        @can('update', $dinas)
                                            <a href="{{ route('perjalanan-dinas.edit', $dinas->id) }}" class="btn btn-warning btn-sm d-inline-block me-1" data-bs-toggle="tooltip" title="Edit Data"><i class="bi bi-pencil-square"></i></a>
                                            @php $aksiDitampilkan = true; @endphp
                                        @endcan
                                        @can('delete', $dinas)
                                            {{-- Modifikasi Form Hapus --}}
                                            <form action="{{ route('perjalanan-dinas.destroy', $dinas->id) }}" method="POST" class="d-inline-block delete-form"> {{-- Tambah class 'delete-form' --}}
                                                @csrf
                                                @method('DELETE')
                                                {{-- Hapus onsubmit, ganti type ke 'button' atau biarkan 'submit' tapi cegah defaultnya di JS --}}
                                                <button type="submit" class="btn btn-danger btn-sm delete-button" data-bs-toggle="tooltip" title="Hapus Data"> {{-- Tambah class 'delete-button' --}}
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            @php $aksiDitampilkan = true; @endphp
                                        @endcan
                                        @if (!$aksiDitampilkan) - @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ Auth::user()->role !== 'personil' ? 9 : 8 }}" class="text-center">Tidak ada data perjalanan dinas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Link Pagination --}}
                <div class="mt-3">
                    {{ $perjalananDinas->links() }}
                </div>
            </div>
        </div>
    </section>
    {{-- Akhir Bagian Tabel --}}

</div>
@endsection

@push('js')
{{-- Pastikan SweetAlert2 sudah dimuat di layout utama Anda --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi Tooltip
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Event Listener untuk semua tombol hapus dengan class 'delete-button'
    const deleteButtons = document.querySelectorAll('.delete-button');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault(); // Mencegah form langsung submit

            const form = this.closest('form'); // Cari form terdekat

            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Data perjalanan dinas ini akan dihapus secara permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33', // Warna tombol konfirmasi (merah)
                cancelButtonColor: '#3085d6', // Warna tombol batal
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Jika user konfirmasi, submit form hapus
                    form.submit();
                }
            });
        });
    });
});
</script>
