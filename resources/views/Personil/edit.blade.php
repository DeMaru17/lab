<!-- filepath: c:\xampp1\htdocs\lab\resources\views\Personil\edit.blade.php -->
@extends('layout.app')

@section('content')
<div id="main">
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Edit Personil</h3>
                    <p class="text-subtitle text-muted">Form untuk mengedit data personil.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('personil.index') }}">Daftar Personil</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit Personil</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Form Edit Personil</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('personil.update', $user->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="form-group">
                            <label for="name">Nama</label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ $user->name }}" placeholder="Masukkan nama" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" value="{{ $user->email }}" placeholder="Masukkan email" required>
                        </div>
                        <div class="form-group">
                            <label for="jenis_kelamin">Jenis Kelamin</label>
                            <select id="jenis_kelamin" name="jenis_kelamin" class="form-select" required>
                                <option value="" >Pilih jenis kelamin</option>
                                <option value="Laki-laki" {{ $user->jenis_kelamin == 'Laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                                <option value="Perempuan" {{ $user->jenis_kelamin == 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="jabatan">Jabatan</label>
                            <select id="jabatan" name="jabatan" class="form-select" required>
                                <option value="manager" {{ $user->jabatan == 'manager' ? 'selected' : '' }}>Manager</option>
                                <option value="asisten manager analis" {{ $user->jabatan == 'asisten manager analis' ? 'selected' : '' }}>Asisten Manager Analis</option>
                                <option value="asisten manager preparator" {{ $user->jabatan == 'asisten manager preparator' ? 'selected' : '' }}>Asisten Manager Preparator</option>
                                <option value="preparator" {{ $user->jabatan == 'preparator' ? 'selected' : '' }}>Preparator</option>
                                <option value="analis" {{ $user->jabatan == 'analis' ? 'selected' : '' }}>Analis</option>
                                <option value="mekanik" {{ $user->jabatan == 'mekanik' ? 'selected' : '' }}>Mekanik</option>
                                <option value="admin" {{ $user->jabatan == 'admin' ? 'selected' : '' }}>Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="password">Password (Opsional)</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password baru">
                        </div>
                        <div class="form-group">
                            <label for="password_confirmation">Konfirmasi Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Konfirmasi password baru">
                        </div>
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('personil.index') }}" class="btn btn-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection