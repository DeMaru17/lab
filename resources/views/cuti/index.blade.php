{{-- filepath: resources/views/cuti/index.blade.php --}}
@extends('layout.app')

@section('content')
<div id="main">
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Daftar Cuti</h3>
                    <p class="text-subtitle text-muted">Lihat semua pengajuan cuti di sini.</p>
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

        <!-- Tabel daftar cuti -->
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Daftar Pengajuan Cuti</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Jenis Cuti</th>
                                    <th>Mulai</th>
                                    <th>Selesai</th>
                                    <th>Lama Cuti</th>
                                    <th>Keperluan</th>
                                    <th>Kuota Tersisa</th>
                                    <th>Status</th>
                                    @if (auth()->user()->role === 'manajemen')
                                        <th>Aksi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($cuti as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $item->user->name ?? 'User tidak ditemukan' }}</td>
                                        <td>{{ $item->jenisCuti->nama_cuti }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item->mulai_cuti)->format('d-m-Y') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item->selesai_cuti)->format('d-m-Y') }}</td>
                                        <td>{{ $item->lama_cuti }} hari</td>
                                        <td><textarea name="" id="" cols="10" rows="5">{{ $item->keperluan }}</textarea></td>
                                        <td>
                                            {{-- Tampilkan kuota cuti tersisa berdasarkan jenis cuti --}}
                                            {{ $cutiQuota[$item->jenis_cuti_id]->durasi_cuti ?? '0' }} hari
                                        </td>
                                        <td>
                                            @if ($item->status === 'pending')
                                                <span class="badge bg-warning">Pending</span>
                                            @elseif ($item->status === 'approved')
                                                <span class="badge bg-success">Approved</span>
                                            @else
                                                <span class="badge bg-danger">Rejected</span>
                                            @endif
                                        </td>
                                        @if (auth()->user()->role === 'manajemen')
                                            <td>
                                                <form action="#" method="POST" style="display: inline;">
                                                    {{-- {{ route('cuti.updateStatus', $item->id) }} --}}
                                                    @csrf
                                                    @method('PATCH')
                                                    <select name="status" class="form-select form-select-sm " onchange="this.form.submit()">
                                                        <option value="approved" {{ $item->status === 'approved' ? 'selected' : '' }}>Approve</option>
                                                        <option value="pending" {{ $item->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                                        <option value="rejected" {{ $item->status === 'rejected' ? 'selected' : '' }}>Reject</option>
                                                    </select>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center">Belum ada pengajuan cuti.</td>
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