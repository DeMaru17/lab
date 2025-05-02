{{-- resources/views/profile/edit.blade.php --}}
@extends('layout.app')

@section('content')
    <div id="main">
        {{-- Header Halaman & Breadcrumb --}}
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        {{-- Ubah Judul --}}
                        <h3>Profil Saya</h3>
                        <p class="text-subtitle text-muted">Ubah data profil dan password Anda.</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Profil Saya</li>
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
                    <h4 class="card-title">Edit Profil</h4>
                </div>
                <div class="card-body">
                    {{-- Ubah action ke route profile.update --}}
                    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT') {{-- Gunakan PUT atau PATCH --}}

                        {{-- === Bagian Data Diri === --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Nama <span class="text-danger">*</span></label>
                                    <input type="text" id="name" name="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name', $user->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Email <span class="text-danger">*</span></label>
                                    <input type="email" id="email" name="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        value="{{ old('email', $user->email) }}" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="jenis_kelamin">Jenis Kelamin <span class="text-danger">*</span></label>
                                    <select id="jenis_kelamin" name="jenis_kelamin"
                                        class="form-select @error('jenis_kelamin') is-invalid @enderror" required>
                                        <option value="">-- Pilih --</option>
                                        <option value="Laki-laki"
                                            {{ old('jenis_kelamin', $user->jenis_kelamin) == 'Laki-laki' ? 'selected' : '' }}>
                                            Laki-laki</option>
                                        <option value="Perempuan"
                                            {{ old('jenis_kelamin', $user->jenis_kelamin) == 'Perempuan' ? 'selected' : '' }}>
                                            Perempuan</option>
                                    </select>
                                    @error('jenis_kelamin')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            {{-- Hapus Tanggal Mulai Bekerja, Jabatan, Vendor dari form ini --}}
                        </div>
                        {{-- === Akhir Bagian Data Diri === --}}

                        <hr>

                        {{-- === Bagian Upload Tanda Tangan (Sama seperti di Personil/edit) === --}}
                        <div class="row">
                            <div class="col-12">
                                <h5>Pengaturan Tanda Tangan Digital</h5>
                            </div>
                            <div class="col-md-6 col-12">
                                <div class="form-group">
                                    <label>Tanda Tangan Saat Ini:</label><br>
                                    @if ($user->signature_path && Storage::disk('public')->exists($user->signature_path))
                                        <img src="{{ asset('storage/' . $user->signature_path) }}"
                                            alt="Tanda Tangan {{ $user->name }}"
                                            style="max-height: 80px; height: auto; border: 1px solid #ddd; margin-bottom: 10px; background-color: #fff;">
                                        <br><small class="text-muted">Kosongkan input unggah jika tidak ingin
                                            mengganti.</small>
                                    @else
                                        <p class="text-muted mb-0">Belum ada tanda tangan tersimpan.</p>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6 col-12">
                                <div class="form-group">
                                    <label for="signature_image">Unggah Tanda Tangan Baru (Format: PNG, JPG)</label>
                                    <input type="file" id="signature_image" name="signature_image"
                                        class="form-control @error('signature_image') is-invalid @enderror"
                                        accept="image/png, image/jpeg, image/jpg">
                                    <small class="text-muted">Rekomendasi: Gambar TTD dengan background transparan (PNG)
                                        atau putih bersih. Maks: 1MB.</small>
                                    @error('signature_image')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        {{-- === Akhir Bagian Upload Tanda Tangan === --}}

                        <hr>

                        {{-- === Bagian Ganti Password (Sama seperti di Personil/edit) === --}}
                        <div class="row">
                            <div class="col-12">
                                <h5>Ganti Password (Opsional)</h5>
                                <p class="text-muted"><small>Kosongkan field password jika Anda tidak ingin mengganti
                                        password.</small></p>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password">Password Baru</label>
                                    <input type="password" id="password" name="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        placeholder="Min. 8 karakter">
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password_confirmation">Konfirmasi Password Baru</label>
                                    <input type="password" id="password_confirmation" name="password_confirmation"
                                        class="form-control" placeholder="Konfirmasi password baru">
                                </div>
                            </div>
                        </div>
                        {{-- === Akhir Bagian Ganti Password === --}}

                        <div class="row mt-3">
                            <div class="col-12 d-flex justify-content-end">
                                {{-- Ubah teks tombol --}}
                                <button type="submit" class="btn btn-primary">Simpan Profil</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
