{{-- resources/views/perjalanan_dinas/create.blade.php --}}
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Tambah Perjalanan Dinas</h3>
                    <p class="text-subtitle text-muted">Formulir untuk mencatat data perjalanan dinas baru.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('perjalanan-dinas.index') }}">Perjalanan Dinas</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Tambah Data</li>
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
                <h4 class="card-title">Input Data Perjalanan Dinas</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('perjalanan-dinas.store') }}" method="POST">
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
                                        {{-- Loop data $users yang dikirim dari controller create --}}
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
                            {{-- Jika bukan admin, user_id tidak perlu diinput (otomatis di controller) --}}

                            <div class="form-group">
                                <label for="jurusan">Jurusan / Tujuan <span class="text-danger">*</span></label>
                                <input type="text" id="jurusan" name="jurusan" class="form-control @error('jurusan') is-invalid @enderror" value="{{ old('jurusan') }}" placeholder="Masukkan kota atau tujuan dinas" required>
                                @error('jurusan')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Kolom Kanan --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tanggal_berangkat">Tanggal Berangkat <span class="text-danger">*</span></label>
                                <input type="date" id="tanggal_berangkat" name="tanggal_berangkat" class="form-control @error('tanggal_berangkat') is-invalid @enderror" value="{{ old('tanggal_berangkat') }}" required>
                                @error('tanggal_berangkat')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="perkiraan_tanggal_pulang">Perkiraan Tanggal Pulang <span class="text-danger">*</span></label>
                                <input type="date" id="perkiraan_tanggal_pulang" name="perkiraan_tanggal_pulang" class="form-control @error('perkiraan_tanggal_pulang') is-invalid @enderror" value="{{ old('perkiraan_tanggal_pulang') }}" required>
                                @error('perkiraan_tanggal_pulang')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12 d-flex justify-content-end">
                            <a href="{{ route('perjalanan-dinas.index') }}" class="btn btn-light-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan Data</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

</div>
@endsection
