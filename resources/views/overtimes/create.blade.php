{{-- resources/views/overtimes/create.blade.php --}}
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Ajukan Lembur</h3>
                    <p class="text-subtitle text-muted">Formulir untuk mengajukan lembur.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('overtimes.index') }}">Lembur</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Ajukan Lembur</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    {{-- Akhir Header Halaman --}}

    {{-- Tampilkan Warning Batas Lembur jika perlu --}}
    @if($showWarning ?? false)
        <div class="alert alert-light-warning color-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Perhatian!</strong> Total jam lembur Anda bulan ini ({{ round(($currentMonthTotal ?? 0) / 60, 1) }} jam) sudah mendekati atau melebihi batas 54 jam.
             <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Input Data Lembur</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('overtimes.store') }}" method="POST">
                    @csrf
                    <div class="row">
                        {{-- Kolom Kiri --}}
                        <div class="col-md-6">
                            {{-- Tampilkan dropdown user HANYA untuk Admin --}}
                            @if (Auth::user()->role === 'admin')
                                <div class="form-group">
                                    <label for="user_id">Pilih Karyawan <span class="text-danger">*</span></label>
                                    <select name="user_id" id="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                                        <option value="" disabled {{ old('user_id') ? '' : 'selected' }}>-- Pilih Karyawan --</option>
                                        @foreach ($users as $id => $name)
                                            <option value="{{ $id }}" {{ old('user_id') == $id ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('user_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                             <div class="form-group">
                                <label for="tanggal_lembur">Tanggal Lembur <span class="text-danger">*</span></label>
                                <input type="date" id="tanggal_lembur" name="tanggal_lembur" class="form-control @error('tanggal_lembur') is-invalid @enderror" value="{{ old('tanggal_lembur', date('Y-m-d')) }}" required>
                                @error('tanggal_lembur')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Kolom Kanan --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="jam_mulai">Jam Mulai <span class="text-danger">*</span></label>
                                <input type="time" id="jam_mulai" name="jam_mulai" class="form-control @error('jam_mulai') is-invalid @enderror" value="{{ old('jam_mulai') }}" required>
                                @error('jam_mulai')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="jam_selesai">Jam Selesai <span class="text-danger">*</span></label>
                                <input type="time" id="jam_selesai" name="jam_selesai" class="form-control @error('jam_selesai') is-invalid @enderror" value="{{ old('jam_selesai') }}" required>
                                @error('jam_selesai')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Uraian Pekerjaan --}}
                        <div class="col-12">
                             <div class="form-group">
                                <label for="uraian_pekerjaan">Uraian Pekerjaan <span class="text-danger">*</span></label>
                                <textarea name="uraian_pekerjaan" id="uraian_pekerjaan" rows="4" class="form-control @error('uraian_pekerjaan') is-invalid @enderror" placeholder="Jelaskan pekerjaan yang dilakukan saat lembur..." required>{{ old('uraian_pekerjaan') }}</textarea>
                                @error('uraian_pekerjaan')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12 d-flex justify-content-end">
                            <a href="{{ route('overtimes.index') }}" class="btn btn-light-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-primary">Ajukan Lembur</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

</div>
@endsection
