{{-- resources/views/perjalanan_dinas/edit.blade.php --}}
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@section('content')
<div id="main">
    {{-- Header Halaman & Breadcrumb --}}
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Edit Perjalanan Dinas</h3>
                    {{-- Gunakan $perjalananDina --}}
                    <p class="text-subtitle text-muted">Ubah data perjalanan dinas untuk: {{ $perjalananDina->user->name ?? 'N/A' }}</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('perjalanan-dinas.index') }}">Perjalanan Dinas</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit Data</li>
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
                <h4 class="card-title">Form Edit Perjalanan Dinas</h4>
            </div>
            <div class="card-body">
                {{-- Gunakan $perjalananDina untuk route --}}
                <form action="{{ route('perjalanan-dinas.update', $perjalananDina->id) }}" method="POST">
                    @csrf
                    @method('PUT') {{-- Atau PATCH --}}

                    <div class="row">
                        {{-- Kolom Kiri --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Karyawan</label>
                                {{-- Gunakan $perjalananDina --}}
                                <input type="text" class="form-control" value="{{ $perjalananDina->user->name ?? 'N/A' }}" readonly disabled>
                            </div>

                            <div class="form-group">
                                <label for="jurusan">Jurusan / Tujuan <span class="text-danger">*</span></label>
                                {{-- Gunakan $perjalananDina --}}
                                <input type="text" id="jurusan" name="jurusan" class="form-control @error('jurusan') is-invalid @enderror" value="{{ old('jurusan', $perjalananDina->jurusan) }}" required>
                                @error('jurusan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group">
                                <label for="status">Status Perjalanan <span class="text-danger">*</span></label>
                                {{-- Gunakan $perjalananDina --}}
                                <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                                    <option value="berlangsung" {{ old('status', $perjalananDina->status) == 'berlangsung' ? 'selected' : '' }}>Berlangsung</option>
                                    <option value="selesai" {{ old('status', $perjalananDina->status) == 'selesai' ? 'selected' : '' }}>Selesai</option>
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Kolom Kanan --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tanggal_berangkat">Tanggal Berangkat <span class="text-danger">*</span></label>
                                {{-- Gunakan $perjalananDina --}}
                                <input type="date" id="tanggal_berangkat" name="tanggal_berangkat" class="form-control @error('tanggal_berangkat') is-invalid @enderror" value="{{ old('tanggal_berangkat', $perjalananDina->tanggal_berangkat ? $perjalananDina->tanggal_berangkat->format('Y-m-d') : '') }}" required>
                                @error('tanggal_berangkat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group">
                                <label for="perkiraan_tanggal_pulang">Perkiraan Tanggal Pulang <span class="text-danger">*</span></label>
                                {{-- Gunakan $perjalananDina --}}
                                <input type="date" id="perkiraan_tanggal_pulang" name="perkiraan_tanggal_pulang" class="form-control @error('perkiraan_tanggal_pulang') is-invalid @enderror" value="{{ old('perkiraan_tanggal_pulang', $perjalananDina->perkiraan_tanggal_pulang ? $perjalananDina->perkiraan_tanggal_pulang->format('Y-m-d') : '') }}" required>
                                @error('perkiraan_tanggal_pulang') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                             <div class="form-group">
                                <label for="tanggal_pulang">Tanggal Pulang Aktual (Isi jika sudah selesai)</label>
                                {{-- Gunakan $perjalananDina --}}
                                <input type="date" id="tanggal_pulang" name="tanggal_pulang" class="form-control @error('tanggal_pulang') is-invalid @enderror" value="{{ old('tanggal_pulang', $perjalananDina->tanggal_pulang ? $perjalananDina->tanggal_pulang->format('Y-m-d') : '') }}">
                                @error('tanggal_pulang') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12 d-flex justify-content-end">
                            <a href="{{ route('perjalanan-dinas.index') }}" class="btn btn-light-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

</div>
@endsection
