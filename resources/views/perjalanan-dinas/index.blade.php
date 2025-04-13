<!-- filepath: c:\xampp1\htdocs\lab\resources\views\PerjalananDinas\index.blade.php -->
@extends('layout.app')

@section('content')
<div id="main">
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Daftar Perjalanan Dinas</h3>
                    <p class="text-subtitle text-muted">Kelola data perjalanan dinas di sini.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{route('dashboard.index')}}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Perjalanan Dinas</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Tabel daftar perjalanan dinas -->
        <section class="section">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Daftar Perjalanan Dinas</h4>
                    <a href="{{ route('perjalanan-dinas.create') }}" class="btn btn-primary">Tambah Perjalanan Dinas</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Jurusan</th>
                                    <th>Tanggal Berangkat</th>
                                    <th>Perkiraan Pulang</th>
                                    <th>Tanggal Pulang</th>
                                    <th>Lama Dinas</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($perjalananDinas as $dinas)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $dinas->user->name }}</td>
                                        <td>{{ $dinas->jurusan }}</td>
                                        <td>{{ \Carbon\Carbon::parse($dinas->tanggal_berangkat)->format('d-m-Y') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($dinas->perkiraan_tanggal_pulang)->format('d-m-Y') }}</td>
                                        <td>{{ $dinas->tanggal_pulang ? \Carbon\Carbon::parse($dinas->tanggal_pulang)->format('d-m-Y') : '-' }}</td>
                                        <td>{{ $dinas->lama_dinas ?? '-' }} hari</td>
                                        <td>
                                            <span class="badge bg-{{ $dinas->status == 'selesai' ? 'success' : 'warning' }}">
                                                {{ ucfirst($dinas->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <!-- Tombol edit -->
                                            <a href="{{ route('perjalanan-dinas.edit', $dinas->id) }}" class="btn btn-warning btn-sm me-1">Edit</a>
                                            <!-- Tombol hapus -->
                                            <a href="{{route('perjalanan-dinas.destroy', $dinas->id) }}" class="btn btn-danger btn-sm" data-confirm-delete="true">Hapus</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center">Tidak ada data perjalanan dinas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection