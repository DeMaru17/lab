{{-- resources/views/attendance_corrections/create.blade.php --}}
@extends('layout.app')

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/extensions/flatpickr/flatpickr.min.css') }}">
    <style>
        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #dc3545;
        }

        .invalid-feedback {
            display: block;
        }

        /* Awalnya sembunyikan info data asli */
        #original-attendance-info {
            display: none;
            /* Mulai tersembunyi */
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }

        #original-attendance-info.visible {
            display: block;
            /* Tampilkan */
            opacity: 1;
        }

        #loading-original-data {
            display: none;
            /* Spinner tersembunyi */
            text-align: center;
            padding: 10px;
            color: #6c757d;
        }
    </style>
@endpush

@section('content')
    <div id="main">
        {{-- Header Halaman & Breadcrumb --}}
        <div class="page-heading">
            {{-- ... (Kode header seperti sebelumnya) ... --}}
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
                                <li class="breadcrumb-item">Absensi</li>
                                <li class="breadcrumb_item active" aria-current="page">Ajukan Koreksi</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Detail Pengajuan Koreksi</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('attendance_corrections.store') }}" method="POST">
                        @csrf

                        {{-- Hidden input untuk ID absensi asli, akan diisi oleh JS --}}
                        <input type="hidden" id="original_attendance_id" name="original_attendance_id"
                            value="{{ $originalAttendance?->id }}">

                        <div class="row">
                            {{-- Kolom Kiri --}}
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="correction_date" class="form-label">Tanggal Absensi yang Dikoreksi <span
                                            class="text-danger">*</span></label>
                                    <input type="text" id="correction_date" name="correction_date"
                                        class="form-control flatpickr-date @error('correction_date') is-invalid @enderror"
                                        value="{{ old('correction_date', $correctionDate) }}" placeholder="YYYY-MM-DD"
                                        required>
                                    @error('correction_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group mb-3">
                                    <label for="requested_shift_id" class="form-label">Shift Seharusnya (Jika Ada
                                        Perubahan)</label>
                                    <select id="requested_shift_id" name="requested_shift_id"
                                        class="form-select @error('requested_shift_id') is-invalid @enderror">
                                        <option value="">-- Pilih Shift Jika Berubah --</option>
                                        @foreach ($shifts as $shift)
                                            <option value="{{ $shift->id }}" {{-- Pre-select berdasarkan old() atau data asli JIKA ADA SAAT AWAL LOAD --}}
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

                                {{-- Input Jam Masuk Seharusnya (Native) --}}
                                <div class="form-group mb-3">
                                    <label for="requested_clock_in" class="form-label">Jam Masuk Seharusnya</label>
                                    {{-- Ubah type="text" menjadi type="time", hapus class flatpickr-time, hapus readonly --}}
                                    <input type="time" id="requested_clock_in" name="requested_clock_in"
                                        class="form-control @error('requested_clock_in') is-invalid @enderror"
                                        value="{{ old('requested_clock_in', $originalAttendance && $originalAttendance->clock_in_time ? \Carbon\Carbon::parse($originalAttendance->clock_in_time)->format('H:i') : '') }}">
                                    {{-- Format H:i sudah sesuai untuk type="time" --}}
                                    @error('requested_clock_in')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted mt-1">Kosongkan jika tidak ingin mengoreksi jam
                                        masuk.</small>
                                </div>

                                {{-- Input Jam Keluar Seharusnya (Native) --}}
                                <div class="form-group mb-3">
                                    <label for="requested_clock_out" class="form-label">Jam Keluar Seharusnya</label>
                                    {{-- Ubah type="text" menjadi type="time", hapus class flatpickr-time, hapus readonly --}}
                                    <input type="time" id="requested_clock_out" name="requested_clock_out"
                                        class="form-control @error('requested_clock_out') is-invalid @enderror"
                                        value="{{ old('requested_clock_out', $originalAttendance && $originalAttendance->clock_out_time ? \Carbon\Carbon::parse($originalAttendance->clock_out_time)->format('H:i') : '') }}">
                                    {{-- Format H:i sudah sesuai untuk type="time" --}}
                                    @error('requested_clock_out')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted mt-1">Kosongkan jika tidak ingin mengoreksi jam
                                        keluar.</small>
                                </div>
                            </div>

                            {{-- Kolom Kanan --}}
                            <div class="col-md-6">
                                {{-- Tempat untuk menampilkan data asli (diupdate oleh JS) --}}
                                <div id="loading-original-data">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Memuat...</span>
                                    </div>
                                    Memuat data absensi tercatat...
                                </div>
                                <div id="original-attendance-info"
                                    class="alert alert-light-info color-info mb-3 {{ $originalAttendance ? 'visible' : '' }}">
                                    <h4 class="alert-heading">Data Absensi Tercatat</h4>
                                    <p>
                                        Shift Tercatat: <strong
                                            id="original-shift">{{ $originalAttendance?->shift?->name ?? 'N/A' }}</strong>
                                        <br>
                                        Jam Masuk Tercatat: <strong
                                            id="original-clock-in">{{ $originalAttendance && $originalAttendance->clock_in_time ? \Carbon\Carbon::parse($originalAttendance->clock_in_time)->format('H:i:s') : 'N/A' }}</strong>
                                        <br>
                                        Jam Keluar Tercatat: <strong
                                            id="original-clock-out">{{ $originalAttendance && $originalAttendance->clock_out_time ? \Carbon\Carbon::parse($originalAttendance->clock_out_time)->format('H:i:s') : 'N/A' }}</strong>
                                        <br>
                                        Status Saat Ini: <strong
                                            id="original-status">{{ $originalAttendance?->attendance_status ?? 'Belum Diproses' }}</strong>
                                        <br>
                                        Catatan Sistem: <span
                                            id="original-notes">{{ $originalAttendance?->notes ?? '-' }}</span>
                                    </p>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="reason" class="form-label">Alasan Pengajuan Koreksi <span
                                            class="text-danger">*</span></label>
                                    <textarea id="reason" name="reason" rows="5" class="form-control @error('reason') is-invalid @enderror"
                                        placeholder="Jelaskan alasan Anda mengajukan koreksi ini..." required>{{ old('reason') }}</textarea>
                                    @error('reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send-fill"></i> Kirim Pengajuan Koreksi
                            </button>
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
    <script src="{{ asset('assets/extensions/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('assets/extensions/flatpickr/l10n/id.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // Fungsi untuk mengambil dan menampilkan data absensi asli
            async function fetchAndDisplayOriginalData(selectedDate) {
                if (!selectedDate) {
                    originalInfoDiv.classList.remove('visible');
                    loadingDiv.style.display = 'none';
                    originalAttendanceIdInput.value = ''; // Kosongkan ID
                    // Kosongkan juga pre-fill input koreksi jika tidak ada tanggal terpilih
                    if (requestedShiftSelect) requestedShiftSelect.value = '';
                    if (requestedClockInInput) requestedClockInInput.value = '';
                    if (requestedClockOutInput) requestedClockOutInput.value = '';
                    return;
                }

                originalInfoDiv.classList.remove('visible'); // Sembunyikan dulu
                loadingDiv.style.display = 'block'; // Tampilkan loading
                originalAttendanceIdInput.value = ''; // Kosongkan ID sementara

                // Buat URL endpoint dengan tanggal terpilih
                const url =
                `/attendance-corrections/get-original-data/${selectedDate}`; // Sesuaikan jika base URL berbeda

                try {
                    const response = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken // Sertakan CSRF jika diperlukan oleh middleware (biasanya tidak untuk GET)
                        }
                    });

                    loadingDiv.style.display = 'none'; // Sembunyikan loading

                    if (!response.ok) {
                        console.error('Gagal mengambil data absensi asli:', response.statusText);
                        originalInfoDiv.classList.remove('visible'); // Tetap sembunyikan jika error
                        // Kosongkan pre-fill input koreksi
                        if (requestedShiftSelect) requestedShiftSelect.value = '';
                        if (requestedClockInInput) requestedClockInInput.value = '';
                        if (requestedClockOutInput) requestedClockOutInput.value = '';
                        return;
                    }

                    const data = await response.json();

                    if (data) { // Jika ada data ditemukan
                        originalShift.textContent = data.shift_name || 'N/A';
                        originalClockIn.textContent = data.clock_in || 'N/A';
                        originalClockOut.textContent = data.clock_out || 'N/A';
                        originalStatus.textContent = data.status || 'Belum Diproses';
                        originalNotes.textContent = data.notes || '-';
                        originalAttendanceIdInput.value = data.id || ''; // Set ID absensi asli
                        originalInfoDiv.classList.add('visible'); // Tampilkan info

                        // Pre-fill input koreksi berdasarkan data asli
                        if (requestedShiftSelect) requestedShiftSelect.value = data.shift_id || '';
                        if (requestedClockInInput) requestedClockInInput.value = data.clock_in !== 'N/A' ? data
                            .clock_in.substring(0, 5) : ''; // Ambil HH:MM
                        if (requestedClockOutInput) requestedClockOutInput.value = data.clock_out !== 'N/A' ?
                            data.clock_out.substring(0, 5) : ''; // Ambil HH:MM

                    } else { // Jika tidak ada data (JSON null)
                        originalInfoDiv.classList.remove('visible'); // Sembunyikan info
                        // Kosongkan pre-fill input koreksi
                        if (requestedShiftSelect) requestedShiftSelect.value = '';
                        if (requestedClockInInput) requestedClockInInput.value = '';
                        if (requestedClockOutInput) requestedClockOutInput.value = '';
                    }

                } catch (error) {
                    console.error('Error saat fetch data absensi asli:', error);
                    loadingDiv.style.display = 'none';
                    originalInfoDiv.classList.remove('visible');
                    // Kosongkan pre-fill input koreksi
                    if (requestedShiftSelect) requestedShiftSelect.value = '';
                    if (requestedClockInInput) requestedClockInInput.value = '';
                    if (requestedClockOutInput) requestedClockOutInput.value = '';
                }
            }

            // Inisialisasi Flatpickr untuk input tanggal
            flatpickr(".flatpickr-date", {
                altInput: true,
                altFormat: "j F Y",
                dateFormat: "Y-m-d",
                locale: "id",
                onChange: function(selectedDates, dateStr, instance) {
                    // Panggil fungsi fetch saat tanggal berubah
                    fetchAndDisplayOriginalData(dateStr);
                },
                // Panggil juga saat pertama kali load jika sudah ada tanggal
                onReady: function(selectedDates, dateStr, instance) {
                    if (instance.input.value) {
                        fetchAndDisplayOriginalData(instance.input.value);
                    }
                }
            });

            // Inisialisasi Flatpickr untuk input jam
            flatpickr(".flatpickr-time", {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i",
                time_24hr: true,
            });

            // Validasi frontend sederhana: setidaknya satu jam harus diisi
            const form = document.querySelector('form[action="{{ route('attendance_corrections.store') }}"]');
            if (form && requestedClockInInput && requestedClockOutInput) {
                form.addEventListener('submit', function(event) {
                    if (!requestedClockInInput.value && !requestedClockOutInput.value) {
                        event.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Input Tidak Lengkap',
                            text: 'Harap isi setidaknya Jam Masuk Seharusnya atau Jam Keluar Seharusnya.',
                        });
                        if (!requestedClockInInput.value) {
                            requestedClockInInput.focus();
                            requestedClockInInput.classList.add('is-invalid');
                        } else if (!requestedClockOutInput.value) {
                            requestedClockOutInput.classList.add('is-invalid');
                            requestedClockOutInput.focus();
                        }
                    } else {
                        requestedClockInInput.classList.remove('is-invalid');
                        requestedClockOutInput.classList.remove('is-invalid');
                    }
                });
            }
        });
    </script>
@endpush
