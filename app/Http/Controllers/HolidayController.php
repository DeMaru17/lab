<?php

namespace App\Http\Controllers;

use App\Models\Holiday; // Model untuk data hari libur
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;     // Untuk logging error dan informasi
use RealRashid\SweetAlert\Facades\Alert; // Untuk menampilkan notifikasi SweetAlert
use Carbon\Carbon; // Untuk manipulasi tanggal dan waktu
use Illuminate\Support\Facades\Auth; // Ditambahkan untuk potensi otorisasi
use Illuminate\Support\Facades\DB;   // Ditambahkan untuk konsistensi jika ada transaksi DB

/**
 * Class HolidayController
 *
 * Mengelola semua operasi CRUD (Create, Read, Update, Delete) yang berkaitan
 * dengan data hari libur nasional atau perusahaan.
 * Akses ke controller ini biasanya dibatasi untuk Admin.
 *
 * @package App\Http\Controllers
 */
class HolidayController extends Controller
{
    /**
     * Menampilkan daftar semua hari libur.
     * Data hari libur dapat difilter berdasarkan tahun dan diurutkan berdasarkan tanggal.
     * Data dipaginasi untuk tampilan yang lebih baik.
     *
     * @param  \Illuminate\Http\Request  $request Data request yang mungkin berisi parameter filter tahun.
     * @return \Illuminate\View\View Mengembalikan view 'holidays.index' dengan data hari libur.
     */
    public function index(Request $request)
    {
        // Otorisasi: Biasanya hanya Admin yang boleh melihat dan mengelola hari libur.
        // Anda bisa menambahkan middleware 'role:admin' pada route atau menggunakan Policy.
        // Contoh dengan Policy (jika ada HolidayPolicy@viewAny):
        // $this->authorize('viewAny', Holiday::class);

        // Mengambil filter tahun dari request, default ke tahun saat ini jika tidak ada input
        $selectedYear = $request->input('year', Carbon::now(config('app.timezone', 'Asia/Jakarta'))->year);

        // Mengambil data hari libur dari database berdasarkan tahun yang dipilih
        $holidays = Holiday::whereYear('tanggal', $selectedYear)
            ->orderBy('tanggal', 'asc') // Urutkan berdasarkan tanggal secara ascending
            ->paginate(20); // Gunakan pagination, 20 item per halaman

        // Mengirim data ke view 'holidays.index'
        return view('holidays.index', compact('holidays', 'selectedYear'));
    }

    /**
     * Menampilkan form untuk membuat data hari libur baru.
     *
     * @return \Illuminate\View\View Mengembalikan view 'holidays.create'.
     */
    public function create()
    {
        // Otorisasi: Hanya Admin yang boleh membuat hari libur baru.
        // $this->authorize('create', Holiday::class);

        return view('holidays.create');
    }

