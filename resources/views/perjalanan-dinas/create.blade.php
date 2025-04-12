<!-- filepath: c:\xampp1\htdocs\lab\resources\views\perjalanan-dinas\create.blade.php -->
@extends('layout.app')

@section('content')
<div id="main">
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Tambah Perjalanan Dinas</h3>
                    <p class="text-subtitle text-muted">Form untuk menambahkan data perjalanan dinas baru.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('perjalanan-dinas.index') }}">Perjalanan Dinas</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Tambah</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Form tambah perjalanan dinas -->
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Form Tambah Perjalanan Dinas</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('perjalanan-dinas.store') }}" method="POST">
                        @csrf

                        <!-- Nama Personil -->
                        <div class="form-group">
                            <label for="user_id">Nama Personil</label>
                            @if(Auth::user()->role === 'personil')
                                <!-- Jika personil, tampilkan nama mereka dalam input readonly -->
                                <input type="text" class="form-control" value="{{ Auth::user()->name }}" readonly>
                                <input type="hidden" name="user_id" value="{{ Auth::user()->id }}">
                            @else
                                <!-- Jika admin atau manajemen, tampilkan dropdown -->
                                <select name="user_id" id="user_id" class="form-control @error('user_id') is-invalid @enderror">
                                    <option value="">-- Pilih Personil --</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>

                        <!-- Tanggal Berangkat -->
                        <div class="form-group">
                            <label for="tanggal_berangkat">Tanggal Berangkat</label>
                            <input type="date" name="tanggal_berangkat" id="tanggal_berangkat" class="form-control @error('tanggal_berangkat') is-invalid @enderror" value="{{ old('tanggal_berangkat') }}">
                            @error('tanggal_berangkat')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Perkiraan Tanggal Pulang -->
                        <div class="form-group">
                            <label for="perkiraan_tanggal_pulang">Perkiraan Tanggal Pulang</label>
                            <input type="date" name="perkiraan_tanggal_pulang" id="perkiraan_tanggal_pulang" class="form-control @error('perkiraan_tanggal_pulang') is-invalid @enderror" value="{{ old('perkiraan_tanggal_pulang') }}">
                            @error('perkiraan_tanggal_pulang')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Jurusan -->
                        <div class="form-group">
                            <label for="jurusan">Jurusan (Tujuan)</label>
                            <input type="text" name="jurusan" id="jurusan" class="form-control @error('jurusan') is-invalid @enderror" value="{{ old('jurusan') }}" placeholder="Masukkan tujuan perjalanan dinas">
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