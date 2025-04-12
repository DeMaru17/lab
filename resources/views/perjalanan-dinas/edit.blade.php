<!-- filepath: c:\xampp1\htdocs\lab\resources\views\perjalanan-dinas\edit.blade.php -->
@extends('layout.app')

@section('content')
<div id="main">
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Edit Perjalanan Dinas</h3>
                    <p class="text-subtitle text-muted">Form untuk mengedit data perjalanan dinas.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('perjalanan-dinas.index') }}">Perjalanan Dinas</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Form edit perjalanan dinas -->
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Form Edit Perjalanan Dinas</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('perjalanan-dinas.update', $perjalanan_dina->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Nama Personil -->
                        <div class="form-group">
                            <label for="user_id">Nama Personil</label>
                            <!-- Nama personil readonly untuk semua role -->
                            <input type="text" class="form-control" value="{{ $perjalanan_dina->user->name }}" readonly>
                            <input type="hidden" name="user_id" value="{{ $perjalanan_dina->user_id }}">
                        </div>

                        <!-- Tanggal Berangkat -->
                        <div class="form-group">
                            <label for="tanggal_berangkat">Tanggal Berangkat</label>
                            <input type="date" name="tanggal_berangkat" class="form-control" value="{{ old('tanggal_berangkat', \Carbon\Carbon::parse($perjalanan_dina->tanggal_berangkat)->format('Y-m-d')) }}">
                            @error('tanggal_berangkat')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Perkiraan Tanggal Pulang -->
                        <div class="form-group">
                            <label for="perkiraan_tanggal_pulang">Perkiraan Tanggal Pulang</label>
                            <input type="date" name="perkiraan_tanggal_pulang" class="form-control" value="{{ old('perkiraan_tanggal_pulang', \Carbon\Carbon::parse($perjalanan_dina->perkiraan_tanggal_pulang)->format('Y-m-d')) }}">
                            @error('perkiraan_tanggal_pulang')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Tanggal Pulang -->
                        <div class="form-group">
                            <label for="tanggal_pulang">Tanggal Pulang</label>
                            <input type="date" name="tanggal_pulang" class="form-control" value="{{ old('tanggal_pulang', optional($perjalanan_dina->tanggal_pulang)->format('Y-m-d')) }}">
                            @error('tanggal_pulang')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Jurusan -->
                        <div class="form-group">
                            <label for="jurusan">Jurusan (Tujuan)</label>
                            <input type="text" name="jurusan" id="jurusan" class="form-control @error('jurusan') is-invalid @enderror" value="{{ old('jurusan', $perjalanan_dina->jurusan) }}" placeholder="Masukkan tujuan perjalanan dinas">
                            @error('jurusan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Tombol Simpan dan Batal -->
                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('perjalanan-dinas.index') }}" class="btn btn-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection