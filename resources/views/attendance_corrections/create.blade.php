{{-- resources/views/attendance_corrections/create.blade.php --}}
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@push('css')
    {{-- CSS untuk Flatpickr date/time picker --}}
    <link rel="stylesheet" href="{{ asset('assets/extensions/flatpickr/flatpickr.min.css') }}">
    <style>
        /* Styling untuk menyorot input yang tidak valid */
        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #dc3545; /* Warna merah standar Bootstrap untuk error */
        }

        /* Memastikan pesan error validasi ditampilkan */
        .invalid-feedback {
            display: block;
        }

        /* Styling untuk kontainer informasi data absensi asli */
        #original-attendance-info {
            display: none; /* Awalnya disembunyikan */
            opacity: 0;    /* Transparansi awal untuk efek fade-in */
            transition: opacity 0.5s ease-in-out; /* Efek transisi saat muncul/hilang */
            border-left: 5px solid #0d6efd; /* Garis biru di sisi kiri (Bootstrap info color) */
            background-color: #e7f3ff; /* Warna latar belakang terang untuk info box */
        }

        #original-attendance-info.visible {
            display: block; /* Tampilkan elemen */
            opacity: 1;     /* Buat elemen sepenuhnya terlihat */
        }

        /* Styling untuk indikator loading saat mengambil data absensi asli */
        #loading-original-data {
            display: none; /* Awalnya disembunyikan */
            text-align: center;
            padding: 10px;
            color: #6c757d; /* Warna teks abu-abu */
        }
    </style>
@endpush

@section('content')
    <div id="main">
        {{-- Header Halaman & Breadcrumb --}}
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Pengajuan Koreksi Absensi</h3>
                        <p class="text-subtitle text-muted">Formulir untuk mengajukan koreksi data absensi Anda.</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                                <li class="breadcrumb-item">Absensi</li> {{-- Atau link ke daftar koreksi jika ada --}}
                                <li class="breadcrumb-item active" aria-current="page">Ajukan Koreksi</li>
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
                    <h4 class="card-title">Detail Pengajuan Koreksi</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('attendance_corrections.store') }}" method="POST">
                        @csrf

                        {{-- Input tersembunyi untuk menyimpan ID absensi asli, akan diisi oleh JavaScript --}}
                        <input type="hidden" id="original_attendance_id" name="original_attendance_id"
                            value="{{ $originalAttendance?->id }}">

                        <div class="row">
                            {{-- Kolom Kiri Form --}}
                            <div class="col-md-6">
                                {{-- Input Tanggal Absensi yang Dikoreksi --}}
                                <div class="form-group mb-3">
                                    <label for="correction_date" class="form-label">Tanggal Absensi yang Dikoreksi <span
                                            class="text-danger">*</span></label>
                                    <input type="text" id="correction_date" name="correction_date"
                                        class="form-control flatpickr-date @error('correction_date') is-invalid @enderror"
                                        value="{{ old('correction_date', $correctionDate) }}" {{-- $correctionDate dari controller --}}
                                        placeholder="YYYY-MM-DD" required>
                                    @error('correction_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Pilihan Shift Seharusnya --}}
                                <div class="form-group mb-3">
                                    <label for="requested_shift_id" class="form-label">Shift Seharusnya (Jika Ada
                                        Perubahan)</label>
                                    <select id="requested_shift_id" name="requested_shift_id"
                                        class="form-select @error('requested_shift_id') is-invalid @enderror">
                                        <option value="">-- Pilih Shift Jika Berubah --</option>
                                        @foreach ($shifts as $shift) {{-- $shifts dari controller --}}
                                            <option value="{{ $shift->id }}"
                                                {{-- Pre-select berdasarkan old value atau data absensi asli (jika ada saat load awal) --}}
                                                {{ old('requested_shift_id', $originalAttendance?->shift_id) == $shift->id ? 'selected' : '' }}>
                                                {{ $shift->name }}
                                                ({{ \Carbon\Carbon::parse($shift->start_time)->format('H:i') }} -
                                                {{ \Carbon\Carbon::parse($shift->end_time)->format('H:i') }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('requested_shift_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Input Jam Masuk Seharusnya (Menggunakan input time HTML5) --}}
                                <div class="form-group mb-3">
                                    <label for="requested_clock_in" class="form-label">Jam Masuk Seharusnya</label>
                                    <input type="time" id="requested_clock_in" name="requested_clock_in"
                                        class="form-control @error('requested_clock_in') is-invalid @enderror"
                                        value="{{ old('requested_clock_in', $originalAttendance && $originalAttendance->clock_in_time ? \Carbon\Carbon::parse($originalAttendance->clock_in_time)->format('H:i') : '') }}">
                                    @error('requested_clock_in')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted mt-1">Kosongkan jika tidak ingin mengoreksi jam
                                        masuk.</small>
                                </div>

                                {{-- Input Jam Keluar Seharusnya (Menggunakan input time HTML5) --}}
                                <div class="form-group mb-3">
                                    <label for="requested_clock_out" class="form-label">Jam Keluar Seharusnya</label>
                                    <input type="time" id="requested_clock_out" name="requested_clock_out"
                                        class="form-control @error('requested_clock_out') is-invalid @enderror"
                                        value="{{ old('requested_clock_out', $originalAttendance && $originalAttendance->clock_out_time ? \Carbon\Carbon::parse($originalAttendance->clock_out_time)->format('H:i') : '') }}">
                                    @error('requested_clock_out')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted mt-1">Kosongkan jika tidak ingin mengoreksi jam
                                        keluar.</small>
                                </div>
                            </div>

                            {{-- Kolom Kanan Form --}}
                            <div class="col-md-6">
                                {{-- Indikator Loading Data Absensi Asli --}}
                                <div id="loading-original-data">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Memuat...</span>
                                    </div>
                                    Memuat data absensi tercatat...
                                </div>

                                {{-- Informasi Data Absensi Asli (Diisi oleh JavaScript) --}}
                                <div id="original-attendance-info"
                                    class="alert alert-light-info color-info mb-3 p-3 {{ $originalAttendance ? 'visible' : '' }}">
                                    <h5 class="alert-heading fw-bold">Data Absensi Tercatat</h5>
                                    <hr class="my-2">
                                    <p class="mb-1">
                                        Shift Tercatat: <strong
                                            id="original-shift">{{ $originalAttendance?->shift?->name ?? 'N/A' }}</strong>
                                    </p>
                                    <p class="mb-1">
                                        Jam Masuk Tercatat: <strong
                                            id="original-clock-in">{{ $originalAttendance && $originalAttendance->clock_in_time ? \Carbon\Carbon::parse($originalAttendance->clock_in_time)->format('H:i:s') : 'N/A' }}</strong>
                                    </p>
                                    <p class="mb-1">
                                        Jam Keluar Tercatat: <strong
                                            id="original-clock-out">{{ $originalAttendance && $originalAttendance->clock_out_time ? \Carbon\Carbon::parse($originalAttendance->clock_out_time)->format('H:i:s') : 'N/A' }}</strong>
                                    </p>
                                    <p class="mb-1">
                                        Status Saat Ini: <strong
                                            id="original-status">{{ $originalAttendance?->attendance_status ?? 'Belum Diproses' }}</strong>
                                    </p>
                                    <p class="mb-0">
                                        Catatan Sistem: <span
                                            id="original-notes">{{ $originalAttendance?->notes ?? '-' }}</span>
                                    </p>
                                </div>

                                {{-- Input Alasan Pengajuan Koreksi --}}
                                <div class="form-group mb-3">
                                    <label for="reason" class="form-label">Alasan Pengajuan Koreksi <span
                                            class="text-danger">*</span></label>
                                    <textarea id="reason" name="reason" rows="5" class="form-control @error('reason') is-invalid @enderror"
                                        placeholder="Jelaskan alasan Anda mengajukan koreksi ini secara detail..." required>{{ old('reason') }}</textarea>
                                    @error('reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Tombol Aksi Form --}}
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send-fill"></i> Kirim Pengajuan Koreksi
                            </button>
                            {{-- Tombol kembali menggunakan history browser --}}
                            <a href="#" onclick="window.history.back(); return false;"
                                class="btn btn-light-secondary">
                                Kembali
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('js')
    {{-- JavaScript untuk Flatpickr --}}
    <script src="{{ asset('assets/extensions/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('assets/extensions/flatpickr/l10n/id.js') }}"></script> {{-- Lokalisasi Bahasa Indonesia --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Ambil elemen-elemen DOM yang akan dimanipulasi
            const correctionDateInput = document.getElementById('correction_date');
            const originalInfoDiv = document.getElementById('original-attendance-info');
            const loadingDiv = document.getElementById('loading-original-data');
            const originalShift = document.getElementById('original-shift');
            const originalClockIn = document.getElementById('original-clock-in');
            const originalClockOut = document.getElementById('original-clock-out');
            const originalStatus = document.getElementById('original-status');
            const originalNotes = document.getElementById('original-notes');
            const originalAttendanceIdInput = document.getElementById('original_attendance_id');
            const requestedShiftSelect = document.getElementById('requested_shift_id');
            const requestedClockInInput = document.getElementById('requested_clock_in');
            const requestedClockOutInput = document.getElementById('requested_clock_out');
            // Ambil CSRF token dari meta tag (penting jika route AJAX dilindungi CSRF, meskipun GET biasanya tidak)
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            // Fungsi untuk mengambil dan menampilkan data absensi asli berdasarkan tanggal terpilih
            async function fetchAndDisplayOriginalData(selectedDate) {
                console.log('Tanggal yang dipilih untuk fetch:', selectedDate); // Debug: tampilkan tanggal yang dipilih

                // Jika tidak ada tanggal terpilih, sembunyikan info dan reset input
                if (!selectedDate) {
                    originalInfoDiv.classList.remove('visible');
                    loadingDiv.style.display = 'none';
                    originalAttendanceIdInput.value = '';
                    if (requestedShiftSelect) requestedShiftSelect.value = '';
                    if (requestedClockInInput) requestedClockInInput.value = '';
                    if (requestedClockOutInput) requestedClockOutInput.value = '';
                    return;
                }

                originalInfoDiv.classList.remove('visible'); // Sembunyikan info box sebelum request baru
                loadingDiv.style.display = 'block';      // Tampilkan indikator loading
                originalAttendanceIdInput.value = '';      // Kosongkan ID absensi asli sementara

                // URL endpoint untuk mengambil data absensi asli
                const url = `/attendance-corrections/get-original-data/${selectedDate}`;

                try {
                    const response = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            // 'X-CSRF-TOKEN': csrfToken // Biasanya tidak diperlukan untuk GET request
                        }
                    });

                    loadingDiv.style.display = 'none'; // Sembunyikan loading setelah response diterima

                    if (!response.ok) {
                        console.error('Gagal mengambil data absensi asli. Status:', response.status, response.statusText);
                        originalInfoDiv.classList.remove('visible');
                        if (requestedShiftSelect) requestedShiftSelect.value = '';
                        if (requestedClockInInput) requestedClockInInput.value = '';
                        if (requestedClockOutInput) requestedClockOutInput.value = '';
                        // Bisa tambahkan notifikasi error ke pengguna jika perlu
                        return;
                    }

                    const data = await response.json();
                    console.log('Data diterima dari server:', data); // Debug: tampilkan data yang diterima

                    if (data) { // Jika server mengembalikan data (bukan null)
                        originalInfoDiv.classList.add('visible'); // Tampilkan info box

                        // Isi elemen di info box dengan data yang diterima
                        originalShift.textContent = data.shift_name || 'N/A';
                        originalClockIn.textContent = data.clock_in || 'N/A'; // data.clock_in adalah string "HH:MM:SS" atau null
                        originalClockOut.textContent = data.clock_out || 'N/A';// data.clock_out adalah string "HH:MM:SS" atau null
                        originalStatus.textContent = data.status || 'Belum Diproses';
                        originalNotes.textContent = data.notes || '-';
                        originalAttendanceIdInput.value = data.id || '';

                        // Pre-fill input form koreksi berdasarkan data asli
                        if (requestedShiftSelect) {
                            requestedShiftSelect.value = data.shift_id !== null ? data.shift_id : '';
                        }
                        if (requestedClockInInput) {
                            // data.clock_in dari server adalah string "HH:MM:SS" atau null
                            // Input type="time" mengharapkan format "HH:MM"
                            if (data.clock_in && typeof data.clock_in === 'string') {
                                requestedClockInInput.value = data.clock_in.substring(0, 5); // Ambil HH:MM
                            } else {
                                requestedClockInInput.value = '';
                            }
                        }
                        if (requestedClockOutInput) {
                            // data.clock_out dari server adalah string "HH:MM:SS" atau null
                            if (data.clock_out && typeof data.clock_out === 'string') {
                                requestedClockOutInput.value = data.clock_out.substring(0, 5); // Ambil HH:MM
                            } else {
                                requestedClockOutInput.value = '';
                            }
                        }
                    } else { // Jika server mengembalikan null (tidak ada data absensi untuk tanggal tsb)
                        originalInfoDiv.classList.remove('visible'); // Sembunyikan info box
                        if (requestedShiftSelect) requestedShiftSelect.value = '';
                        if (requestedClockInInput) requestedClockInInput.value = '';
                        if (requestedClockOutInput) requestedClockOutInput.value = '';
                        console.log('Tidak ada data absensi asli ditemukan untuk tanggal ini.');
                    }

                } catch (error) {
                    console.error('Error saat fetch data absensi asli (catch block):', error);
                    loadingDiv.style.display = 'none';
                    originalInfoDiv.classList.remove('visible');
                    if (requestedShiftSelect) requestedShiftSelect.value = '';
                    if (requestedClockInInput) requestedClockInInput.value = '';
                    if (requestedClockOutInput) requestedClockOutInput.value = '';
                    // Tampilkan pesan error ke pengguna jika perlu
                }
            }

            // Inisialisasi Flatpickr untuk input tanggal
            flatpickr(".flatpickr-date", {
                altInput: true, // Menampilkan format alternatif yang lebih mudah dibaca pengguna
                altFormat: "j F Y", // Format alternatif (contoh: 13 Mei 2025)
                dateFormat: "Y-m-d", // Format yang dikirim ke server (YYYY-MM-DD)
                locale: "id", // Menggunakan lokalisasi Bahasa Indonesia
                onChange: function(selectedDates, dateStr, instance) {
                    // Panggil fungsi fetch saat tanggal pada kalender berubah
                    fetchAndDisplayOriginalData(dateStr);
                },
                // Panggil juga saat halaman pertama kali dimuat jika input tanggal sudah memiliki nilai
                onReady: function(selectedDates, dateStr, instance) {
                    if (instance.input.value) {
                        fetchAndDisplayOriginalData(instance.input.value);
                    }
                }
            });

            // Validasi frontend sederhana: memastikan setidaknya satu jam (masuk atau keluar) diisi
            const form = document.querySelector('form[action="{{ route('attendance_corrections.store') }}"]');
            if (form && requestedClockInInput && requestedClockOutInput) {
                form.addEventListener('submit', function(event) {
                    // Hapus kelas is-invalid sebelumnya
                    requestedClockInInput.classList.remove('is-invalid');
                    requestedClockOutInput.classList.remove('is-invalid');

                    if (!requestedClockInInput.value && !requestedClockOutInput.value) {
                        event.preventDefault(); // Mencegah form submit
                        Swal.fire({ // Menggunakan SweetAlert untuk notifikasi
                            icon: 'error',
                            title: 'Input Tidak Lengkap',
                            text: 'Harap isi setidaknya Jam Masuk Seharusnya atau Jam Keluar Seharusnya.',
                        });
                        // Beri fokus dan tandai input yang relevan sebagai tidak valid
                        if (!requestedClockInInput.value) {
                            requestedClockInInput.focus();
                            requestedClockInInput.classList.add('is-invalid');
                        } else if (!requestedClockOutInput.value) { // Seharusnya tidak mungkin karena cek di atas, tapi untuk kelengkapan
                            requestedClockOutInput.classList.add('is-invalid');
                            requestedClockOutInput.focus();
                        }
                    }
                });
            }
        });
    </script>
@endpush
