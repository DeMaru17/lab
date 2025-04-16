{{-- filepath: resources/views/cuti/quota/index.blade.php --}}
@extends('layout.app')

@section('content')
<div id="main">
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Kuota Cuti</h3>
                    <p class="text-subtitle text-muted">Kelola data kuota cuti di sini.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Kuota Cuti</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Tabel daftar kuota cuti -->
        <section class="section">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Daftar Kuota Cuti</h4>
                    @if (Auth::user()->role !== 'personil')
                        <form method="GET" action="{{ route('cuti.quota.index') }}" class="d-flex">
                            <input type="text" name="search" class="form-control me-2" placeholder="Cari nama atau email..." value="{{ request('search') }}">
                            <button type="submit" class="btn btn-primary">Cari</button>
                        </form>
                    @endif
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Jenis Cuti</th>
                                    <th>Kuota Tersedia</th>
                                    @if (Auth::user()->role !== 'personil')
                                        <th class="text-center">Aksi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cutiQuota as $quota)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $quota->user->name }}</td>
                                        <td>{{ $quota->user->email }}</td>
                                        <td>{{ $quota->jenisCuti->nama_cuti }}</td>
                                        <td>{{ $quota->durasi_cuti }} hari</td>
                                        @if (Auth::user()->role !== 'personil')
                                            <td class="text-center">
                                                <!-- Form untuk memperbarui kuota -->
                                                <form method="POST" action="{{ route('cuti.quota.update', $quota->id) }}" class="d-inline">
                                                    @csrf
                                                    <div class="input-group">
                                                        <input type="number" name="durasi_cuti" class="form-control" value="{{ $quota->durasi_cuti }}" min="0">
                                                        <button type="submit" class="btn btn-success btn-sm">Update</button>
                                                    </div>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ Auth::user()->role !== 'personil' ? 6 : 5 }}" class="text-center">Tidak ada data kuota cuti.</td>
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