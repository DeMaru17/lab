<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RealRashid\SweetAlert\Facades\Alert;
use Carbon\Carbon; // Import Carbon

class HolidayController extends Controller
{
    /**
     * Display a listing of the resource.
     * Menampilkan daftar hari libur dengan filter tahun.
     */
    public function index(Request $request)
    {
        // Ambil filter tahun, default ke tahun saat ini
        $selectedYear = $request->input('year', Carbon::now()->year);

        // Ambil data libur berdasarkan tahun yang dipilih
        $holidays = Holiday::whereYear('tanggal', $selectedYear)
                           ->orderBy('tanggal', 'asc') // Urutkan berdasarkan tanggal
                           ->paginate(20); // Gunakan pagination

        // Kirim data ke view
        return view('holidays.index', compact('holidays', 'selectedYear'));
    }

    /**
     * Show the form for creating a new resource.
     * Menampilkan form tambah hari libur.
     */
    public function create()
    {
        return view('holidays.create');
    }

    /**
     * Store a newly created resource in storage.
     * Menyimpan hari libur baru.
     */
    public function store(Request $request)
    {
        // Validasi input
        $validatedData = $request->validate([
            'tanggal' => 'required|date_format:Y-m-d|unique:holidays,tanggal', // Tanggal harus unik
            'nama_libur' => 'required|string|max:255',
        ], [
            'tanggal.unique' => 'Tanggal libur ini sudah terdaftar.',
            'tanggal.date_format' => 'Format tanggal harus YYYY-MM-DD.',
        ]);

        try {
            Holiday::create($validatedData);
            Alert::success('Sukses', 'Hari libur berhasil ditambahkan.');
            // Redirect ke index dengan filter tahun dari tanggal yg baru ditambahkan
            return redirect()->route('holidays.index', ['year' => Carbon::parse($validatedData['tanggal'])->year]);
        } catch (\Exception $e) {
            Log::error("Error creating holiday: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan saat menyimpan hari libur.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified resource. (Tidak digunakan, langsung ke edit)
     */
    // public function show(Holiday $holiday)
    // {
    //     //
    // }

    /**
     * Show the form for editing the specified resource.
     * Menampilkan form edit hari libur.
     */
    public function edit(Holiday $holiday) // Menggunakan Route Model Binding
    {
        // Kirim data holiday ke view edit
        return view('holidays.edit', compact('holiday'));
    }

    /**
     * Update the specified resource in storage.
     * Memperbarui data hari libur.
     */
    public function update(Request $request, Holiday $holiday)
    {
         // Validasi input
         $validatedData = $request->validate([
            // Tanggal boleh sama dengan yg lama, tapi unik jika diubah
            'tanggal' => 'required|date_format:Y-m-d|unique:holidays,tanggal,' . $holiday->tanggal . ',tanggal',
            'nama_libur' => 'required|string|max:255',
        ],[
            'tanggal.unique' => 'Tanggal libur ini sudah terdaftar.',
            'tanggal.date_format' => 'Format tanggal harus YYYY-MM-DD.',
        ]);

        try {
            $holiday->update($validatedData);
            Alert::success('Sukses', 'Hari libur berhasil diperbarui.');
             // Redirect ke index dengan filter tahun dari tanggal yg baru diupdate
            return redirect()->route('holidays.index', ['year' => Carbon::parse($validatedData['tanggal'])->year]);
        } catch (\Exception $e) {
            Log::error("Error updating holiday {$holiday->tanggal}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan saat memperbarui hari libur.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     * Menghapus data hari libur.
     */
    public function destroy(Holiday $holiday)
    {
        try {
            $holidayDate = $holiday->tanggal->format('Y-m-d'); // Simpan tanggal untuk redirect
            $holidayName = $holiday->nama_libur; // Simpan nama untuk pesan
            $holiday->delete();
            Alert::success('Sukses', "Hari libur '{$holidayName}' ({$holidayDate}) berhasil dihapus.");
             // Redirect ke index dengan filter tahun dari tanggal yg dihapus
            return redirect()->route('holidays.index', ['year' => Carbon::parse($holidayDate)->year]);
        } catch (\Exception $e) {
             Log::error("Error deleting holiday {$holiday->tanggal}: " . $e->getMessage());
             Alert::error('Gagal', 'Terjadi kesalahan saat menghapus hari libur.');
             return redirect()->route('holidays.index');
        }
    }
}