    /**
     * Menyimpan data hari libur baru ke database setelah validasi.
     * Tanggal hari libur harus unik.
     *
     * @param  \Illuminate\Http\Request  $request Data dari form pembuatan hari libur.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke halaman daftar hari libur dengan pesan status.
     */
    public function store(Request $request)
    {
        // Otorisasi:
        // $this->authorize('create', Holiday::class);

        // Validasi data input dari form
        $validatedData = $request->validate([
            'tanggal' => 'required|date_format:Y-m-d|unique:holidays,tanggal', // Tanggal wajib, format YYYY-MM-DD, dan unik di tabel holidays
            'nama_libur' => 'required|string|max:255', // Nama hari libur wajib, maks 255 karakter
        ], [
            // Pesan error kustom untuk validasi
            'tanggal.unique' => 'Tanggal libur ini sudah terdaftar sebelumnya.',
            'tanggal.date_format' => 'Format tanggal yang dimasukkan harus YYYY-MM-DD (contoh: 2025-12-31).',
        ]);

        DB::beginTransaction();
        try {
            Holiday::create($validatedData); // Membuat record baru di tabel 'holidays'
            DB::commit();

            Alert::success('Sukses Ditambahkan', 'Hari libur baru berhasil ditambahkan.');
            // Mengarahkan kembali ke halaman daftar hari libur, dengan filter tahun dari tanggal yang baru ditambahkan
            return redirect()->route('holidays.index', ['year' => Carbon::parse($validatedData['tanggal'])->year]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat membuat hari libur baru ({$validatedData['tanggal']} - {$validatedData['nama_libur']}): " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Menyimpan', 'Terjadi kesalahan saat menyimpan data hari libur. Silakan coba lagi.');
            return redirect()->back()->withInput(); // Kembali ke form dengan input sebelumnya
        }
    }

    /**
     * Display the specified resource. (Tidak digunakan sesuai kode, langsung ke edit)
     * Jika akan digunakan, pastikan ada view 'holidays.show'.
     *
     * @param  \App\Models\Holiday  $holiday
     * @return void
     */
    // public function show(Holiday $holiday)
    // {
    //     // $this->authorize('view', $holiday);
    // }

    /**
     * Menampilkan form untuk mengedit data hari libur yang sudah ada.
     * Menggunakan Route Model Binding untuk mengambil instance Holiday.
     *
     * @param  \App\Models\Holiday  $holiday Instance Holiday yang akan diedit.
     * @return \Illuminate\View\View Mengembalikan view 'holidays.edit' dengan data hari libur.
     */
    public function edit(Holiday $holiday) // Laravel otomatis melakukan findOrFail($id)
    {
        // Otorisasi:
        // $this->authorize('update', $holiday);

        // Mengirim data holiday yang akan diedit ke view 'holidays.edit'
        return view('holidays.edit', compact('holiday'));
    }

    /**
     * Memperbarui data hari libur yang sudah ada di database.
     * Tanggal hari libur harus unik (kecuali jika tidak diubah).
     *
     * @param  \Illuminate\Http\Request  $request Data dari form edit hari libur.
     * @param  \App\Models\Holiday  $holiday Instance Holiday yang akan diupdate.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali dengan pesan status.
     */
    public function update(Request $request, Holiday $holiday)
    {
        // Otorisasi:
        // $this->authorize('update', $holiday);

        // Validasi data input
        $validatedData = $request->validate([
            // Tanggal wajib, format YYYY-MM-DD.
            // Unik di tabel holidays, kecuali untuk tanggal libur saat ini (jika tidak diubah).
            // Format validasi unik: unique:table,column_to_check_for_uniqueness,id_to_ignore,column_name_of_id_to_ignore
            // Di sini kita menggunakan tanggal sebagai ID uniknya, jadi kita perlu mengabaikan tanggal itu sendiri jika tidak berubah.
            // Cara yang lebih tepat adalah menggunakan Rule::unique()->ignore($holiday->id) jika ID adalah primary key.
            // Namun, karena 'tanggal' yang unik, kita perlu memastikan tanggal baru tidak sama dengan tanggal lain,
            // kecuali tanggal itu sendiri jika tidak diubah.
            // Jika 'tanggal' adalah primary key, maka 'unique:holidays,tanggal,' . $holiday->tanggal . ',tanggal' akan salah.
            // Seharusnya: 'unique:holidays,tanggal,' . $holiday->id // jika 'id' adalah primary key
            // Jika 'tanggal' adalah primary key dan ingin diubah, validasinya menjadi lebih kompleks.
            // Asumsi 'tanggal' adalah kolom yang bisa diubah dan harus unik.
            'tanggal' => [
                'required',
                'date_format:Y-m-d',
                // Gunakan Rule::unique untuk mengabaikan record saat ini berdasarkan primary key 'tanggal'
                \Illuminate\Validation\Rule::unique('holidays', 'tanggal')->ignore($holiday->getKey(), $holiday->getKeyName()),
            ],
            'nama_libur' => 'required|string|max:255',
        ], [
            'tanggal.unique' => 'Tanggal libur ini sudah terdaftar untuk entri lain.',
            'tanggal.date_format' => 'Format tanggal yang dimasukkan harus YYYY-MM-DD.',
        ]);

        DB::beginTransaction();
        try {
            $holiday->update($validatedData); // Melakukan update pada record hari libur
            DB::commit();

            Alert::success('Sukses Diperbarui', 'Data hari libur berhasil diperbarui.');
            // Mengarahkan kembali ke halaman daftar hari libur, dengan filter tahun dari tanggal yang baru diupdate
            return redirect()->route('holidays.index', ['year' => Carbon::parse($validatedData['tanggal'])->year]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat memperbarui Holiday tanggal " . Carbon::parse($holiday->tanggal)->format('Y-m-d') . ": " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Update', 'Terjadi kesalahan saat memperbarui data hari libur. Silakan coba lagi.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Menghapus data hari libur dari database.
     *
     * @param  \App\Models\Holiday  $holiday Instance Holiday yang akan dihapus.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke halaman daftar hari libur dengan pesan status.
     */
    public function destroy(Holiday $holiday)
    {
        // Otorisasi:
        // $this->authorize('delete', $holiday);
        DB::beginTransaction();
        try {
            $holidayDate = Carbon::parse($holiday->tanggal)->format('Y-m-d'); // Simpan tanggal untuk redirect dan pesan
            $holidayName = $holiday->nama_libur; // Simpan nama untuk pesan notifikasi

            $holiday->delete(); // Menghapus record dari database
            DB::commit();

            Alert::success('Sukses Dihapus', "Hari libur '{$holidayName}' ({$holidayDate}) berhasil dihapus.");
            // Mengarahkan kembali ke halaman daftar hari libur, dengan filter tahun dari tanggal yang dihapus
            return redirect()->route('holidays.index', ['year' => Carbon::parse($holidayDate)->year]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat menghapus Holiday tanggal " . Carbon::parse($holiday->tanggal)->format('Y-m-d') . ": " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Menghapus', 'Terjadi kesalahan saat menghapus hari libur. Silakan coba lagi.');
            // Redirect kembali ke halaman index (mungkin tanpa filter tahun jika tanggal tidak bisa di-parse lagi)
            return redirect()->route('holidays.index');
        }
    }
}
