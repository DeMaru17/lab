@extends('layout.app')

@section('content')
<div id="main">
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    {{-- Ubah Judul --}}
                    <h3>Edit Pengajuan Cuti</h3>
                    <p class="text-subtitle text-muted">Perbaiki detail pengajuan cuti Anda.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                             <li class="breadcrumb-item"><a href="{{ route('cuti.index') }}">Daftar Cuti</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit Cuti</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Form Edit Pengajuan Cuti</h4>
            </div>
            <div class="card-body">
                {{-- Tampilkan Alasan Penolakan Sebelumnya (jika statusnya rejected) --}}
                @if ($cuti->status == 'rejected' && $cuti->notes)
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                         <strong><i class="bi bi-exclamation-triangle-fill"></i> Alasan Penolakan Sebelumnya:</strong><br>
                         {{ $cuti->notes }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                {{-- Menampilkan Error Umum --}}
                @if ($errors->has('error'))
                    <div class="alert alert-danger">
                        {{ $errors->first('error') }}
                    </div>
                @endif

                 {{-- Ubah action ke route update dan method ke PUT/PATCH --}}
                <form action="{{ route('cuti.update', $cuti->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH') {{-- Atau PUT --}}

                    <div class="row">
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="jenis_cuti_id">Jenis Cuti <span class="text-danger">*</span></label>
                                {{-- Isi value/selected berdasarkan $cuti --}}
                                <select name="jenis_cuti_id" id="jenis_cuti_id" class="form-select @error('jenis_cuti_id') is-invalid @enderror" required>
                                    <option value="" disabled>-- Pilih Jenis Cuti --</option>
                                    @foreach ($jenisCuti as $jenis)
                                        <option value="{{ $jenis->id }}"
                                                data-nama="{{ strtolower($jenis->nama_cuti) }}"
                                                {{ old('jenis_cuti_id', $cuti->jenis_cuti_id) == $jenis->id ? 'selected' : '' }}>
                                            {{ $jenis->nama_cuti }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('jenis_cuti_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="kuota_cuti">Sisa Kuota Tersedia</label>
                                <input type="text" id="kuota_cuti" class="form-control" value="-" readonly style="background-color: #e9ecef;">
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                             <div class="form-group">
                                <label for="mulai_cuti">Mulai Cuti <span class="text-danger">*</span></label>
                                {{-- Isi value berdasarkan $cuti --}}
                                <input type="date" name="mulai_cuti" id="mulai_cuti" class="form-control @error('mulai_cuti') is-invalid @enderror"
                                       value="{{ old('mulai_cuti', $cuti->mulai_cuti->format('Y-m-d')) }}" required>
                                @error('mulai_cuti') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                         <div class="col-md-6 col-12">
                             <div class="form-group">
                                <label for="selesai_cuti">Selesai Cuti <span class="text-danger">*</span></label>
                                 {{-- Isi value berdasarkan $cuti --}}
                                <input type="date" name="selesai_cuti" id="selesai_cuti" class="form-control @error('selesai_cuti') is-invalid @enderror"
                                       value="{{ old('selesai_cuti', $cuti->selesai_cuti->format('Y-m-d')) }}" required>
                                @error('selesai_cuti') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="keperluan">Keperluan <span class="text-danger">*</span></label>
                                 {{-- Isi value berdasarkan $cuti --}}
                                <textarea name="keperluan" id="keperluan" rows="3" class="form-control @error('keperluan') is-invalid @enderror" required>{{ old('keperluan', $cuti->keperluan) }}</textarea>
                                 @error('keperluan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                         <div class="col-12">
                             <div class="form-group">
                                <label for="alamat_selama_cuti">Alamat Selama Cuti <span class="text-danger">*</span></label>
                                 {{-- Isi value berdasarkan $cuti --}}
                                <textarea name="alamat_selama_cuti" id="alamat_selama_cuti" rows="3" class="form-control @error('alamat_selama_cuti') is-invalid @enderror" required>{{ old('alamat_selama_cuti', $cuti->alamat_selama_cuti) }}</textarea>
                                @error('alamat_selama_cuti') <div class="invalid-feedback">{{ $message }}</div> @enderror
                             </div>
                         </div>
                        <div class="col-12">
                            {{-- Input Surat Sakit hanya muncul jika Jenis Cuti = Cuti Sakit --}}
                            <div class="form-group" id="surat_sakit_group" style="display: none;">
                                <label for="surat_sakit">Unggah Surat Sakit (Baru)</label>
                                <input type="file" name="surat_sakit" id="surat_sakit" class="form-control @error('surat_sakit') is-invalid @enderror">
                                @error('surat_sakit')
                                     <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Format: PDF, JPG, JPEG, PNG. Maks: 2MB. Kosongkan jika tidak ingin mengganti file yang sudah ada.</small>

                                {{-- Tampilkan link ke file lama jika ada --}}
                                @if($cuti->surat_sakit)
                                <div class="mt-2">
                                    <small>File saat ini:
                                        <a href="{{ asset('storage/' . $cuti->surat_sakit) }}" target="_blank">
                                            <i class="bi bi-paperclip"></i> {{ basename($cuti->surat_sakit) }}
                                        </a>
                                    </small>
                                </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            {{-- Ubah teks tombol submit --}}
                            <button type="submit" class="btn btn-primary me-1 mb-1">Update & Ajukan Ulang</button>
                            <a href="{{ route('cuti.index') }}" class="btn btn-light-secondary me-1 mb-1">Batal</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>
@endsection

@push('js')
    {{-- Salin kode JavaScript yang sama dari create.blade.php untuk fetch kuota dan toggle surat sakit --}}
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const jenisCutiSelect = document.getElementById('jenis_cuti_id');
        const kuotaCutiInput = document.getElementById('kuota_cuti');
        const suratSakitGroup = document.getElementById('surat_sakit_group');

        // Fungsi untuk mengambil atau menampilkan info kuota
        function displayQuotaInfo(jenisCutiId, selectedOption) {
            const namaCutiLower = selectedOption ? selectedOption.getAttribute('data-nama') : '';
            kuotaCutiInput.value = 'Memuat...';
            if (namaCutiLower === 'cuti sakit') {
                kuotaCutiInput.value = 'Sesuai ketentuan';
                return;
            }
            if (jenisCutiId) {
                fetch(`{{ route('cuti.getQuota.ajax') }}?jenis_cuti_id=${jenisCutiId}`)
                    .then(response => {
                        if (!response.ok) { throw new Error('Network response was not ok: ' + response.statusText); }
                        return response.json();
                    })
                    .then(data => {
                        if (typeof data.durasi_cuti !== 'undefined') { kuotaCutiInput.value = data.durasi_cuti + ' hari'; }
                        else { kuotaCutiInput.value = 'Data tidak ditemukan'; }
                    })
                    .catch(error => {
                        console.error('Error fetching kuota cuti:', error);
                        kuotaCutiInput.value = 'Gagal memuat';
                    });
            } else {
                kuotaCutiInput.value = '-';
            }
        }

         // Fungsi untuk menampilkan/menyembunyikan input surat sakit
         function toggleSuratSakitInput(selectedOption) {
            const namaCutiLower = selectedOption ? selectedOption.getAttribute('data-nama') : '';
             if (namaCutiLower === 'cuti sakit') { suratSakitGroup.style.display = 'block'; }
             else { suratSakitGroup.style.display = 'none'; }
         }

        // Event listener saat pilihan jenis cuti berubah
        jenisCutiSelect.addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            displayQuotaInfo(this.value, selectedOption);
            toggleSuratSakitInput(selectedOption);
        });

        // Panggil saat halaman load untuk mengisi nilai awal
        if (jenisCutiSelect.value) {
             const initialSelectedOption = jenisCutiSelect.options[jenisCutiSelect.selectedIndex];
            displayQuotaInfo(jenisCutiSelect.value, initialSelectedOption);
            toggleSuratSakitInput(initialSelectedOption);
        } else {
            toggleSuratSakitInput(null);
             kuotaCutiInput.value = '-';
        }
    });
    </script>
@endpush