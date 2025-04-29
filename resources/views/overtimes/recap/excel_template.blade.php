{{-- resources/views/overtimes/recap/excel_template.blade.php --}}
{{-- Ini adalah template tabel HTML untuk export Excel --}}
<table>
    {{-- Header Informasi Filter --}}
    <thead>
        <tr>
            <th colspan="6" style="font-weight: bold; font-size: 14px; text-align: center;">REKAP LEMBUR KARYAWAN</th>
        </tr>
        <tr>
            <th colspan="6" style="font-weight: bold; text-align: center;">Periode:
                {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} -
                {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</th>
        </tr>
        <tr>
            <th align="left">Filter Karyawan:</th>
            <th align="left">{{ $selectedUserName }}</th>
            <th></th> {{-- Spacer --}}
            <th align="left">Filter Vendor:</th>
            <th align="left">{{ $selectedVendorName }}</th>
            <th></th> {{-- Spacer --}}
        </tr>
        <tr>
            <th align="left">Filter Status:</th>
            <th align="left">{{ Str::title($selectedStatus) }}</th>
            <th colspan="4"></th> {{-- Spacer --}}
        </tr>
        <tr>
            <th colspan="6" height="20"></th> {{-- Baris kosong sebagai spasi --}}
        </tr>
    </thead>

    {{-- Body Data --}}
    <tbody>
        {{-- Loop untuk setiap user dalam rekap --}}
        @forelse($recapData as $userData)
            {{-- Baris Nama Karyawan --}}
            <tr>
                <td colspan="6" style="font-weight: bold; background-color: #f2f2f2;">{{ $userData['user']->name }}
                    ({{ $userData['user']->jabatan }} - {{ $userData['user']->vendor->name ?? 'Internal' }})</td>
            </tr>
            {{-- Header Detail Lembur --}}
            <tr>
                <td style="font-weight: bold; border: 1px solid #000; text-align: center;">Tanggal</td>
                <td style="font-weight: bold; border: 1px solid #000; text-align: center;">Jam Mulai</td>
                <td style="font-weight: bold; border: 1px solid #000; text-align: center;">Jam Selesai</td>
                <td style="font-weight: bold; border: 1px solid #000; text-align: center;">Durasi (Menit)</td>
                {{-- Tampilkan menit untuk mudah dijumlah --}}
                <td style="font-weight: bold; border: 1px solid #000; text-align: center;" colspan="2">Uraian
                    Pekerjaan</td> {{-- Colspan 2 --}}
            </tr>
            {{-- Loop Detail Lembur per User --}}
            @forelse($userData['details']->sortBy('tanggal_lembur') as $detail)
                <tr>
                    <td style="border: 1px solid #000; text-align: center;">
                        {{ $detail->tanggal_lembur->format('d/m/Y') }}</td>
                    <td style="border: 1px solid #000; text-align: center;">{{ $detail->jam_mulai->format('H:i') }}</td>
                    <td style="border: 1px solid #000; text-align: center;">{{ $detail->jam_selesai->format('H:i') }}
                    </td>
                    <td style="border: 1px solid #000; text-align: right;">{{ $detail->durasi_menit ?? 0 }}</td>
                    {{-- Tampilkan menit --}}
                    <td style="border: 1px solid #000;" colspan="2">{{ $detail->uraian_pekerjaan }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="border: 1px solid #000; font-style: italic;">Tidak ada detail lembur pada
                        periode vendor yang relevan.</td>
                </tr>
            @endforelse
            {{-- Baris Total per Periode Vendor --}}
            @foreach ($userData['periods'] as $periodInfo)
                <tr>
                    <td colspan="3" style="font-weight: bold; text-align: right; border-top: 1px solid #000;">Total
                        Periode ({{ $periodInfo['start'] }} - {{ $periodInfo['end'] }}):</td>
                    <td style="font-weight: bold; text-align: right; border-top: 1px solid #000;">
                        {{ $periodInfo['total_minutes'] }}</td> {{-- Total Menit --}}
                    <td style="border-top: 1px solid #000;" colspan="2">
                        ({{ floor($periodInfo['total_minutes'] / 60) }} jam {{ $periodInfo['total_minutes'] % 60 }}
                        menit)
                        @if ($periodInfo['total_minutes'] > 3240)
                            <span style="color: red; font-weight:bold;">(MELEBIHI BATAS)</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            {{-- Baris Kosong Pemisah Antar User --}}
            <tr>
                <td colspan="6" height="15"></td>
            </tr>
        @empty
            {{-- Pesan jika tidak ada data sama sekali --}}
            <tr>
                <td colspan="6">Tidak ada data lembur ditemukan sesuai filter.</td>
            </tr>
        @endforelse
    </tbody>
</table>
