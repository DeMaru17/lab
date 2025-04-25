@extends('layout.app')

@section('content')
<div id="main">
    <div class="page-heading">
       {{-- ... Page Title / Breadcrumb ... --}}
       <h3>Tambah Personil</h3>
       <p class="text-subtitle text-muted">Form untuk menambahkan data personil baru.</p>
       {{-- ... End Page Title / Breadcrumb ... --}}
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Form Tambah Personil</h4>
            </div>
            <div class="card-body">
                 {{-- TAMBAHKAN enctype di sini --}}
                <form action="{{ route('personil.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        {{-- Kolom Kiri (Nama, Email, JK, Tgl Mulai) --}}
                        <div class="col-md-6">
                           {{-- ... (Input Name, Email, Jenis Kelamin, Tanggal Mulai Bekerja seperti sebelumnya) ... --}}
                           <div class="form-group">
                                <label for="name">Nama <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Masukkan nama" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group">
                                <label for="email">Email <span class="text-danger">*</span></label>
                                <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="Masukkan email" required>
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group">
                                <label for="jenis_kelamin">Jenis Kelamin <span class="text-danger">*</span></label>
                                <select id="jenis_kelamin" name="jenis_kelamin" class="form-select @error('jenis_kelamin') is-invalid @enderror" required>
                                    <option value="" disabled {{ old('jenis_kelamin') ? '' : 'selected' }}>-- Pilih --</option>
                                    <option value="Laki-laki" {{ old('jenis_kelamin') == 'Laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                                    <option value="Perempuan" {{ old('jenis_kelamin') == 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                                </select>
                                @error('jenis_kelamin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                             <div class="form-group">
                                <label for="tanggal_mulai_bekerja">Tanggal Mulai Bekerja <span class="text-danger">*</span></label>
                                <input type="date" id="tanggal_mulai_bekerja" name="tanggal_mulai_bekerja" class="form-control @error('tanggal_mulai_bekerja') is-invalid @enderror" value="{{ old('tanggal_mulai_bekerja') }}" required>
                                @error('tanggal_mulai_bekerja') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Kolom Kanan (Password, Jabatan, Vendor, Signature) --}}
                        <div class="col-md-6">
                             <div class="form-group">
                                <label for="password">Password <span class="text-danger">*</span></label>
                                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Min. 8 karakter" required>
                                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group">
                                <label for="password_confirmation">Konfirmasi Password <span class="text-danger">*</span></label>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Ulangi password" required>
                            </div>
                            <div class="form-group">
                                <label for="jabatan">Jabatan <span class="text-danger">*</span></label>
                                <select id="jabatan" name="jabatan" class="form-select @error('jabatan') is-invalid @enderror" required>
                                     <option value="" disabled {{ old('jabatan') ? '' : 'selected' }}>-- Pilih Jabatan --</option>
                                     {{-- Opsi Jabatan --}}
                                     <option value="manager" {{ old('jabatan') == 'manager' ? 'selected' : '' }}>Manager</option>
                                     <option value="asisten manager analis" {{ old('jabatan') == 'asisten manager analis' ? 'selected' : '' }}>Asisten Manager Analis</option>
                                     <option value="asisten manager preparator" {{ old('jabatan') == 'asisten manager preparator' ? 'selected' : '' }}>Asisten Manager Preparator</option>
                                     <option value="preparator" {{ old('jabatan') == 'preparator' ? 'selected' : '' }}>Preparator</option>
                                     <option value="analis" {{ old('jabatan') == 'analis' ? 'selected' : '' }}>Analis</option>
                                     <option value="mekanik" {{ old('jabatan') == 'mekanik' ? 'selected' : '' }}>Mekanik</option>
                                     <option value="admin" {{ old('jabatan') == 'admin' ? 'selected' : '' }}>Admin</option>
                                </select>
                                @error('jabatan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group">
                                <label for="vendor_id">Vendor (Kosongkan jika Karyawan Internal)</label>
                                <select id="vendor_id" name="vendor_id" class="form-select @error('vendor_id') is-invalid @enderror">
                                    <option value="">-- Pilih Vendor (jika ada) --</option>
                                    @foreach($vendors as $vendor) {{-- Loop dari data controller --}}
                                        <option value="{{ $vendor->id }}" {{ old('vendor_id') == $vendor->id ? 'selected' : '' }}>
                                            {{ $vendor->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('vendor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            {{-- === TAMBAHKAN INPUT TANDA TANGAN === --}}
                            <div class="form-group">
                                <label for="signature_image">Unggah Tanda Tangan (Opsional)</label>
                                <input type="file" id="signature_image" name="signature_image" class="form-control @error('signature_image') is-invalid @enderror" accept="image/png, image/jpeg, image/jpg">
                                <small class="text-muted">Format: PNG, JPG/JPEG. Maks: 1MB.</small>
                                @error('signature_image')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            {{-- === AKHIR INPUT TANDA TANGAN === --}}
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <a href="{{ route('personil.index') }}" class="btn btn-light-secondary me-2">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>
@endsection