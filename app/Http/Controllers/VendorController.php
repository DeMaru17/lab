<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // Untuk hapus file
use Illuminate\Support\Facades\Log;     // Untuk logging error
use RealRashid\SweetAlert\Facades\Alert; // Untuk notifikasi
use Illuminate\Support\Facades\DB; // Untuk transaksi DB

class VendorController extends Controller
{
    /**
     * Menampilkan daftar vendor.
     */
    public function index()
    {
        // Ambil semua vendor, urutkan berdasarkan nama, gunakan pagination
        $vendors = Vendor::orderBy('name', 'asc')->paginate(15); // Sesuaikan jumlah per halaman
        return view('vendors.index', compact('vendors'));
    }

    /**
     * Menampilkan form untuk membuat vendor baru.
     */
    public function create()
    {
        return view('vendors.create');
    }

    /**
     * Menyimpan vendor baru ke database.
     */
    public function store(Request $request)
    {
        // Validasi input
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:vendors,name',
            'logo_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024' // Maks 1MB, opsional
        ]);

        $logoPath = null;
        if ($request->hasFile('logo_image')) {
            try {
                // Simpan logo ke storage/app/public/vendor-logos
                $logoPath = $request->file('logo_image')->store('vendor-logos', 'public');
            } catch (\Exception $e) {
                Log::error("Vendor logo upload failed: " . $e->getMessage());
                Alert::error('Gagal Upload', 'Gagal mengunggah file logo vendor.');
                return redirect()->back()->withInput();
            }
        }

        // Buat vendor baru
        try {
            Vendor::create([
                'name' => $validatedData['name'],
                'logo_path' => $logoPath,
            ]);
            Alert::success('Sukses', 'Vendor baru berhasil ditambahkan.');
            return redirect()->route('vendors.index');
        } catch (\Exception $e) {
            Log::error("Error creating vendor: " . $e->getMessage());
            // Hapus logo yg sudah terupload jika pembuatan DB gagal
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            Alert::error('Gagal', 'Terjadi kesalahan saat menyimpan data vendor.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Menampilkan detail vendor (Opsional, jika diperlukan).
     */
    public function show(Vendor $vendor)
    {
        // return view('vendors.show', compact('vendor'));
        return redirect()->route('vendors.edit', $vendor); // Atau langsung ke edit saja
    }

    /**
     * Menampilkan form untuk mengedit vendor.
     */
    public function edit(Vendor $vendor) // Otomatis find vendor by ID
    {
        return view('vendors.edit', compact('vendor'));
    }

    /**
     * Memperbarui data vendor di database.
     */
    public function update(Request $request, Vendor $vendor)
    {
        // Validasi input
        $validatedData = $request->validate([
            // Pastikan unik tapi abaikan ID vendor saat ini
            'name' => 'required|string|max:255|unique:vendors,name,' . $vendor->id,
            'logo_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024' // Maks 1MB, opsional
        ]);

        $updateData = ['name' => $validatedData['name']];
        $oldLogoPath = $vendor->logo_path; // Simpan path lama

        if ($request->hasFile('logo_image')) {
            try {
                // Simpan logo baru
                $logoPath = $request->file('logo_image')->store('vendor-logos', 'public');
                $updateData['logo_path'] = $logoPath; // Siapkan path baru untuk disimpan

                // Hapus logo lama JIKA upload baru berhasil
                if ($oldLogoPath) {
                    Storage::disk('public')->delete($oldLogoPath);
                }
            } catch (\Exception $e) {
                Log::error("Vendor logo update failed for vendor {$vendor->id}: " . $e->getMessage());
                Alert::error('Gagal Upload', 'Gagal mengunggah file logo vendor baru.');
                return redirect()->back()->withInput();
            }
        }

        // Update data vendor
        try {
            $vendor->update($updateData);
            Alert::success('Sukses', 'Data vendor berhasil diperbarui.');
            return redirect()->route('vendors.index');
        } catch (\Exception $e) {
            Log::error("Error updating vendor {$vendor->id}: " . $e->getMessage());
            // Jika update DB gagal tapi logo baru sudah terupload, hapus logo baru itu
            if (isset($updateData['logo_path']) && $updateData['logo_path'] !== $oldLogoPath) {
                Storage::disk('public')->delete($updateData['logo_path']);
            }
            Alert::error('Gagal', 'Terjadi kesalahan saat memperbarui data vendor.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Menghapus vendor dari database.
     */
    public function destroy(Vendor $vendor)
    {
        // Cek apakah ada user yang masih terhubung ke vendor ini
        if ($vendor->users()->exists()) {
            Alert::error('Gagal', 'Vendor tidak dapat dihapus karena masih terhubung dengan data pengguna.');
            return redirect()->route('vendors.index');
        }

        // Hapus jika tidak ada user terhubung
        DB::beginTransaction();
        try {
            $logoPath = $vendor->logo_path;
            $vendorName = $vendor->name; // Simpan nama untuk pesan

            $vendor->delete(); // Hapus data vendor dari DB

            // Hapus file logo dari storage jika ada
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            DB::commit();
            Alert::success('Sukses', 'Vendor "' . $vendorName . '" berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error deleting vendor {$vendor->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Terjadi kesalahan saat menghapus vendor.');
        }

        return redirect()->route('vendors.index');
    }
}
