{{-- filepath: resources/views/cuti/quota/index.blade.php --}}
@extends('layout.app')

@section('content')
<div id="main">
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Kuota Cuti</h3>
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
    </div>

    {{-- Tabel daftar kuota cuti --}}
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Daftar Kuota Cuti</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    {{-- 2. Beri ID pada tabel agar bisa ditarget oleh Javascript --}}
                    <table class="table table-striped table-hover" id="tableCutiQuota">
                        <thead class="thead-dark">
                            <tr>
                                {{-- Sesuaikan header tabel --}}
                                <th>No</th>
                                @if (Auth::user()->role !== 'personil')
                                    <th>Nama</th>
                                    <th>Email</th>
                                @endif
                                <th>Jenis Cuti</th>
                                <th>Kuota Tersedia</th>
                                @if (Auth::user()->role !== 'personil')
                                    <th class="text-center">Aksi (Update Kuota)</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cutiQuota as $quota)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                     @if (Auth::user()->role !== 'personil')
                                        <td>{{ $quota->user->name }}</td>
                                        <td>{{ $quota->user->email }}</td>
                                     @endif
                                    <td>{{ $quota->jenisCuti->nama_cuti }}</td>
                                    <td>{{ $quota->durasi_cuti }} hari</td>
                                    @if (Auth::user()->role !== 'personil')
                                        <td class="text-center">
                                            {{-- Form update inline (seperti sebelumnya) --}}
                                            <form method="POST" action="{{ route('cuti-quota.update', $quota->id) }}" class="d-inline">
                                                @csrf
                                                @method('PUT') 
                                                <div class="input-group input-group-sm"> {{-- perkecil input group --}}
                                                    <input type="number" name="durasi_cuti" class="form-control" value="{{ $quota->durasi_cuti }}" min="0" style="max-width: 80px;"> {{-- batasi lebar input --}}
                                                    <button type="submit" class="btn btn-success btn-sm">Update</button>
                                                </div>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    {{-- Sesuaikan colspan --}}
                                    <td colspan="{{ Auth::user()->role !== 'personil' ? 6 : 3 }}" class="text-center">
                                        @if(request()->filled('search') && Auth::user()->role !== 'personil')
                                            Tidak ada data kuota cuti yang cocok dengan pencarian Anda.
                                        @elseif(Auth::user()->role !== 'personil')
                                             Tidak ada data kuota cuti untuk ditampilkan.
                                        @else
                                             Anda belum memiliki data kuota cuti.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>




@endsection





