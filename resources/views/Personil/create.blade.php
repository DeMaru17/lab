<!-- filepath: c:\xampp1\htdocs\lab\resources\views\Personil\create.blade.php -->
@extends('layout.app')

@section('content')
<div id="main">
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Tambah Personil</h3>
                    <p class="text-subtitle text-muted">Form untuk menambahkan data personil baru.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('personil.index') }}">Daftar Personil</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Tambah Personil</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Form Tambah Personil</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('personil.store') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label for="name">Nama</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="Masukkan nama" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Masukkan email" required>
                        </div>
                        <div class="form-group">
                            <label for="jenis_kelamin">Jenis Kelamin</label>
                            <select id="jenis_kelamin" name="jenis_kelamin" class="form-select" required>
                                <option value="" disabled selected>Pilih jenis kelamin</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="tanggal_mulai_bekerja">Tanggal Mulai Bekerja</label>
                            <input type="date" id="tanggal_mulai_bekerja" name="tanggal_mulai_bekerja" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password" required>
                        </div>
                        <div class="form-group">
                            <label for="password_confirmation">Konfirmasi Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Konfirmasi password" required>
                        </div>
                        <div class="form-group">
                            <label for="jabatan">Jabatan</label>
                            <select id="jabatan" name="jabatan" class="form-select" required>
                                <option value="" disabled selected>Pilih jabatan</option>
                                <option value="manager">Manager</option>
                                <option value="asisten manager analis">Asisten Manager Analis</option>
                                <option value="asisten manager preparator">Asisten Manager Preparator</option>
                                <option value="preparator">Preparator</option>
                                <option value="analis">Analis</option>
                                <option value="mekanik">Mekanik</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('personil.index') }}" class="btn btn-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection