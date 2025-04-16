{{-- filepath: resources/views/cuti/create.blade.php --}}
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

        <!-- Form pengajuan cuti -->
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Form Pengajuan Cuti</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('cuti.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <label for="jenis_cuti_id">Jenis Cuti</label>
                            <select name="jenis_cuti_id" id="jenis_cuti_id" class="form-control" required>
                                <option value="" disabled selected>Pilih Jenis Cuti</option>
                                @foreach ($jenisCuti as $cuti)
                                    <option value="{{ $cuti->id }}">{{ $cuti->nama_cuti }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="kuota_cuti">Kuota Cuti</label>
                            <input type="text" id="kuota_cuti" class="form-control" value="-" readonly>
                        </div>
                        <div class="form-group">
                            <label for="mulai_cuti">Mulai Cuti</label>
                            <input type="date" name="mulai_cuti" id="mulai_cuti" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="selesai_cuti">Selesai Cuti</label>
                            <input type="date" name="selesai_cuti" id="selesai_cuti" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="keperluan">Keperluan</label>
                            <textarea name="keperluan" id="keperluan" class="form-control" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="alamat_selama_cuti">Alamat Selama Cuti</label>
                            <textarea name="alamat_selama_cuti" id="alamat_selama_cuti" class="form-control" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="surat_sakit">Surat Sakit (Jika Diperlukan)</label>
                            <input type="file" name="surat_sakit" id="surat_sakit" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary">Ajukan Cuti</button>
                    </form>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
    document.getElementById('jenis_cuti_id').addEventListener('change', function () {
        const jenisCutiId = this.value;

        // Reset kuota cuti saat jenis cuti berubah
        document.getElementById('kuota_cuti').value = '-';

        if (jenisCutiId) {
            // Lakukan request AJAX untuk mendapatkan kuota cuti
            fetch(`{{ route('cuti.quota') }}?jenis_cuti_id=${jenisCutiId}`)
                .then(response => response.json())
                .then(data => {
                    // Tampilkan kuota cuti di input
                    document.getElementById('kuota_cuti').value = data.durasi_cuti + ' hari';
                })
                .catch(error => {
                    console.error('Error fetching kuota cuti:', error);
                });
        }
    });
</script>
@endsection