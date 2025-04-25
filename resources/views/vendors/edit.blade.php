{{-- resources/views/vendors/edit.blade.php --}}
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Edit Vendor</h3>
                    <p class="text-subtitle text-muted">Ubah data untuk vendor: {{ $vendor->name }}.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('vendors.index') }}">Vendor</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit Vendor</li>
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
                <h4 class="card-title">Form Edit Vendor</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('vendors.update', $vendor->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT') {{-- Atau PATCH, sesuaikan dengan route Anda --}}

                    <div class="row">
                        {{-- Kolom Nama Vendor --}}
                        <div class="col-md-8 col-12">
                            <div class="form-group">
                                <label for="name">Nama Vendor <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $vendor->name) }}" placeholder="Masukkan nama vendor" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        {{-- Kolom Logo --}}
                         <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label>Logo Saat Ini:</label><br>
                                @if ($vendor->logo_path && Storage::disk('public')->exists($vendor->logo_path))
                                    <img src="{{ asset('storage/' . $vendor->logo_path) }}" alt="Logo {{ $vendor->name }}" style="max-height: 50px; height: auto; border: 1px solid #ddd; margin-bottom: 10px; background-color: #fff;">
                                    <br><small class="text-muted">Kosongkan input unggah jika tidak ingin mengganti.</small>
                                @else
                                    <p class="text-muted mb-0">Belum ada logo.</p>
                                @endif
                            </div>
                            <div class="form-group">
                                <label for="logo_image">Unggah Logo Baru (Opsional)</label>
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
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

</div>
@endsection
