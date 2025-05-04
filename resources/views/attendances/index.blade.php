{{-- resources/views/attendances/index.blade.php --}}
@extends('layout.app') {{-- Sesuaikan dengan layout utama Anda --}}

@push('css')
{{-- CSS Khusus untuk Halaman Absensi --}}
<style>
    /* Beri tinggi spesifik untuk iframe peta */
    #map-iframe {
        height: 300px; /* Sesuaikan tinggi peta */
        width: 100%;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        background-color: #e9ecef; /* Warna placeholder saat loading */
    }
    /* Styling untuk video preview kamera */
    #video-preview {
        width: 100%;
        max-width: 400px; /* Batasi lebar video preview */
        height: auto;
        border: 1px solid #ccc;
        margin-bottom: 10px;
        background-color: #000; /* Background hitam untuk video */
        display: block; /* Pastikan video tampil sebagai block */
        margin-left: auto; /* Tengahkan video jika container lebih lebar */
        margin-right: auto;
    }
    /* Canvas untuk capture disembunyikan */
    #capture-canvas {
        display: none;
    }
    /* Styling untuk preview selfie */
    #selfie-preview img {
        max-width: 150px;
        height: auto;
        border: 1px solid #ccc;
        margin-top: 5px;
    }
    /* Styling untuk status lokasi */
    .location-status { font-weight: bold; }
    .location-ok { color: #198754; } /* Hijau */
    .location-error { color: #dc3545; } /* Merah */
    /* Styling untuk spinner di tombol */
    .spinner-border-sm { vertical-align: middle; }
    /* Styling untuk pesan error validasi JS */
    .js-invalid-feedback {
        display: none; /* Sembunyikan default */
        width: 100%;
        margin-top: .25rem;
        font-size: .875em;
        color: #dc3545; /* Warna error Bootstrap */
    }
    /* Tampilkan pesan error jika input terkait invalid */
    .form-control.is-invalid ~ .js-invalid-feedback,
    .form-select.is-invalid ~ .js-invalid-feedback {
        display: block;
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
                    <h3>Absensi Harian</h3>
                    {{-- Tampilkan waktu realtime --}}
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
    {{-- Akhir Header Halaman --}}

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Formulir Absensi Hari Ini ({{ \Carbon\Carbon::now()->isoFormat('dddd, D MMMM YYYY') }})</h4>
            </div>
            <div class="card-body">

                {{-- Tampilkan Status Absensi Hari Ini --}}
                <div class="alert alert-light-secondary color-secondary mb-4">
                    <h4 class="alert-heading">Status Anda Hari Ini:</h4>
                    @if($attendanceAction === 'check_in')
                        <p>Anda belum melakukan Check-in.</p>
                    @elseif($attendanceAction === 'check_out')
                        <p>Anda sudah Check-in pada pukul: <strong>{{ $checkInTime ? $checkInTime->format('H:i:s') : 'N/A' }}</strong>. Silakan lakukan Check-out.</p>
                    @elseif($attendanceAction === 'completed')
                        <p>Anda sudah menyelesaikan absensi hari ini (Check-in: {{ $checkInTime ? $checkInTime->format('H:i:s') : 'N/A' }}, Check-out: {{ $todaysAttendance->clock_out_time ? $todaysAttendance->clock_out_time->format('H:i:s') : 'N/A' }}).</p>
                    @endif
                    {{-- Tampilkan info absensi hari ini jika ada --}}
                    @if($todaysAttendance)
                        <hr>
                        <small>
                            Lokasi Check-in: {{ $todaysAttendance->clock_in_location_status ?? '-' }} |
                            Lokasi Check-out: {{ $todaysAttendance->clock_out_location_status ?? '-' }} |
                            Shift: {{ $todaysAttendance->shift->name ?? 'N/A' }}
                        </small>
                    @endif
                </div>

                {{-- Tampilkan Form hanya jika belum selesai absensi --}}
                @if($attendanceAction !== 'completed')
                    {{-- 1. Pilihan Shift (Hanya saat Check-in) --}}
                    <div class="form-group mb-3" id="shift-selection-group" style="{{ $attendanceAction === 'check_in' ? '' : 'display: none;' }}">
                        <label for="shift_id" class="form-label">Pilih Shift Kerja Hari Ini <span class="text-danger">*</span></label>
                        <select id="shift_id" name="shift_id" class="form-select" {{ $attendanceAction === 'check_in' ? 'required' : '' }}>
                            <option value="" disabled selected>-- Pilih Shift --</option>
                            @foreach ($shifts as $shift)
                                <option value="{{ $shift->id }}">{{ $shift->name }} ({{ \Carbon\Carbon::parse($shift->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($shift->end_time)->format('H:i') }})</option>
                            @endforeach
                        </select>
                        {{-- Pesan error JS --}}
                        <div class="js-invalid-feedback" id="shift-error">Pilih shift kerja Anda.</div>
                    </div>

                    {{-- 2. Geolocation & iFrame Map --}}
                    <div class="mb-3">
                        <label class="form-label">Lokasi Anda:</label>
                        <iframe id="map-iframe" src="" frameborder="0" style="border:0;" allowfullscreen="" aria-hidden="false" tabindex="0" title="Peta Lokasi Anda"></iframe>
                        <div id="location-info" class="mt-2">
                            Status: <span id="location-status" class="location-status">-</span> |
                            Jarak: <span id="location-distance">-</span> meter<br>
                            <small class="text-muted" id="location-coords">Koordinat: -</small>
                        </div>
                        {{-- Pesan error JS --}}
                        <div class="js-invalid-feedback" id="location-error">Gagal mendapatkan lokasi atau Anda di luar radius.</div>
                    </div>

                    {{-- 3. Kamera & Selfie --}}
                    <div class="mb-3">
                        <label class="form-label">Selfie Absensi: <span class="text-danger">*</span></label>
                        <div class="text-center">
                            <video id="video-preview" autoplay playsinline muted></video> {{-- Tambah muted --}}
                            <canvas id="capture-canvas"></canvas> {{-- Hidden canvas --}}
                            <button type="button" id="capture-button" class="btn btn-secondary btn-sm mb-2" disabled>
                                <i class="bi bi-camera-fill"></i> Ambil Foto Selfie
                            </button>
                            <div id="selfie-preview" class="mt-2"></div> {{-- Untuk menampilkan hasil foto --}}
                             {{-- Pesan error JS --}}
                            <div class="js-invalid-feedback" id="selfie-error">Wajib mengambil foto selfie.</div>
                        </div>
                    </div>

                    {{-- 4. Tombol Aksi Utama --}}
                    <div class="d-grid gap-2 mt-4">
                        <button type="button" id="attendance-button" class="btn btn-primary btn-lg" disabled>
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
                            <span id="button-text">Memuat Status...</span>
                        </button>
                    </div>

                    {{-- Hidden inputs untuk data yg dikirim via JS --}}
                    <input type="hidden" id="latitude" name="latitude">
                    <input type="hidden" id="longitude" name="longitude">
                    <input type="hidden" id="selfie_data" name="selfie_data">
                    <input type="hidden" id="action_type" name="action" value="{{ $attendanceAction }}">

                @else
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill"></i> Anda sudah menyelesaikan absensi untuk hari ini.
                    </div>
                @endif

            </div> {{-- End Card Body --}}
        </div> {{-- End Card --}}
    </section>

</div>
@endsection

@push('js')
{{-- Pastikan SweetAlert2 & Bootstrap JS sudah dimuat di layout --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
{{-- Hapus script Google Maps API Key --}}

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
    const selfiePreview = document.getElementById('selfie-preview');
    const selfieErrorElement = document.getElementById('selfie-error');
    const attendanceButton = document.getElementById('attendance-button');
    const buttonText = document.getElementById('button-text');
    const buttonSpinner = attendanceButton.querySelector('.spinner-border');
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const selfieDataInput = document.getElementById('selfie_data');
    const actionTypeInput = document.getElementById('action_type');
    const csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : document.querySelector('input[name="_token"]').value;

    // --- State Variables ---
    let currentStream = null; let currentLatitude = null; let currentLongitude = null;
    let isLocationValid = false; let isSelfieTaken = false; let isProcessing = false;
    const attendanceAction = "{{ $attendanceAction }}";

    // --- Office Location & Radius ---
    const officeLat = {{ config('attendance.office_latitude', -6.183333) }};
    const officeLng = {{ config('attendance.office_longitude', 106.833333) }};
    const allowedRadius = {{ config('attendance.allowed_radius_meters', 500) }};

    // --- Update Waktu Realtime ---
    function updateTime() {
        const now = new Date();
        if(currentTimeElement) {
            currentTimeElement.textContent = `Waktu Saat Ini: ${now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
        }
    }
    const timeInterval = setInterval(updateTime, 1000);
    updateTime();

    // --- Fungsi Inisialisasi Halaman ---
    function initializePage() {
        if (attendanceAction === 'completed') {
            attendanceButton.disabled = true;
            buttonText.textContent = 'Absensi Selesai';
            if(timeInterval) clearInterval(timeInterval); // Hentikan update waktu jika sudah selesai
            return;
        }
        buttonText.textContent = attendanceAction === 'check_in' ? 'Lakukan Check-in' : 'Lakukan Check-out';
        buttonSpinner.style.display = 'none';
        attendanceButton.disabled = true;
        captureButton.disabled = true;
        getLocation();
        startCamera();
    }

    // --- Fungsi Geolocation ---
    function getLocation() {
        locationStatusElement.textContent = 'Mencari...'; locationStatusElement.className = 'location-status';
        locationDistanceElement.textContent = '-'; locationCoordsElement.textContent = 'Koordinat: -';
        locationErrorElement.style.display = 'none';
        if (mapIframe) mapIframe.src = '';

        if (!navigator.geolocation) {
            locationStatusElement.textContent = 'Not Supported';
            locationErrorElement.textContent = 'Geolocation tidak didukung browser ini.';
            locationErrorElement.style.display = 'block';
            isLocationValid = false; checkEnableButton(); return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                currentLatitude = position.coords.latitude;
                currentLongitude = position.coords.longitude;
                latitudeInput.value = currentLatitude;
                longitudeInput.value = currentLongitude;
                locationCoordsElement.textContent = `Koordinat: ${currentLatitude.toFixed(6)}, ${currentLongitude.toFixed(6)}`;

                const distance = calculateDistance(currentLatitude, currentLongitude, officeLat, officeLng);
                locationDistanceElement.textContent = distance !== false ? Math.round(distance) : 'Error';

                if (mapIframe) {
                    mapIframe.src = `https://www.google.com/maps?q=${currentLatitude},${currentLongitude}&hl=id&z=17&output=embed`;
                }

                if (distance !== false && distance <= allowedRadius) {
                    locationStatusElement.textContent = 'Dalam Radius'; locationStatusElement.className = 'location-status location-ok';
                    isLocationValid = true;
                } else if (distance !== false) {
                    locationStatusElement.textContent = 'Luar Radius!'; locationStatusElement.className = 'location-status location-error';
                    isLocationValid = false;
                    locationErrorElement.textContent = `Anda ${Math.round(distance)}m dari kantor (batas: ${allowedRadius}m).`;
                    locationErrorElement.style.display = 'block';
                } else {
                    locationStatusElement.textContent = 'Error Hitung'; locationStatusElement.className = 'location-status location-error';
                    isLocationValid = false;
                }
                checkEnableButton();
            },
            (error) => {
                console.error("Geolocation Error:", error);
                locationStatusElement.textContent = 'Error'; locationStatusElement.className = 'location-status location-error';
                let errorMsg = 'Gagal mendapatkan lokasi: ';
                switch(error.code) {
                    case error.PERMISSION_DENIED: errorMsg += "Izin lokasi ditolak."; break;
                    case error.POSITION_UNAVAILABLE: errorMsg += "Informasi lokasi tidak tersedia."; break;
                    case error.TIMEOUT: errorMsg += "Timeout saat mencari lokasi."; break;
                    default: errorMsg += "Error tidak diketahui.";
                }
                locationErrorElement.textContent = errorMsg + ' Pastikan GPS/Layanan Lokasi aktif.';
                locationErrorElement.style.display = 'block';
                isLocationValid = false; checkEnableButton();
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    }

    // --- Fungsi Kamera ---
    async function startCamera() {
        selfieErrorElement.style.display = 'none';
        try {
            currentStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            videoPreview.srcObject = currentStream;
            videoPreview.style.display = 'block';
            captureButton.disabled = false;
            selfiePreview.innerHTML = '';
            isSelfieTaken = false;
            checkEnableButton();
        } catch (err) {
            console.error("Camera Error:", err);
            captureButton.disabled = true; videoPreview.style.display = 'none';
            selfieErrorElement.textContent = `Gagal akses kamera: ${err.message}. Berikan izin.`;
            selfieErrorElement.style.display = 'block';
            isSelfieTaken = false; checkEnableButton();
        }
    }

    // --- Fungsi Capture Selfie ---
    if (captureButton) {
        captureButton.addEventListener('click', function() {
            if (!currentStream) return;
            const context = captureCanvas.getContext('2d');
            captureCanvas.width = videoPreview.videoWidth;
            captureCanvas.height = videoPreview.videoHeight;
            context.drawImage(videoPreview, 0, 0, captureCanvas.width, captureCanvas.height);
            const dataUrl = captureCanvas.toDataURL('image/jpeg', 0.8);
            selfieDataInput.value = dataUrl;
            selfiePreview.innerHTML = `<img src="${dataUrl}" alt="Selfie Preview">`;
            isSelfieTaken = true;
            selfieErrorElement.style.display = 'none';
            checkEnableButton();
            // Optional: stopCamera(); videoPreview.style.display = 'none'; this.disabled = true;
        });
    }

    // --- Fungsi Hentikan Kamera ---
    function stopCamera() {
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
            currentStream = null; videoPreview.srcObject = null;
        }
    }

    // --- Fungsi Cek Kesiapan Tombol Utama ---
    function checkEnableButton() {
        let shiftOk = true;
        if (attendanceAction === 'check_in') {
            shiftOk = shiftSelect && shiftSelect.value !== '';
            if (shiftSelect) { // Selalu cek elemennya ada
                shiftSelect.classList.toggle('is-invalid', !shiftOk);
                document.getElementById('shift-error').style.display = shiftOk ? 'none' : 'block';
            }
        } else {
            if(shiftSelect) shiftSelect.classList.remove('is-invalid');
            if(document.getElementById('shift-error')) document.getElementById('shift-error').style.display = 'none';
        }
        // Update status tombol utama
        attendanceButton.disabled = !(isLocationValid && isSelfieTaken && shiftOk) || isProcessing;
    }

    // --- Listener untuk perubahan shift ---
    if (shiftSelect && attendanceAction === 'check_in') {
        shiftSelect.addEventListener('change', checkEnableButton);
    }

    // --- Fungsi Submit Absensi (AJAX) ---
    if (attendanceButton) {
        attendanceButton.addEventListener('click', function() {
            // Validasi ulang sebelum kirim
            const selectedShiftId = (attendanceAction === 'check_in') ? shiftSelect.value : null;
            if (attendanceAction === 'check_in' && !selectedShiftId) {
                 Swal.fire('Error', 'Silakan pilih shift kerja Anda.', 'error');
                 shiftSelect.classList.add('is-invalid'); shiftSelect.focus(); return;
            }
            if (!isLocationValid) { Swal.fire('Error', 'Lokasi Anda tidak valid atau di luar radius.', 'error'); return; }
            if (!isSelfieTaken || !selfieDataInput.value) { Swal.fire('Error', 'Silakan ambil foto selfie.', 'error'); selfieErrorElement.style.display = 'block'; return; }

            // Tampilkan loading & disable tombol
            isProcessing = true; this.disabled = true;
            buttonSpinner.style.display = 'inline-block'; buttonText.textContent = 'Memproses...';

            const formData = { latitude: latitudeInput.value, longitude: longitudeInput.value, selfie_data: selfieDataInput.value, action: actionTypeInput.value, shift_id: selectedShiftId };

            // Kirim data
            fetch("{{ route('attendances.store') }}", { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: JSON.stringify(formData) })
            .then(response => {
                if (!response.ok) { return response.json().then(err => { throw new Error(err.message || `Error ${response.status}`); }); }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, timer: 2000, showConfirmButton: false })
                        .then(() => { window.location.reload(); });
                    stopCamera();
                } else { throw new Error(data.message || 'Error tidak diketahui.'); }
            })
            .catch(error => {
                console.error('Attendance Submit Error:', error);
                Swal.fire('Gagal', error.message || 'Gagal menyimpan data absensi.', 'error');
                isProcessing = false; this.disabled = false; buttonSpinner.style.display = 'none';
                buttonText.textContent = attendanceAction === 'check_in' ? 'Lakukan Check-in' : 'Lakukan Check-out';
            });
        });
    }

    // --- Fungsi Hitung Jarak (Haversine) ---
    function calculateDistance(lat1, lon1, lat2, lon2) {
        if (!lat1 || !lon1 || !lat2 || !lon2) return false;
        const R = 6371e3; const phi1 = lat1 * Math.PI/180; const phi2 = lat2 * Math.PI/180;
        const deltaPhi = (lat2-lat1) * Math.PI/180; const deltaLambda = (lon2-lon1) * Math.PI/180;
        const a = Math.sin(deltaPhi/2) * Math.sin(deltaPhi/2) + Math.cos(phi1) * Math.cos(phi2) * Math.sin(deltaLambda/2) * Math.sin(deltaLambda/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); return R * c;
    }

    // --- Inisialisasi Awal ---
    initializePage();

});
</script>
@endpush
