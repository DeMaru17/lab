{{-- resources/views/attendances/index.blade.php --}}
@php
    // Import Carbon jika belum diimport di AppServiceProvider atau alias
    use Carbon\Carbon;
@endphp
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@push('css')
    {{-- CSS Khusus untuk Halaman Absensi --}}
    <style>
        /* Beri tinggi spesifik untuk iframe peta */
        .map-container {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%;
            /* Aspect ratio 16:9 */
            height: 0;
            overflow: hidden;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 0.25rem;
            background-color: #e9ecef;
        }

        /* Iframe peta */
        #map-iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100% !important;
            height: 100% !important;
            border: none;
        }

        /* Perbaikan untuk container kamera */
        .camera-section-container {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            padding: 0;
            box-sizing: border-box;
            overflow: hidden;
        }

        /* Wrapper untuk elemen kamera */
        .camera-elements-wrapper {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            padding: 0;
            box-sizing: border-box;
        }

        /* Perbaikan utama untuk video preview */
        #video-preview {
            display: block;
            width: 100%;
            max-width: 100%;
            height: auto;
            max-height: 60vh;
            aspect-ratio: 4/3;
            border: 1px solid #ccc;
            margin: 0 auto 10px;
            background-color: #000;
            border-radius: 0.25rem;
            object-fit: contain;
            box-sizing: border-box;
        }

        /* Media query untuk layar kecil */
        @media (max-width: 768px) {
            #video-preview {
                max-height: 50vh;
            }
        }

        /* Class untuk menyembunyikan elemen */
        .hidden {
            display: none !important;
        }

        /* Canvas tetap disembunyikan */
        #capture-canvas !important {
            display: none;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Styling preview selfie */
        #selfie-preview {
            width: 100%;
            text-align: center;
            margin-top: 10px;
        }

        #selfie-preview img {
            display: inline-block;
            max-width: 100%;
            height: auto;
            max-height: 300px;
            border: 1px solid #ccc;
            border-radius: 0.25rem;
            object-fit: contain;
        }

        /* Styling tombol kamera */
        .camera-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        /* Styling status lokasi */
        .location-status {
            font-weight: bold;
        }

        .location-ok {
            color: #198754;
        }

        .location-error {
            color: #dc3545;
        }

        .spinner-border-sm {
            vertical-align: -0.125em;
        }

        .js-invalid-feedback {
            display: none;
            width: 100%;
            margin-top: .25rem;
            font-size: .875em;
            color: #dc3545;
            text-align: center;
        }

        .form-control.is-invalid~.js-invalid-feedback,
        .form-select.is-invalid~.js-invalid-feedback {
            display: block;
        }

        #selfie-error.js-invalid-feedback {
            display: none;
        }
    </style>
    </style>
@endpush

