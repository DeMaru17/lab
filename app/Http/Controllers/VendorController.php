<?php

namespace App\Http\Controllers;

use App\Models\Vendor; // Model untuk data vendor
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // Untuk operasi file (hapus logo)
use Illuminate\Support\Facades\Log;     // Untuk logging error dan informasi
use RealRashid\SweetAlert\Facades\Alert; // Untuk menampilkan notifikasi SweetAlert
use Illuminate\Support\Facades\DB; // Untuk transaksi database
// use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Jika Anda akan menggunakan Policy untuk Vendor

/**
 * Class VendorController
 *
 * Mengelola semua operasi CRUD (Create, Read, Update, Delete) yang berkaitan
 * dengan data vendor. Termasuk pengelolaan unggah, pembaruan, dan penghapusan
 * file logo vendor. Akses ke controller ini biasanya dibatasi untuk Admin.
 *
 * @package App\Http\Controllers
 */
class VendorController extends Controller
{
    // use AuthorizesRequests; // Aktifkan jika Anda membuat dan mendaftarkan VendorPolicy

    /**
     * Menampilkan daftar semua vendor.
     * Data vendor diurutkan berdasarkan nama dan dipaginasi.
     *
     * @return \Illuminate\View\View Mengembalikan view 'vendors.index' dengan data vendor.
     */
    public function index()
    {
        // Otorisasi: Pastikan hanya pengguna yang berhak (misal: Admin) yang bisa mengakses.
        // Anda bisa menggunakan middleware 'role:admin' pada route atau Policy.
        // Contoh dengan Policy (jika ada VendorPolicy@viewAny):
        // $this->authorize('viewAny', Vendor::class);

        // Mengambil semua data vendor, diurutkan berdasarkan nama secara ascending (A-Z)
        // dan menggunakan pagination untuk menampilkan 15 item per halaman.
        $vendors = Vendor::orderBy('name', 'asc')->paginate(15); // Sesuaikan jumlah item per halaman jika perlu
        return view('vendors.index', compact('vendors'));
    }

    /**
     * Menampilkan form untuk membuat data vendor baru.
     *
     * @return \Illuminate\View\View Mengembalikan view 'vendors.create'.
     */
    public function create()
    {
        // Otorisasi: Pastikan hanya pengguna yang berhak (misal: Admin) yang bisa membuat vendor.
        // Contoh dengan Policy (jika ada VendorPolicy@create):
        // $this->authorize('create', Vendor::class);

        return view('vendors.create');
    }

