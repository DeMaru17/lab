{{-- resources/views/vendors/create.blade.php --}}
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Tambah Vendor Baru</h3>
                    <p class="text-subtitle text-muted">Masukkan data untuk vendor baru.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('vendors.index') }}">Vendor</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Tambah Vendor</li>
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
                <h4 class="card-title">Form Tambah Vendor</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('vendors.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-8 col-12"> {{-- Lebarkan kolom nama --}}
                            <div class="form-group">
                                <label for="name">Nama Vendor <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Masukkan nama vendor" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                         <div class="col-md-4 col-12"> {{-- Kolom untuk logo --}}
                            <div class="form-group">
                                <label for="logo_image">Logo Vendor (Opsional)</label>
                                <input type="file" id="logo_image" name="logo_image" class="form-control @error('logo_image') is-invalid @enderror" accept="image/png, image/jpeg, image/jpg">
                                <small class="text-muted">Format: PNG, JPG/JPEG. Maks: 1MB.</small>
                                @error('logo_image')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12 d-flex justify-content-end">
                            <a href="{{ route('vendors.index') }}" class="btn btn-light-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan Vendor</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

</div>
@endsection
