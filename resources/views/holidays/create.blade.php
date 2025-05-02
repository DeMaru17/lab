{{-- resources/views/holidays/create.blade.php --}}
@extends('layout.app')

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Tambah Hari Libur</h3>
                    <p class="text-subtitle text-muted">Masukkan data hari libur nasional atau cuti bersama baru.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('holidays.index') }}">Hari Libur</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Tambah</li>
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
                <h4 class="card-title">Form Tambah Hari Libur</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('holidays.store') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label for="tanggal">Tanggal Libur <span class="text-danger">*</span></label>
                                <input type="date" id="tanggal" name="tanggal" class="form-control @error('tanggal') is-invalid @enderror" value="{{ old('tanggal') }}" required>
                                @error('tanggal')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-8 col-12">
                            <div class="form-group">
                                <label for="nama_libur">Nama Hari Libur / Keterangan <span class="text-danger">*</span></label>
                                <input type="text" id="nama_libur" name="nama_libur" class="form-control @error('nama_libur') is-invalid @enderror" value="{{ old('nama_libur') }}" placeholder="Contoh: Hari Raya Idul Fitri (Hari ke-1)" required>
                                @error('nama_libur')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12 d-flex justify-content-end">
                            <a href="{{ route('holidays.index') }}" class="btn btn-light-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan Hari Libur</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

</div>
@endsection