    /**
     * Menyimpan data vendor baru ke database setelah validasi.
     * Jika ada file logo yang diunggah, file tersebut akan disimpan ke storage.
     *
     * @param  \Illuminate\Http\Request  $request Data dari form pembuatan vendor.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke halaman daftar vendor dengan pesan status.
     */
    public function store(Request $request)
    {
        // Otorisasi:
        // $this->authorize('create', Vendor::class);

        // Validasi data input dari form
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:vendors,name', // Nama vendor wajib, unik di tabel vendors, maks 255 karakter
            'logo_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024' // Logo opsional, harus gambar, tipe png/jpg/jpeg, maks 1MB
        ]);

        $logoPath = null; // Path default untuk logo adalah null
        // Jika ada file logo yang diunggah di request
        if ($request->hasFile('logo_image')) {
            try {
                // Simpan file logo ke direktori 'vendor-logos' di dalam disk 'public'
                // Path yang disimpan adalah relatif terhadap 'storage/app/public/'
                $logoPath = $request->file('logo_image')->store('vendor-logos', 'public');
            } catch (\Exception $e) {
                Log::error("Gagal mengunggah logo vendor saat store: " . $e->getMessage());
                Alert::error('Gagal Upload Logo', 'Terjadi kesalahan saat mengunggah file logo vendor.');
                return redirect()->back()->withInput(); // Kembali ke form dengan input sebelumnya
            }
        }

        DB::beginTransaction(); // Memulai transaksi database
        try {
            // Membuat record vendor baru di database
            Vendor::create([
                'name' => $validatedData['name'],
                'logo_path' => $logoPath, // Simpan path logo (bisa null jika tidak ada)
            ]);
            DB::commit(); // Simpan perubahan jika berhasil

            Alert::success('Sukses Ditambahkan', 'Vendor baru berhasil ditambahkan ke sistem.');
            return redirect()->route('vendors.index'); // Mengarahkan ke halaman daftar vendor
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan transaksi jika terjadi error
            Log::error("Error saat membuat vendor baru: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            // Jika pembuatan record di DB gagal, hapus file logo yang mungkin sudah terlanjur diunggah
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
                Log::info("Logo vendor '{$logoPath}' yang diunggah telah dihapus karena gagal menyimpan data vendor.");
            }
            Alert::error('Gagal Menyimpan', 'Terjadi kesalahan saat menyimpan data vendor. Silakan coba lagi.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Menampilkan detail spesifik vendor.
     * (Saat ini method ini mengarahkan langsung ke halaman edit,
     * bisa diubah untuk menampilkan halaman detail jika diperlukan).
     *
     * @param  \App\Models\Vendor  $vendor Instance Vendor yang akan ditampilkan (via Route Model Binding).
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View Mengarahkan ke halaman edit atau menampilkan view detail.
     */
    public function show(Vendor $vendor)
    {
        // Otorisasi:
        // $this->authorize('view', $vendor);

        // Logika saat ini langsung mengarahkan ke halaman edit.
        // Jika Anda ingin halaman show terpisah, uncomment baris di bawah dan buat view-nya.
        // return view('vendors.show', compact('vendor'));
        return redirect()->route('vendors.edit', $vendor->id); // Mengarahkan ke form edit untuk vendor ini
    }

    /**
     * Menampilkan form untuk mengedit data vendor yang sudah ada.
     *
     * @param  \App\Models\Vendor  $vendor Instance Vendor yang akan diedit.
     * @return \Illuminate\View\View Mengembalikan view 'vendors.edit' dengan data vendor.
     */
    public function edit(Vendor $vendor) // Laravel otomatis melakukan findOrFail($id) untuk Route Model Binding
    {
        // Otorisasi:
        // $this->authorize('update', $vendor);

        return view('vendors.edit', compact('vendor'));
    }

    /**
     * Memperbarui data vendor yang sudah ada di database.
     * Jika ada file logo baru yang diunggah, file logo lama akan dihapus.
     *
     * @param  \Illuminate\Http\Request  $request Data dari form edit vendor.
     * @param  \App\Models\Vendor  $vendor Instance Vendor yang akan diupdate.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke halaman daftar vendor dengan pesan status.
     */
    public function update(Request $request, Vendor $vendor)
    {
        // Otorisasi:
        // $this->authorize('update', $vendor);

        // Validasi data input
        $validatedData = $request->validate([
            // Nama vendor wajib, unik (kecuali untuk ID vendor saat ini), maks 255 karakter
            'name' => 'required|string|max:255|unique:vendors,name,' . $vendor->id,
            'logo_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024' // Logo opsional
        ]);

        $updateData = ['name' => $validatedData['name']]; // Data awal untuk diupdate
        $oldLogoPath = $vendor->logo_path; // Simpan path logo lama untuk potensi penghapusan

        // Jika ada file logo baru yang diunggah
        if ($request->hasFile('logo_image')) {
            try {
                // Simpan file logo baru
                $newLogoPath = $request->file('logo_image')->store('vendor-logos', 'public');
                $updateData['logo_path'] = $newLogoPath; // Tambahkan path logo baru ke data update

            } catch (\Exception $e) {
                Log::error("Gagal mengunggah logo vendor baru untuk Vendor ID {$vendor->id}: " . $e->getMessage());
                Alert::error('Gagal Upload Logo', 'Gagal mengunggah file logo vendor baru.');
                return redirect()->back()->withInput();
            }
        } elseif ($request->input('remove_logo') == '1' && $oldLogoPath) {
            // Jika ada permintaan untuk menghapus logo yang ada (misalnya via checkbox 'remove_logo')
            // dan logo lama memang ada.
            $updateData['logo_path'] = null; // Set path logo menjadi null di database
        }


        DB::beginTransaction();
        try {
            // Lakukan update data vendor di database
            $vendor->update($updateData);

            // Jika logo baru diunggah dan berbeda dengan yang lama (atau yang lama ada dan yang baru di-set null),
            // hapus file logo lama dari storage.
            if (isset($newLogoPath) && $oldLogoPath && $newLogoPath !== $oldLogoPath) {
                Storage::disk('public')->delete($oldLogoPath);
                Log::info("Logo vendor lama '{$oldLogoPath}' untuk Vendor ID {$vendor->id} telah dihapus setelah update.");
            } elseif (isset($updateData['logo_path']) && $updateData['logo_path'] === null && $oldLogoPath) {
                // Jika logo di-set null (karena remove_logo) dan ada logo lama
                Storage::disk('public')->delete($oldLogoPath);
                Log::info("Logo vendor '{$oldLogoPath}' untuk Vendor ID {$vendor->id} telah dihapus berdasarkan permintaan.");
            }

            DB::commit();
            Alert::success('Sukses Diperbarui', 'Data vendor berhasil diperbarui.');
            return redirect()->route('vendors.index');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat memperbarui Vendor ID {$vendor->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            // Jika update database gagal TAPI logo baru sudah terlanjur diunggah, hapus logo baru tersebut.
            if (isset($newLogoPath) && Storage::disk('public')->exists($newLogoPath)) {
                Storage::disk('public')->delete($newLogoPath);
                Log::info("Logo vendor baru '{$newLogoPath}' yang diunggah telah dihapus karena gagal update data Vendor ID {$vendor->id}.");
            }
            Alert::error('Gagal Update', 'Terjadi kesalahan saat memperbarui data vendor. Silakan coba lagi.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Menghapus data vendor dari database.
     * Sebelum menghapus, akan dicek apakah vendor tersebut masih terhubung dengan data pengguna.
     * Jika masih terhubung, penghapusan akan dicegah.
     * File logo vendor juga akan dihapus dari storage jika ada.
     *
     * @param  \App\Models\Vendor  $vendor Instance Vendor yang akan dihapus.
     * @return \Illuminate\Http\RedirectResponse Mengarahkan kembali ke halaman daftar vendor dengan pesan status.
     */
    public function destroy(Vendor $vendor)
    {
        // Otorisasi:
        // $this->authorize('delete', $vendor);

        // Pengecekan apakah vendor masih digunakan oleh data pengguna (relasi 'users')
        // Ini mencegah error foreign key constraint dan menjaga integritas data.
        if ($vendor->users()->exists()) { // Asumsi ada relasi 'users' di model Vendor: public function users() { return $this->hasMany(User::class); }
            Alert::error('Gagal Dihapus', 'Vendor tidak dapat dihapus karena masih terhubung dengan satu atau lebih data pengguna.');
            return redirect()->route('vendors.index');
        }

        // Jika tidak ada pengguna yang terhubung, lanjutkan proses penghapusan
        DB::beginTransaction();
        try {
            $logoPath = $vendor->logo_path; // Simpan path logo untuk dihapus dari storage
            $vendorName = $vendor->name; // Simpan nama vendor untuk pesan notifikasi

            $vendor->delete(); // Hapus record vendor dari database

            // Hapus file logo fisik dari storage jika ada
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
                Log::info("Logo vendor '{$logoPath}' untuk vendor '{$vendorName}' (ID: {$vendor->id}) telah dihapus dari storage.");
            }

            DB::commit();
            Alert::success('Sukses Dihapus', 'Vendor "' . $vendorName . '" beserta logonya (jika ada) berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat menghapus Vendor ID {$vendor->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Alert::error('Gagal Menghapus', 'Terjadi kesalahan saat menghapus vendor. Silakan coba lagi.');
        }

        return redirect()->route('vendors.index');
    }
}