@section('content')
    <div id="main">
        {{-- Header Halaman & Breadcrumb --}}
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Absensi Harian</h3>
                        <p class="text-subtitle text-muted" id="current-time">Memuat waktu...</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Absensi</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Formulir Absensi Hari Ini ({{ Carbon::now()->isoFormat('dddd, D MMMM YYYY') }})
                    </h4>
                </div>
                <div class="card-body">
                    {{-- Tampilkan Status Absensi Hari Ini --}}
                    <div class="alert alert-light-secondary color-secondary mb-4">
                        <h4 class="alert-heading">Status Anda Hari Ini:</h4>
                        @if ($attendanceAction === 'check_in')
                            <p>Anda belum melakukan Check-in.</p>
                        @elseif($attendanceAction === 'check_out')
                            <p>Anda sudah Check-in pada pukul:
                                <strong>{{ $checkInTime ? Carbon::parse($checkInTime)->format('H:i:s') : 'N/A' }}</strong>.
                                Silakan lakukan Check-out.
                            </p>
                        @elseif($attendanceAction === 'completed')
                            <p>Anda sudah menyelesaikan absensi hari ini (Check-in:
                                {{ $checkInTime ? Carbon::parse($checkInTime)->format('H:i:s') : 'N/A' }}, Check-out:
                                {{ $todaysAttendance->clock_out_time ? Carbon::parse($todaysAttendance->clock_out_time)->format('H:i:s') : 'N/A' }}).
                            </p>
                        @endif
                        @if ($todaysAttendance)
                            <hr>
                            <small>
                                Lokasi Check-in: {{ $todaysAttendance->clock_in_location_status ?? '-' }} |
                                Lokasi Check-out: {{ $todaysAttendance->clock_out_location_status ?? '-' }} |
                                Shift: {{ $todaysAttendance->shift->name ?? 'N/A' }}
                            </small>
                        @endif
                    </div>

                    @if ($attendanceAction !== 'completed')
                        {{-- 1. Pilihan Shift (Hanya saat Check-in) --}}
                        <div class="form-group mb-3" id="shift-selection-group"
                            style="{{ $attendanceAction === 'check_in' ? '' : 'display: none;' }}">
                            <label for="shift_id" class="form-label">Pilih Shift Kerja Hari Ini <span
                                    class="text-danger">*</span></label>
                            <select id="shift_id" name="shift_id"
                                class="form-select @error('shift_id') is-invalid @enderror"
                                {{ $attendanceAction === 'check_in' ? 'required' : '' }}>
                                <option value="" disabled selected>-- Pilih Shift --</option>
                                @foreach ($shifts as $shift)
                                    <option value="{{ $shift->id }}">{{ $shift->name }}
                                        ({{ Carbon::parse($shift->start_time)->format('H:i') }} -
                                        {{ Carbon::parse($shift->end_time)->format('H:i') }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="js-invalid-feedback" id="shift-error">Pilih shift kerja Anda.</div>
                            @error('shift_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- 2. Geolocation & iFrame Map --}}
                        <div class="mb-3">
                            <label class="form-label">Lokasi Anda:</label>
                            <div class="map-container">
                                <iframe id="map-iframe" src="" frameborder="0" allowfullscreen=""
                                    aria-hidden="false" tabindex="0" title="Peta Lokasi Anda"></iframe>
                            </div>
                            <div id="location-info" class="mt-2">
                                Status: <span id="location-status" class="location-status">-</span> |
                                Jarak: <span id="location-distance">-</span> meter<br>
                                <small class="text-muted" id="location-coords">Koordinat: -</small>
                            </div>
                            <div class="js-invalid-feedback" id="location-error" style="display: none;">Gagal mendapatkan
                                lokasi atau Anda di luar radius.</div>
                            @error('latitude')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('longitude')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- 3. Kamera & Selfie --}}
                        <div class="camera-section-container">
                            <label class="form-label d-block mb-3">Selfie Absensi: <span
                                    class="text-danger">*</span></label>

                            <div class="camera-elements-wrapper">
                                {{-- Video Preview --}}
                                <video id="video-preview" autoplay playsinline muted></video>

                                {{-- Canvas Tersembunyi --}}
                                <canvas style="display: none" id="capture-canvas"></canvas>

                                {{-- Tombol Capture & Retake --}}
                                <div class="camera-buttons">
                                    <button type="button" id="capture-button" class="btn btn-secondary btn-sm" disabled>
                                        <i class="bi bi-camera-fill"></i> Ambil Foto
                                    </button>
                                    <button type="button" id="retake-button" class="btn btn-warning btn-sm hidden">
                                        <i class="bi bi-arrow-counterclockwise"></i> Ulangi
                                    </button>
                                </div>

                                {{-- Area Preview Hasil Capture --}}
                                <div id="selfie-preview"></div>

                                {{-- Pesan Error --}}
                                <div class="js-invalid-feedback" id="selfie-error" style="display: none;">Wajib mengambil
                                    foto selfie.</div>
                                @error('selfie_data')
                                    <div class="invalid-feedback d-block text-center">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- 4. Tombol Aksi Utama --}}
                        <div class="d-grid gap-2 mt-4">
                            <button type="button" id="attendance-button" class="btn btn-primary btn-lg" disabled>
                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"
                                    style="display: none;"></span>
                                <span id="button-text">Memuat Status...</span>
                            </button>
                        </div>

                        {{-- Hidden inputs --}}
                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">
                        <input type="hidden" id="selfie_data" name="selfie_data">
                        <input type="hidden" id="action_type" name="action" value="{{ $attendanceAction }}">
                    @else
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill"></i> Anda sudah menyelesaikan absensi untuk hari ini.
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection

