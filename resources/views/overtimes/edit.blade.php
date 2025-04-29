{{-- resources/views/overtimes/edit.blade.php --}}
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Edit Pengajuan Lembur</h3>
                    <p class="text-subtitle text-muted">Ubah data lembur untuk: {{ $overtime->user->name ?? 'N/A' }}</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('overtimes.index') }}">Lembur</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit Lembur</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    {{-- Akhir Header Halaman --}}

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Form Edit Lembur</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('overtimes.update', $overtime->id) }}" method="POST">
                    @csrf
                    @method('PUT') {{-- Atau PATCH --}}

                    <div class="row">
                        {{-- Kolom Kiri --}}
                        <div class="col-md-6">
                            {{-- Tampilkan Nama Karyawan (read-only) --}}
                            <div class="form-group">
                                <label>Nama Karyawan</label>
                                <input type="text" class="form-control" value="{{ $overtime->user->name ?? 'N/A' }}" readonly disabled>
                            </div>

                            <div class="form-group">
                                <label for="tanggal_lembur">Tanggal Lembur <span class="text-danger">*</span></label>
                                <input type="date" id="tanggal_lembur" name="tanggal_lembur" class="form-control @error('tanggal_lembur') is-invalid @enderror" value="{{ old('tanggal_lembur', $overtime->tanggal_lembur ? $overtime->tanggal_lembur->format('Y-m-d') : '') }}" required>
                                @error('tanggal_lembur')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Kolom Kanan --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="jam_mulai">Jam Mulai <span class="text-danger">*</span></label>
                                {{-- Format H:i untuk input type time --}}
                                <input type="time" id="jam_mulai" name="jam_mulai" class="form-control @error('jam_mulai') is-invalid @enderror" value="{{ old('jam_mulai', $overtime->jam_mulai ? $overtime->jam_mulai->format('H:i') : '') }}" required>
                                @error('jam_mulai')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="jam_selesai">Jam Selesai <span class="text-danger">*</span></label>
                                <input type="time" id="jam_selesai" name="jam_selesai" class="form-control @error('jam_selesai') is-invalid @enderror" value="{{ old('jam_selesai', $overtime->jam_selesai ? $overtime->jam_selesai->format('H:i') : '') }}" required>
                                @error('jam_selesai')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Uraian Pekerjaan --}}
                        <div class="col-12">
                             <div class="form-group">
                                <label for="uraian_pekerjaan">Uraian Pekerjaan <span class="text-danger">*</span></label>
                                <textarea name="uraian_pekerjaan" id="uraian_pekerjaan" rows="4" class="form-control @error('uraian_pekerjaan') is-invalid @enderror" required>{{ old('uraian_pekerjaan', $overtime->uraian_pekerjaan) }}</textarea>
                                @error('uraian_pekerjaan')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12 d-flex justify-content-end">
                            <a href="{{ route('overtimes.index') }}" class="btn btn-light-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

</div>
@endsection
