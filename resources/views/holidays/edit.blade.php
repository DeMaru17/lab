{{-- resources/views/holidays/edit.blade.php --}}
@extends('layout.app')

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Edit Hari Libur</h3>
                    <p class="text-subtitle text-muted">Ubah data untuk tanggal: {{ $holiday->tanggal->format('d F Y') }}.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('holidays.index') }}">Hari Libur</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit</li>
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
                <h4 class="card-title">Form Edit Hari Libur</h4>
            </div>
            <div class="card-body">
                {{-- Arahkan ke route update dengan parameter tanggal --}}
                <form action="{{ route('holidays.update', $holiday->tanggal->format('Y-m-d')) }}" method="POST">
                    @csrf
                    @method('PUT') {{-- Gunakan PUT atau PATCH --}}

                    <div class="row">
                        <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label for="tanggal">Tanggal Libur <span class="text-danger">*</span></label>
                                {{-- Isi value dengan data lama --}}
                                <input type="date" id="tanggal" name="tanggal" class="form-control @error('tanggal') is-invalid @enderror" value="{{ old('tanggal', $holiday->tanggal->format('Y-m-d')) }}" required>
                                @error('tanggal')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-8 col-12">
                            <div class="form-group">
                                <label for="nama_libur">Nama Hari Libur / Keterangan <span class="text-danger">*</span></label>
                                {{-- Isi value dengan data lama --}}
                                <input type="text" id="nama_libur" name="nama_libur" class="form-control @error('nama_libur') is-invalid @enderror" value="{{ old('nama_libur', $holiday->nama_libur) }}" required>
                                @error('nama_libur')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12 d-flex justify-content-end">
                             {{-- Kembali ke index dengan filter tahun yang sama --}}
                            <a href="{{ route('holidays.index', ['year' => $holiday->tanggal->year]) }}" class="btn btn-light-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

</div>
@endsection