@push('js')
    {{-- Pastikan SweetAlert2 & Bootstrap JS sudah dimuat di layout --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Element References ---
            const currentTimeElement = document.getElementById('current-time');
            const shiftGroup = document.getElementById('shift-selection-group');
            const shiftSelect = document.getElementById('shift_id');
            const mapIframe = document.getElementById('map-iframe');
            const locationStatusElement = document.getElementById('location-status');
            const locationDistanceElement = document.getElementById('location-distance');
            const locationCoordsElement = document.getElementById('location-coords');
            const locationErrorElement = document.getElementById('location-error');
            const videoPreview = document.getElementById('video-preview');
            const captureCanvas = document.getElementById('capture-canvas');
            const captureButton = document.getElementById('capture-button');
            const retakeButton = document.getElementById('retake-button');
            const selfiePreview = document.getElementById('selfie-preview');
            const selfieErrorElement = document.getElementById('selfie-error');
            const attendanceButton = document.getElementById('attendance-button');
            const buttonText = document.getElementById('button-text');
            const buttonSpinner = attendanceButton.querySelector('.spinner-border');
            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');
            const selfieDataInput = document.getElementById('selfie_data');
            const actionTypeInput = document.getElementById('action_type');
            const csrfTokenElement = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfTokenElement ? csrfTokenElement.getAttribute('content') : document.querySelector(
                'input[name="_token"]').value;

            // --- State Variables ---
            let currentStream = null;
            let currentLatitude = null;
            let currentLongitude = null;
            let isLocationValid = false;
            let isSelfieTaken = false;
            let isProcessing = false;
            const attendanceAction = "{{ $attendanceAction }}";

            // --- Office Location & Radius ---
            // Ambil dari konfigurasi Laravel, dengan nilai default jika tidak ada
            const officeLat = {{ config('attendance.office_latitude', -6.183333) }}; // Contoh default: Jakarta
            const officeLng = {{ config('attendance.office_longitude', 106.833333) }}; // Contoh default: Jakarta
            const allowedRadius =
                {{ config('attendance.allowed_radius_meters', 500) }}; // Contoh default: 500 meter

            // --- Update Waktu Realtime ---
            function updateTime() {
                const now = new Date();
                if (currentTimeElement) {
                    currentTimeElement.textContent =
                        `Waktu Saat Ini: ${now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })}`; // Menggunakan format 24 jam
                }
            }
            let timeInterval = setInterval(updateTime, 1000);
            updateTime(); // Panggil sekali saat load

            // --- Fungsi Inisialisasi Halaman ---
            function initializePage() {
                // Jika absensi sudah selesai, hentikan semua proses
                if (attendanceAction === 'completed') {
                    if (attendanceButton) attendanceButton.disabled = true;
                    if (buttonText) buttonText.textContent = 'Absensi Selesai';
                    if (timeInterval) clearInterval(timeInterval); // Hentikan update waktu
                    stopCamera(); // Hentikan kamera jika masih aktif
                    // Sembunyikan elemen form yang tidak perlu
                    const formElements = document.querySelector('.card-body').querySelectorAll(
                        '.form-group, .mb-3, .camera-section-container, .d-grid');
                    formElements.forEach(el => {
                        // Jangan sembunyikan alert status
                        if (!el.classList.contains('alert')) {
                            // Periksa ID sebelum menyembunyikan jika diperlukan
                            if (el.id !== 'shift-selection-group' || attendanceAction !==
                                'completed') { // Contoh: jangan sembunyikan shift jika status completed
                                // el.style.display = 'none'; // Atau cara lain untuk menyembunyikan jika diperlukan
                            }
                        }
                    });
                    return; // Keluar dari fungsi inisialisasi
                }

                // Atur teks tombol berdasarkan aksi
                if (buttonText) buttonText.textContent = attendanceAction === 'check_in' ? 'Lakukan Check-in' :
                    'Lakukan Check-out';
                if (buttonSpinner) buttonSpinner.style.display = 'none';
                if (attendanceButton) attendanceButton.disabled = true; // Tombol utama disable sampai semua valid
                if (captureButton) captureButton.disabled = true; // Tombol capture disable sampai kamera siap

                // Mulai proses mendapatkan lokasi dan kamera
                getLocation();
                startCamera();
            }

            // --- Fungsi Geolocation ---
            function getLocation() {
                if (!locationStatusElement || !locationDistanceElement || !locationCoordsElement || !
                    locationErrorElement || !mapIframe) {
                    console.warn("Elemen lokasi tidak ditemukan.");
                    return; // Hentikan jika elemen penting tidak ada
                }

                // Reset tampilan status lokasi
                locationStatusElement.textContent = 'Mencari...';
                locationStatusElement.className = 'location-status'; // Reset class
                locationDistanceElement.textContent = '-';
                locationCoordsElement.textContent = 'Koordinat: -';
                locationErrorElement.style.display = 'none';
                mapIframe.src = ''; // Kosongkan iframe saat mencari

                if (!navigator.geolocation) {
                    locationStatusElement.textContent = 'Not Supported';
                    locationStatusElement.className = 'location-status location-error';
                    locationErrorElement.textContent = 'Geolocation tidak didukung oleh browser ini.';
                    locationErrorElement.style.display = 'block';
                    isLocationValid = false;
                    checkEnableButton(); // Periksa ulang status tombol utama
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        currentLatitude = position.coords.latitude;
                        currentLongitude = position.coords.longitude;

                        if (latitudeInput) latitudeInput.value = currentLatitude;
                        if (longitudeInput) longitudeInput.value = currentLongitude;
                        if (locationCoordsElement) locationCoordsElement.textContent =
                            `Koordinat: ${currentLatitude.toFixed(6)}, ${currentLongitude.toFixed(6)}`;

                        // Hitung jarak ke kantor
                        const distance = calculateDistance(currentLatitude, currentLongitude, officeLat,
                            officeLng);
                        if (locationDistanceElement) locationDistanceElement.textContent = distance !== false ?
                            Math.round(distance) : 'Error';

                        // Update iframe peta
                        if (mapIframe) {
                            // Gunakan URL Google Maps yang valid untuk embed
                            mapIframe.src =
                                `https://maps.google.com/maps?q=${currentLatitude},${currentLongitude}&hl=id&z=17&output=embed`;
                        }

                        // Validasi jarak
                        if (distance !== false && distance <= allowedRadius) {
                            locationStatusElement.textContent = 'Dalam Radius';
                            locationStatusElement.className = 'location-status location-ok';
                            isLocationValid = true;
                            locationErrorElement.style.display = 'none';
                        } else if (distance !== false) {
                            locationStatusElement.textContent = 'Luar Radius!';
                            locationStatusElement.className = 'location-status location-error';
                            isLocationValid = false;
                            locationErrorElement.textContent =
                                `Anda ${Math.round(distance)}m dari kantor (batas: ${allowedRadius}m).`;
                            locationErrorElement.style.display = 'block';
                        } else { // Jika calculateDistance return false
                            locationStatusElement.textContent = 'Error Hitung';
                            locationStatusElement.className = 'location-status location-error';
                            isLocationValid = false;
                            locationErrorElement.textContent = 'Gagal menghitung jarak.';
                            locationErrorElement.style.display = 'block';
                        }
                        checkEnableButton(); // Periksa ulang status tombol utama
                    },
                    (error) => {
                        console.error("Geolocation Error:", error);
                        if (locationStatusElement) {
                            locationStatusElement.textContent = 'Error';
                            locationStatusElement.className = 'location-status location-error';
                        }
                        let errorMsg = 'Gagal mendapatkan lokasi: ';
                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                errorMsg += "Izin lokasi ditolak.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMsg += "Informasi lokasi tidak tersedia.";
                                break;
                            case error.TIMEOUT:
                                errorMsg += "Timeout saat mencari lokasi.";
                                break;
                            default:
                                errorMsg += "Error tidak diketahui (" + error.code + ").";
                        }
                        if (locationErrorElement) {
                            locationErrorElement.textContent = errorMsg +
                                ' Pastikan GPS/Layanan Lokasi aktif dan izin diberikan.';
                            locationErrorElement.style.display = 'block';
                        }
                        isLocationValid = false;
                        checkEnableButton(); // Periksa ulang status tombol utama
                    }, {
                        enableHighAccuracy: true, // Coba dapatkan lokasi paling akurat
                        timeout: 15000, // Batas waktu 15 detik
                        maximumAge: 0 // Jangan gunakan cache lokasi
                    }
                );
            }

            // --- Fungsi Kamera ---
            async function startCamera() {
                if (!videoPreview || !captureButton || !retakeButton || !selfiePreview || !selfieErrorElement ||
                    !captureCanvas) {
                    console.warn("Elemen kamera tidak ditemukan.");
                    return; // Hentikan jika elemen penting tidak ada
                }


                // Reset tampilan kamera
                selfieErrorElement.style.display = 'none';
                captureButton.disabled = true; // Disable sampai stream siap
                retakeButton.classList.add('hidden');
                captureButton.classList.remove('hidden'); // Pastikan tombol capture terlihat
                videoPreview.classList.remove('hidden'); // Pastikan video preview terlihat
                selfiePreview.innerHTML = ''; // Kosongkan preview selfie sebelumnya
                isSelfieTaken = false;
                if (selfieDataInput) selfieDataInput.value = '';

                try {
                    // Hentikan stream lama jika ada
                    stopCamera();

                    // Minta akses kamera depan (user-facing)
                    currentStream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'user', // Prioritaskan kamera depan
                            // Minta resolusi yang umum, browser akan memilih yang terdekat
                            width: {
                                ideal: 640
                            },
                            height: {
                                ideal: 480
                            }
                        },
                        audio: false // Tidak perlu audio
                    });

                    videoPreview.srcObject = currentStream;

                    // Tunggu metadata video dimuat untuk dapatkan ukuran asli
                    videoPreview.onloadedmetadata = () => {
                        const videoWidth = videoPreview.videoWidth;
                        const videoHeight = videoPreview.videoHeight;
                        captureCanvas.width = videoWidth;
                        captureCanvas.height = videoHeight;

                        // Tambahkan ini untuk memastikan ukuran responsif
                        videoPreview.style.maxWidth = '100%';
                        videoPreview.style.width = 'auto';
                        videoPreview.style.height = 'auto';

                        if (videoWidth && videoHeight) {
                            videoPreview.style.aspectRatio = `${videoWidth}/${videoHeight}`;
                        } else {
                            videoPreview.style.aspectRatio = '4/3';
                        }

                        captureButton.disabled = false;
                        checkEnableButton();
                    };

                    // Handler jika user mencabut izin di tengah jalan (opsional)
                    currentStream.getVideoTracks()[0].onended = () => {
                        console.log('Stream kamera berakhir (mungkin izin dicabut).');
                        stopCamera();
                        captureButton.disabled = true;
                        checkEnableButton();
                    };

                } catch (err) {
                    console.error("Camera Error:", err);
                    captureButton.disabled = true;
                    let errorMsg = 'Gagal akses kamera: ';
                    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                        errorMsg += 'Izin akses kamera ditolak.';
                    } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                        errorMsg += 'Tidak ada kamera yang ditemukan.';
                    } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                        errorMsg += 'Kamera sedang digunakan oleh aplikasi lain.';
                    } else {
                        errorMsg += `${err.name} (${err.message})`;
                    }
                    selfieErrorElement.textContent = errorMsg + ' Pastikan izin telah diberikan.';
                    selfieErrorElement.style.display = 'block';
                    checkEnableButton(); // Periksa ulang status tombol utama
                }
            }

            // --- Fungsi Capture Selfie ---
            if (captureButton) {
                captureButton.addEventListener('click', function() {
                    if (!currentStream || !captureCanvas || !videoPreview || !selfieDataInput || !
                        selfiePreview || !retakeButton) return;

                    // Pastikan canvas punya ukuran yang benar (sesuai stream)
                    if (captureCanvas.width === 0 || captureCanvas.height === 0) {
                        // Coba set ulang jika belum ada ukuran
                        const videoWidth = videoPreview.videoWidth;
                        const videoHeight = videoPreview.videoHeight;
                        if (videoWidth && videoHeight) {
                            captureCanvas.width = videoWidth;
                            captureCanvas.height = videoHeight;
                        } else {
                            console.error("Ukuran video tidak valid untuk capture.");
                            Swal.fire('Error', 'Gagal mendapatkan ukuran video untuk mengambil gambar.',
                                'error');
                            return;
                        }
                    }

                    const context = captureCanvas.getContext('2d');
                    // Gambar frame saat ini dari video ke canvas
                    context.drawImage(videoPreview, 0, 0, captureCanvas.width, captureCanvas.height);

                    try {
                        // Konversi canvas ke Data URL (JPEG, kualitas 80%)
                        // Kualitas bisa disesuaikan (0.1 - 1.0) untuk ukuran file
                        const dataUrl = captureCanvas.toDataURL('image/jpeg', 0.8);

                        // Simpan data URL ke input hidden
                        selfieDataInput.value = dataUrl;

                        // Tampilkan preview gambar yang diambil
                        selfiePreview.innerHTML =
                            `<img src="${dataUrl}" alt="Selfie Preview" style="max-width: 100%; max-height: 300px; object-fit: contain; border-radius: 0.25rem; border: 1px solid #ccc;">`;

                        isSelfieTaken = true;
                        if (selfieErrorElement) selfieErrorElement.style.display =
                            'none'; // Sembunyikan error jika ada

                        // Tampilkan tombol retake, sembunyikan video & capture
                        videoPreview.classList.add('hidden');
                        captureButton.classList.add('hidden');
                        retakeButton.classList.remove('hidden');

                        // Hentikan stream kamera setelah capture berhasil
                        stopCamera();

                        checkEnableButton(); // Periksa ulang status tombol utama
                    } catch (e) {
                        console.error("Error converting canvas to data URL:", e);
                        Swal.fire('Error', 'Gagal memproses gambar selfie. Coba lagi.', 'error');
                        isSelfieTaken = false;
                        checkEnableButton(); // Periksa ulang status tombol utama
                    }
                });
            }

            // --- Fungsi Retake Selfie ---
            if (retakeButton) {
                retakeButton.addEventListener('click', function() {
                    startCamera(); // Panggil startCamera untuk memulai ulang proses
                });
            }

            // --- Fungsi Hentikan Kamera ---
            function stopCamera() {
                if (currentStream) {
                    currentStream.getTracks().forEach(track => {
                        track.stop(); // Hentikan setiap track (video)
                    });
                    currentStream = null;
                    if (videoPreview) videoPreview.srcObject = null; // Hapus source dari elemen video
                    console.log("Kamera dihentikan.");
                }
            }

            // --- Fungsi Cek Kesiapan Tombol Utama ---
            function checkEnableButton() {
                if (!attendanceButton) return; // Jika tombol tidak ada, keluar

                let shiftOk = true;
                // Validasi shift hanya diperlukan saat check-in
                if (attendanceAction === 'check_in') {
                    shiftOk = shiftSelect && shiftSelect.value !== ''; // Pastikan shift sudah dipilih
                    if (shiftSelect) {
                        const shiftError = document.getElementById('shift-error');
                        // Toggle class 'is-invalid' dan tampilkan/sembunyikan pesan error
                        shiftSelect.classList.toggle('is-invalid', !shiftOk);
                        if (shiftError) shiftError.style.display = shiftOk ? 'none' : 'block';
                    }
                } else {
                    // Jika check-out, pastikan tidak ada error shift yang tampil
                    if (shiftSelect) shiftSelect.classList.remove('is-invalid');
                    const shiftError = document.getElementById('shift-error');
                    if (shiftError) shiftError.style.display = 'none';
                }

                // Validasi lokasi
                if (locationErrorElement) {
                    // Sembunyikan/tampilkan error lokasi berdasarkan isLocationValid
                    locationErrorElement.style.display = isLocationValid ? 'none' : 'block';
                }

                // Validasi selfie
                if (selfieErrorElement) {
                    // Sembunyikan/tampilkan error selfie berdasarkan isSelfieTaken
                    // (Tapi hanya tampilkan jika user belum ambil foto sama sekali)
                    // Jika user klik submit sebelum ambil foto, validasi di submit handler akan menampilkannya.
                    // Di sini kita hanya menyembunyikan jika sudah diambil.
                    if (isSelfieTaken) selfieErrorElement.style.display = 'none';
                }


                // Tombol utama aktif jika: lokasi valid, selfie sudah diambil, shift valid (jika check-in), dan tidak sedang proses
                attendanceButton.disabled = !(isLocationValid && isSelfieTaken && shiftOk) || isProcessing;
            }

            // --- Listener untuk perubahan shift (hanya jika check-in) ---
            if (shiftSelect && attendanceAction === 'check_in') {
                shiftSelect.addEventListener('change', checkEnableButton);
            }

            // --- Fungsi Submit Absensi (AJAX) ---
            if (attendanceButton) {
                attendanceButton.addEventListener('click', function(event) {
                    event.preventDefault(); // Mencegah submit form default jika ada

                    const selectedShiftId = (attendanceAction === 'check_in' && shiftSelect) ? shiftSelect
                        .value : null;

                    // --- Validasi Frontend Sebelum Submit ---
                    let isValid = true;
                    checkEnableButton(); // Jalankan check terakhir untuk update UI error

                    // Validasi Shift (hanya Check-in)
                    if (attendanceAction === 'check_in' && !selectedShiftId) {
                        Swal.fire('Error', 'Silakan pilih shift kerja Anda.', 'warning');
                        if (shiftSelect) shiftSelect.focus();
                        isValid = false;
                    }

                    // Validasi Lokasi
                    if (!isLocationValid) {
                        Swal.fire('Error',
                            'Lokasi Anda tidak valid (mungkin di luar radius atau gagal dideteksi).',
                            'warning');
                        // Mungkin scroll ke bagian lokasi jika ada
                        document.getElementById('location-info')?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        isValid = false;
                    }

                    // Validasi Selfie
                    if (!isSelfieTaken || !selfieDataInput || !selfieDataInput.value) {
                        Swal.fire('Error', 'Anda wajib mengambil foto selfie untuk absensi.', 'warning');
                        if (selfieErrorElement) selfieErrorElement.style.display = 'block';
                        document.getElementById('selfie-preview')?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        isValid = false;
                    }

                    // Jika ada validasi yang gagal, hentikan proses submit
                    if (!isValid) {
                        return;
                    }

                    // --- Mulai Proses Submit ---
                    isProcessing = true;
                    this.disabled = true; // Disable tombol
                    if (buttonSpinner) buttonSpinner.style.display = 'inline-block'; // Tampilkan spinner
                    if (buttonText) buttonText.textContent = 'Memproses...'; // Ubah teks tombol

                    const formData = {
                        latitude: latitudeInput.value,
                        longitude: longitudeInput.value,
                        selfie_data: selfieDataInput.value, // Data URL dari canvas
                        action: actionTypeInput.value, // 'check_in' or 'check_out'
                        shift_id: selectedShiftId, // Akan null jika check_out
                        _token: csrfToken // Sertakan CSRF token
                    };

                    fetch("{{ route('attendances.store') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken, // Atau ambil dari formData jika dimasukkan di sana
                                'Accept': 'application/json' // Beritahu server kita menerima JSON
                            },
                            body: JSON.stringify(formData) // Kirim data sebagai JSON string
                        })
                        .then(response => {
                            // Cek jika response tidak OK (status bukan 2xx)
                            if (!response.ok) {
                                // Coba parse body response sebagai JSON untuk pesan error dari server
                                return response.json().then(errData => {
                                    // Buat Error baru dengan pesan dari server atau status text
                                    let errorMsg =
                                        `Error ${response.status}: ${response.statusText}`;
                                    if (errData && errData.message) {
                                        errorMsg = errData
                                            .message; // Gunakan pesan spesifik dari server jika ada
                                    } else if (errData && errData.errors) {
                                        // Jika ada error validasi Laravel
                                        errorMsg = Object.values(errData.errors).flat().join(
                                            '\n');
                                    }
                                    throw new Error(errorMsg);
                                }).catch(parseError => {
                                    // Jika body response bukan JSON atau parsing gagal
                                    console.error("Error parsing error response:", parseError);
                                    // Fallback ke status text
                                    throw new Error(
                                        `Error ${response.status}: ${response.statusText}`);
                                });
                            }
                            // Jika response OK, parse body sebagai JSON
                            return response.json();
                        })
                        .then(data => {
                            // Server diharapkan mengembalikan { success: true, message: '...' }
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: data.message, // Pesan sukses dari server
                                    timer: 2500, // Tutup otomatis setelah 2.5 detik
                                    showConfirmButton: false,
                                    allowOutsideClick: false // Cegah penutupan dengan klik di luar
                                }).then(() => {
                                    window.location
                                        .reload(); // Reload halaman untuk update status
                                });
                                stopCamera(); // Hentikan kamera setelah sukses
                            } else {
                                // Jika server return success: false atau format tidak sesuai
                                throw new Error(data.message ||
                                    'Terjadi kesalahan yang tidak diketahui dari server.');
                            }
                        })
                        .catch(error => {
                            console.error('Attendance Submit Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal Menyimpan Absensi',
                                text: error.message ||
                                    'Tidak dapat terhubung ke server atau terjadi error.', // Tampilkan pesan error
                                confirmButtonText: 'Coba Lagi'
                            });
                            // Kembalikan state tombol jika gagal
                            isProcessing = false;
                            this.disabled = false;
                            if (buttonSpinner) buttonSpinner.style.display = 'none';
                            if (buttonText) buttonText.textContent = attendanceAction === 'check_in' ?
                                'Lakukan Check-in' : 'Lakukan Check-out';
                            checkEnableButton(); // Re-enable tombol jika kondisi lain terpenuhi
                        });
                });
            }

            // --- Fungsi Hitung Jarak (Haversine Formula) ---
            function calculateDistance(lat1, lon1, lat2, lon2) {
                // Pastikan semua input valid
                if (lat1 == null || lon1 == null || lat2 == null || lon2 == null) {
                    console.error("Input koordinat tidak lengkap untuk menghitung jarak.");
                    return false; // Return false jika input tidak valid
                }

                const R = 6371e3; // Radius bumi dalam meter
                const phi1 = lat1 * Math.PI / 180; // Konversi lat1 ke radian
                const phi2 = lat2 * Math.PI / 180; // Konversi lat2 ke radian
                const deltaPhi = (lat2 - lat1) * Math.PI / 180; // Selisih lat dalam radian
                const deltaLambda = (lon2 - lon1) * Math.PI / 180; // Selisih lon dalam radian

                const a = Math.sin(deltaPhi / 2) * Math.sin(deltaPhi / 2) +
                    Math.cos(phi1) * Math.cos(phi2) *
                    Math.sin(deltaLambda / 2) * Math.sin(deltaLambda / 2);

                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

                const distance = R * c; // Jarak dalam meter
                return distance;
            }

            // --- Inisialisasi Awal Saat Halaman Dimuat ---
            initializePage();

            // --- Cleanup saat halaman ditutup/ditinggalkan ---
            // Berguna untuk memastikan stream kamera benar-benar berhenti
            window.addEventListener('beforeunload', function() {
                stopCamera();
                if (timeInterval) clearInterval(timeInterval); // Hentikan interval waktu
            });

            // Optional: Handle visibility change (jika tab tidak aktif lalu aktif lagi)
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    // Jika tab kembali aktif, coba mulai ulang kamera dan lokasi jika diperlukan
                    if (attendanceAction !== 'completed' && !isSelfieTaken && !currentStream) {
                        console.log("Tab aktif kembali, mencoba memulai ulang kamera.");
                        startCamera();
                    }
                    // Anda mungkin juga ingin refresh lokasi jika sudah lama tidak aktif
                    // getLocation();
                } else {
                    // Jika tab tidak aktif, hentikan kamera untuk hemat baterai
                    console.log("Tab tidak aktif, menghentikan kamera.");
                    stopCamera();
                }
            });

        });
    </script>
@endpush
