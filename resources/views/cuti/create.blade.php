@extends('layout.app')

@section('content')
<div id="main">
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Pengajuan Cuti</h3>
                    <p class="text-subtitle text-muted">Ajukan cuti Anda di sini.</p>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Pengajuan Cuti</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel daftar kuota cuti --}}
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Form Pengajuan Cuti</h4>
            </div>
            <div class="card-body">
                {{-- Menampilkan Error Umum (Overlap, Kuota, dll) --}}
                @if ($errors->has('error'))
                    <div class="alert alert-danger">
                        {{ $errors->first('error') }}
                    </div>
                @endif

                <form action="{{ route('cuti.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row"> {{-- Gunakan grid system Mazer/Bootstrap --}}
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="jenis_cuti_id">Jenis Cuti <span class="text-danger">*</span></label>
                                <select name="jenis_cuti_id" id="jenis_cuti_id" class="form-select @error('jenis_cuti_id') is-invalid @enderror" required>
                                    <option value="" disabled selected>-- Pilih Jenis Cuti --</option>
                                    @foreach ($jenisCuti as $cuti)
                                        <option value="{{ $cuti->id }}" data-nama="{{ strtolower($cuti->nama_cuti) }}" {{ old('jenis_cuti_id') == $cuti->id ? 'selected' : '' }}>
                                            {{ $cuti->nama_cuti }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('jenis_cuti_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="kuota_cuti">Sisa Kuota Tersedia</label>
                                {{-- Input dibuat sedikit berbeda untuk indikasi loading --}}
                                <input type="text" id="kuota_cuti" class="form-control" value="-" readonly style="background-color: #e9ecef;">
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                             <div class="form-group">
                                <label for="mulai_cuti">Mulai Cuti <span class="text-danger">*</span></label>
                                <input type="date" name="mulai_cuti" id="mulai_cuti" class="form-control @error('mulai_cuti') is-invalid @enderror" value="{{ old('mulai_cuti') }}" required>
                                @error('mulai_cuti')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                         <div class="col-md-6 col-12">
                             <div class="form-group">
                                <label for="selesai_cuti">Selesai Cuti <span class="text-danger">*</span></label>
                                <input type="date" name="selesai_cuti" id="selesai_cuti" class="form-control @error('selesai_cuti') is-invalid @enderror" value="{{ old('selesai_cuti') }}" required>
                                @error('selesai_cuti')
                                     <div class="invalid-feedback">{{ $message }}</div>
                                 @enderror
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="keperluan">Keperluan <span class="text-danger">*</span></label>
                                <textarea name="keperluan" id="keperluan" rows="3" class="form-control @error('keperluan') is-invalid @enderror" required>{{ old('keperluan') }}</textarea>
                                 @error('keperluan')
                                     <div class="invalid-feedback">{{ $message }}</div>
                                 @enderror
                            </div>
                        </div>
                         <div class="col-12">
                             <div class="form-group">
                                <label for="alamat_selama_cuti">Alamat Selama Cuti <span class="text-danger">*</span></label>
                                <textarea name="alamat_selama_cuti" id="alamat_selama_cuti" rows="3" class="form-control @error('alamat_selama_cuti') is-invalid @enderror" required>{{ old('alamat_selama_cuti') }}</textarea>
                                @error('alamat_selama_cuti')
                                     <div class="invalid-feedback">{{ $message }}</div>
                                 @enderror
                             </div>
                         </div>
                        <div class="col-12">
                             {{-- Input Surat Sakit hanya muncul jika Jenis Cuti = Cuti Sakit --}}
                            <div class="form-group" id="surat_sakit_group" style="display: none;"> {{-- Sembunyikan default --}}
                                <label for="surat_sakit">Unggah Surat Sakit (Wajib jika >= 2 hari kerja)</label>
                                <input type="file" name="surat_sakit" id="surat_sakit" class="form-control @error('surat_sakit') is-invalid @enderror">
                                 @error('surat_sakit')
                                     <div class="invalid-feedback d-block">{{-- Paksa tampil --}}
                                         {{ $message }}
                                     </div>
                                 @enderror
                                 <small class="text-muted">Format: PDF, JPG, JPEG, PNG. Maks: 2MB.</small>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                             <button type="submit" class="btn btn-primary me-1 mb-1">Ajukan Cuti</button>
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const jenisCutiSelect = document.getElementById('jenis_cuti_id');
        const kuotaCutiInput = document.getElementById('kuota_cuti');
        const suratSakitGroup = document.getElementById('surat_sakit_group');

        // Ambil data kuota awal (jika ada old input, tapi mungkin tidak relevan lagi
        // karena kita akan fetch ulang atau set teks khusus)
        // const currentKuotaData = @json($currentKuota ?? []);

        // Fungsi untuk mengambil atau menampilkan info kuota
        function displayQuotaInfo(jenisCutiId, selectedOption) {
            const namaCutiLower = selectedOption ? selectedOption.getAttribute('data-nama') : '';

            kuotaCutiInput.value = 'Memuat...'; // Indikator loading

            // === AWAL PERUBAHAN LOGIKA ===
            if (namaCutiLower === 'cuti sakit') {
                // Jika Cuti Sakit, tampilkan teks khusus, JANGAN fetch angka 0
                kuotaCutiInput.value = 'Sesuai ketentuan'; // Teks bisa disesuaikan
                // Hentikan proses fetch kuota numerik
                return;
            }
            // === AKHIR PERUBAHAN LOGIKA ===


            // Jika bukan Cuti Sakit DAN ada jenis cuti ID dipilih, fetch kuota numerik
            if (jenisCutiId) {
                fetch(`{{ route('cuti.getQuota.ajax') }}?jenis_cuti_id=${jenisCutiId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Pastikan data.durasi_cuti ada sebelum ditampilkan
                        if (typeof data.durasi_cuti !== 'undefined') {
                            kuotaCutiInput.value = data.durasi_cuti + ' hari';
                        } else {
                            kuotaCutiInput.value = 'Data tidak ditemukan'; // Fallback jika data aneh
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching kuota cuti:', error);
                        kuotaCutiInput.value = 'Gagal memuat'; // Tampilkan error jika gagal fetch
                    });
            } else {
                // Reset jika tidak ada jenis cuti dipilih
                kuotaCutiInput.value = '-';
            }
        }

         // Fungsi untuk menampilkan/menyembunyikan input surat sakit (tetap sama)
         function toggleSuratSakitInput(selectedOption) {
            const namaCutiLower = selectedOption ? selectedOption.getAttribute('data-nama') : '';
             // Pastikan nama 'cuti sakit' sama persis (lowercase)
             if (namaCutiLower === 'cuti sakit') {
                suratSakitGroup.style.display = 'block';
            } else {
                 suratSakitGroup.style.display = 'none';
             }
         }

        // Event listener saat pilihan jenis cuti berubah
        jenisCutiSelect.addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            displayQuotaInfo(this.value, selectedOption); // Pass selectedOption
            toggleSuratSakitInput(selectedOption);
        });

        // Panggil saat halaman load jika ada old input value atau pilihan awal
        if (jenisCutiSelect.value) {
             const initialSelectedOption = jenisCutiSelect.options[jenisCutiSelect.selectedIndex];
            displayQuotaInfo(jenisCutiSelect.value, initialSelectedOption); // Pass initial selectedOption
            toggleSuratSakitInput(initialSelectedOption);
        } else {
            // Pastikan input surat sakit tersembunyi jika belum ada pilihan
            toggleSuratSakitInput(null);
             kuotaCutiInput.value = '-'; // Set default saat load jika belum ada pilihan
        }

    });
</script>
@endpush