{{-- resources/views/vendors/index.blade.php --}}
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@section('content')
    <div id="main">
        {{-- Header Halaman & Breadcrumb --}}
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Kelola Vendor</h3>
                        <p class="text-subtitle text-muted">Daftar vendor outsourcing.</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Vendor</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        {{-- Akhir Header Halaman --}}

        {{-- Tombol Tambah Vendor Baru --}}
        <div class="mb-3">
            <a href="{{ route('vendors.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Tambah Vendor Baru
            </a>
        </div>

        {{-- Bagian Tabel Daftar Vendor --}}
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Daftar Vendor</h4>
                    {{-- Tambahkan filter/search jika diperlukan nanti --}}
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="tableVendors">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Vendor</th>
                                    <th>Logo</th>
                                    <th style="min-width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($vendors as $index => $vendor)
                                    <tr>
                                        <td>{{ $loop->iteration + $vendors->firstItem() - 1 }}</td>
                                        <td>{{ $vendor->name }}</td>
                                        <td>
                                            @if ($vendor->logo_path && Storage::disk('public')->exists($vendor->logo_path))
                                                <img src="{{ asset('storage/' . $vendor->logo_path) }}"
                                                    alt="Logo {{ $vendor->name }}"
                                                    style="max-height: 40px; height: auto; background-color: #eee; padding: 2px;">
                                            @else
                                                <span class="text-muted">- Tidak ada logo -</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            {{-- Tombol Edit --}}
                                            <a href="{{ route('vendors.edit', $vendor->id) }}"
                                                class="btn btn-warning btn-sm d-inline-block me-1" data-bs-toggle="tooltip"
                                                title="Edit Vendor">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>

                                            {{-- Tombol Hapus (Gunakan form) --}}
                                            {{-- Tambahkan kelas 'delete-form' agar bisa ditargetkan oleh JavaScript --}}
                                            <form action="{{ route('vendors.destroy', $vendor->id) }}" method="POST"
                                                class="d-inline-block delete-form">
                                                @csrf
                                                @method('DELETE')
                                                {{-- Tambahkan atribut data untuk nama vendor, agar bisa ditampilkan di pesan konfirmasi --}}
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                    data-bs-toggle="tooltip" title="Hapus Vendor"
                                                    data-vendor-name="{{ $vendor->name }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">Belum ada data vendor.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{-- Link Pagination --}}
                    <div class="mt-3">
                        {{ $vendors->links() }} {{-- Pastikan Controller mengirim $vendors sebagai Paginator --}}
                    </div>
                </div>
            </div>
        </section>
        {{-- Akhir Bagian Tabel --}}

    </div>
@endsection

@push('js')
    {{-- Pastikan jQuery sudah di-load jika Anda menggunakan sintaks jQuery untuk SweetAlert --}}
    {{-- Jika Anda menggunakan SweetAlert2 vanilla JS, jQuery tidak diperlukan --}}
    <script>
        // Inisialisasi semua tooltip Bootstrap di halaman ini
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });

            // Logika untuk konfirmasi delete menggunakan SweetAlert
            const deleteForms = document.querySelectorAll(
            '.delete-form'); // Ambil semua form dengan kelas .delete-form
            deleteForms.forEach(form => {
                form.addEventListener('submit', function(event) {
                    event.preventDefault(); // Mencegah form langsung submit
                    const vendorName = this.querySelector('button[type="submit"]').dataset
                        .vendorName || 'vendor ini'; // Ambil nama vendor dari atribut data

                    Swal.fire({
                        title: 'Hapus Vendor',
                        html: `Anda yakin ingin menghapus vendor "<strong>${vendorName}</strong>"?<br><small class='text-danger'>Peringatan: Vendor tidak dapat dihapus jika masih terhubung dengan data pengguna.</small>`, // Menggunakan html untuk format teks
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Ya, Hapus!',
                        cancelButtonText: 'Batal',
                        // Properti tambahan dari package realrashid/sweet-alert (jika menggunakan helper `confirmDelete`)
                        // Mungkin perlu disesuaikan jika Anda memanggil Swal.fire() secara langsung
                        // customClass: {
                        //     confirmButton: 'btn btn-danger',
                        //     cancelButton: 'btn btn-success ms-1'
                        // },
                        // buttonsStyling: false,
                        // reverseButtons: true // Opsional, membalik urutan tombol
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit(); // Jika dikonfirmasi, submit form
                        }
                    });
                });
            });
        });
    </script>
@endpush
