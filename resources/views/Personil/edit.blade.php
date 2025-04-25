@extends('layout.app')

@section('content')
<div id="main">
    <div class="page-heading">
        <div class="page-title">
            {{-- ... (Judul Halaman & Breadcrumb seperti sebelumnya) ... --}}
            <h3>Edit Personil/User</h3>
            <p class="text-subtitle text-muted">Form untuk mengedit data personil/user.</p>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Form Edit: {{ $user->name }}</h4>
            </div>
            <div class="card-body">
                {{-- PENTING: Tambahkan enctype untuk upload file --}}
                <form action="{{ route('personil.update', $user->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    {{-- === Bagian Data Diri (Contoh, sesuaikan field Anda) === --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Nama</label>
                                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" placeholder="Masukkan nama" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                             <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" placeholder="Masukkan email" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="jenis_kelamin">Jenis Kelamin</label>
                                <select id="jenis_kelamin" name="jenis_kelamin" class="form-select @error('jenis_kelamin') is-invalid @enderror" required>
                                    <option value="" >-- Pilih --</option>
                                    <option value="Laki-laki" {{ old('jenis_kelamin', $user->jenis_kelamin) == 'Laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                                    <option value="Perempuan" {{ old('jenis_kelamin', $user->jenis_kelamin) == 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                                </select>
                                @error('jenis_kelamin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                         <div class="col-md-6">
                             <div class="form-group">
                                <label for="tanggal_mulai_bekerja">Tanggal Mulai Bekerja</label>
                                <input type="date" id="tanggal_mulai_bekerja" name="tanggal_mulai_bekerja" class="form-control @error('tanggal_mulai_bekerja') is-invalid @enderror" value="{{ old('tanggal_mulai_bekerja', $user->tanggal_mulai_bekerja ? $user->tanggal_mulai_bekerja->format('Y-m-d') : '') }}" required>
                                @error('tanggal_mulai_bekerja')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                         </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="jabatan">Jabatan</label>
                                <select id="jabatan" name="jabatan" class="form-select @error('jabatan') is-invalid @enderror" required>
                                     {{-- Isi dengan opsi jabatan Anda --}}
                                    <option value="manager" {{ old('jabatan', $user->jabatan) == 'manager' ? 'selected' : '' }}>Manager</option>
                                    <option value="asisten manager analis" {{ old('jabatan', $user->jabatan) == 'asisten manager analis' ? 'selected' : '' }}>Asisten Manager Analis</option>
                                    <option value="asisten manager preparator" {{ old('jabatan', $user->jabatan) == 'asisten manager preparator' ? 'selected' : '' }}>Asisten Manager Preparator</option>
                                    <option value="preparator" {{ old('jabatan', $user->jabatan) == 'preparator' ? 'selected' : '' }}>Preparator</option>
                                    <option value="analis" {{ old('jabatan', $user->jabatan) == 'analis' ? 'selected' : '' }}>Analis</option>
                                    <option value="mekanik" {{ old('jabatan', $user->jabatan) == 'mekanik' ? 'selected' : '' }}>Mekanik</option>
                                    <option value="admin" {{ old('jabatan', $user->jabatan) == 'admin' ? 'selected' : '' }}>Admin</option>
                                </select>
                                @error('jabatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="vendor_id">Vendor Outsourcing</label>
                                {{-- Ganti input jadi select --}}
                                <select id="vendor_id" name="vendor_id" class="form-select @error('vendor_id') is-invalid @enderror">
                                    <option value="">-- Pilih Vendor (jika ada) --</option> {{-- Opsi default kosong --}}
                                    @foreach($vendors as $vendor) {{-- Loop dari data controller --}}
                                        <option value="{{ $vendor->id }}" {{ old('vendor_id', $user->vendor_id) == $vendor->id ? 'selected' : '' }}>
                                            {{ $vendor->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('vendor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    {{-- === Akhir Bagian Data Diri === --}}

                    <hr> {{-- Pemisah --}}

                    {{-- === Bagian Upload Tanda Tangan === --}}
                    <div class="row">
                        <div class="col-12">
                            <h5>Pengaturan Tanda Tangan Digital</h5>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label>Tanda Tangan Saat Ini:</label><br>
                                @if ($user->signature_path)
                                    {{-- Tampilkan gambar TTD jika ada --}}
                                    <img src="{{ asset('storage/' . $user->signature_path) }}" alt="Tanda Tangan {{ $user->name }}" style="max-height: 80px; height: auto; border: 1px solid #ddd; margin-bottom: 10px; background-color: #fff;">
                                    <br><small class="text-muted">Kosongkan input unggah jika tidak ingin mengganti.</small>
                                @else
                                    <p class="text-muted mb-0">Belum ada tanda tangan tersimpan.</p>
                                @endif
                            </div>
                        </div>
                         <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="signature_image">Unggah Tanda Tangan Baru (Format: PNG, JPG)</label>
                                {{-- Input file untuk TTD baru --}}
                                <input type="file" id="signature_image" name="signature_image" class="form-control @error('signature_image') is-invalid @enderror" accept="image/png, image/jpeg, image/jpg">
                                <small class="text-muted">Rekomendasi: Gambar TTD dengan background transparan (PNG) atau putih bersih. Maks: 1MB.</small>
                                @error('signature_image')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                         </div>
                    </div>
                     {{-- === Akhir Bagian Upload Tanda Tangan === --}}


                    <hr> {{-- Pemisah --}}

                    {{-- === Bagian Ganti Password (Opsional) === --}}
                    <div class="row">
                        <div class="col-12">
                            <h5>Ganti Password (Opsional)</h5>
                             <p class="text-muted"><small>Kosongkan field password jika Anda tidak ingin mengganti password.</small></p>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password">Password Baru</label>
                                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Masukkan password baru">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                         <div class="col-md-6">
                             <div class="form-group">
                                <label for="password_confirmation">Konfirmasi Password Baru</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Konfirmasi password baru">
                                {{-- Error konfirmasi biasanya ditampilkan di bawah field password utama oleh Laravel --}}
                            </div>
                        </div>
                    </div>
                    {{-- === Akhir Bagian Ganti Password === --}}


                    <div class="row mt-3">
                        <div class="col-12 d-flex justify-content-end">
                            <a href="{{ route('personil.index') }}" class="btn btn-light-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>
@endsection