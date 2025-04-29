{{-- resources/views/overtimes/recap/index.blade.php --}}
@php
    use Carbon\Carbon; // <-- TAMBAHKAN IMPORT CARBON DI SINI
@endphp
@extends('layout.app')

@section('content')
    <div id="main">
        {{-- Header Halaman & Breadcrumb --}}
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Rekap Lembur Karyawan</h3>
                        <p class="text-subtitle text-muted">Lihat ringkasan dan detail lembur berdasarkan periode.</p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Rekap Lembur</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        {{-- Akhir Header Halaman --}}

        {{-- Filter Form --}}
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Filter Data</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('overtimes.recap.index') }}" method="GET" class="form form-horizontal">
                         {{-- ... (kode form filter lengkap seperti sebelumnya) ... --}}
                         <div class="form-body">
                            <div class="row">
                                {{-- Filter Tanggal --}}
                                <div class="col-md-3"><label for="start_date">Tanggal Mulai</label></div>
                                <div class="col-md-3 form-group"><input type="date" id="start_date" class="form-control" name="start_date" value="{{ $startDate ?? '' }}" required></div>
                                <div class="col-md-3"><label for="end_date">Tanggal Selesai</label></div>
                                <div class="col-md-3 form-group"><input type="date" id="end_date" class="form-control" name="end_date" value="{{ $endDate ?? '' }}" required></div>

                                {{-- Filter Karyawan & Vendor --}}
                                @if (in_array(Auth::user()->role, ['admin', 'manajemen']))
                                    <div class="col-md-3"><label for="user_id">Karyawan</label></div>
                                    <div class="col-md-3 form-group">
                                        <select name="user_id" id="user_id" class="form-select">
                                            <option value="">-- Semua Karyawan --</option>
                                            @foreach ($users as $user) <option value="{{ $user->id }}" {{ $selectedUserId == $user->id ? 'selected' : '' }}>{{ $user->name }}</option> @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3"><label for="vendor_id">Vendor</label></div>
                                    <div class="col-md-3 form-group">
                                        <select name="vendor_id" id="vendor_id" class="form-select">
                                            <option value="">-- Semua Vendor --</option>
                                            <option value="is_null" {{ $selectedVendorId == 'is_null' ? 'selected' : '' }}>Internal Karyawan</option>
                                            @foreach ($vendors as $vendor) <option value="{{ $vendor->id }}" {{ $selectedVendorId == $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option> @endforeach
                                        </select>
                                    </div>
                                @endif

                                {{-- Filter Status --}}
                                <div class="col-md-3"><label for="status">Status Lembur</label></div>
                                <div class="col-md-3 form-group">
                                    <select name="status" id="status" class="form-select">
                                        <option value="approved" {{ $selectedStatus == 'approved' ? 'selected' : '' }}>Approved</option>
                                        <option value="pending" {{ $selectedStatus == 'pending' ? 'selected' : '' }}>Pending (Asisten)</option>
                                        <option value="pending_manager_approval" {{ $selectedStatus == 'pending_manager_approval' ? 'selected' : '' }}>Pending (Manager)</option>
                                        <option value="rejected" {{ $selectedStatus == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                        <option value="cancelled" {{ $selectedStatus == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                        <option value="">-- Semua Status --</option>
                                    </select>
                                </div>

                                {{-- Tombol Submit & Export --}}
                                <div class="col-md-6 {{ Auth::user()->role === 'personil' ? 'offset-md-6' : '' }} mt-2 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary me-1 mb-1"><i class="bi bi-filter"></i> Tampilkan Rekap</button>
                                    {{-- Tombol Export hanya aktif jika filter sudah dijalankan --}}
                                    <a href="{{ route('overtimes.recap.export', request()->query()) }}" class="btn btn-success me-1 mb-1 @if (!$hasFiltered) disabled @endif">
                                        <i class="bi bi-file-earmark-excel"></i> Export Excel
                                    </a>
                                    <a href="{{ route('overtimes.recap.index') }}" class="btn btn-light-secondary mb-1">Reset Filter</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        {{-- Akhir Filter Form --}}

        {{-- Hasil Rekap (Tampilkan HANYA jika filter sudah dijalankan) --}}
        @if ($hasFiltered)
            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Hasil Rekap Lembur ({{ Carbon::parse($startDate)->format('d M Y') }} - {{ Carbon::parse($endDate)->format('d M Y') }})</h4>
                    </div>
                    <div class="card-body">
                        @if ($recapData->isEmpty())
                            <div class="alert alert-light-warning color-warning">
                                <i class="bi bi-exclamation-triangle"></i> Tidak ada data lembur ditemukan sesuai filter yang dipilih.
                            </div>
                        @else
                            @foreach ($recapData as $userData)
                                <div class="card border mb-3">
                                    {{-- ... (kode card header user seperti sebelumnya) ... --}}
                                    <div class="card-header bg-light p-2">
                                        <h5 class="card-title mb-0">{{ $userData['user']->name }} <small class="text-muted">({{ $userData['user']->jabatan }} - {{ $userData['user']->vendor->name ?? 'Internal' }})</small></h5>
                                    </div>
                                    <div class="card-body p-2">
                                        @if ($userData['details']->isEmpty())
                                            <p class="text-center text-muted my-2"><i>Tidak ada detail lembur pada periode vendor yang relevan.</i></p>
                                        @else
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                     {{-- ... (thead dan tbody tabel detail seperti sebelumnya) ... --}}
                                                    <thead><tr class="text-center"><th>Tanggal</th><th>Jam Mulai</th><th>Jam Selesai</th><th>Durasi</th><th>Uraian</th></tr></thead>
                                                    <tbody>
                                                        @foreach ($userData['details']->sortBy('tanggal_lembur') as $detail)
                                                            <tr>
                                                                <td class="text-center">{{ $detail->tanggal_lembur->format('d/m/Y') }}</td>
                                                                <td class="text-center">{{ $detail->jam_mulai->format('H:i') }}</td>
                                                                <td class="text-center">{{ $detail->jam_selesai->format('H:i') }}</td>
                                                                <td class="text-center"> @if (!is_null($detail->durasi_menit)) {{ floor($detail->durasi_menit / 60) }}j {{ $detail->durasi_menit % 60 }}m @else - @endif </td>
                                                                <td>{{ $detail->uraian_pekerjaan }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif

                                        {{-- Tampilkan Total per Periode Vendor --}}
                                        @foreach ($userData['periods'] as $periodInfo)
                                        <div class="alert alert-light-info color-info p-2 mt-2">
                                            <strong>Total Periode ({{ $periodInfo['start'] }} - {{ $periodInfo['end'] }}):</strong>
                                            {{ floor($periodInfo['total_minutes'] / 60) }} jam {{ $periodInfo['total_minutes'] % 60 }} menit
                                            @if ($periodInfo['total_minutes'] > 3240) <span class="badge bg-danger ms-2">Melebihi Batas 54 Jam!</span> @endif
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </section>
        @else
            {{-- Tampilkan pesan jika filter belum dijalankan --}}
            <div class="alert alert-light-info color-info">
                <i class="bi bi-info-circle"></i> Silakan pilih rentang tanggal dan filter lain (jika perlu), lalu klik tombol "Tampilkan Rekap" untuk melihat data.
            </div>
        @endif
{{-- Akhir Hasil Rekap --}}

</div>
@endsection

{{-- Tambahkan JS jika perlu untuk datepicker atau select2 --}}
{{-- @push('js') ... @endpush --}}
