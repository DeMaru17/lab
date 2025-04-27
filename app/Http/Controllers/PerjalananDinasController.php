<?php

namespace App\Http\Controllers;

use App\Models\PerjalananDinas;
use App\Models\User; // Import User jika diperlukan untuk create/edit
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Import Auth
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // <-- 1. Import Trait
use Illuminate\Support\Facades\Log; // <-- 1. Import Log

class PerjalananDinasController extends Controller
{
    use AuthorizesRequests; // <-- 2. Gunakan Trait

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Policy 'viewAny' biasanya true, filter data di sini
        $user = Auth::user();
        $query = PerjalananDinas::with('user:id,name'); // Eager load user

        if ($user->role === 'personil') {
            $query->where('user_id', $user->id);
        }
        // Admin & Manajemen bisa lihat semua (tidak perlu filter tambahan)

        // Tambahkan search jika perlu
        $searchTerm = $request->input('search');
        if ($searchTerm && in_array($user->role, ['admin', 'manajemen'])) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('jurusan', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', '%' . $searchTerm . '%'));
                // Tambah kolom lain jika perlu
            });
        }

        $perjalananDinas = $query->orderBy('tanggal_berangkat', 'desc')->paginate(15);

        if ($searchTerm) {
            $perjalananDinas->appends(['search' => $searchTerm]);
        }

        return view('perjalanan-dinas.index', compact('perjalananDinas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Cek policy create
        $this->authorize('create', PerjalananDinas::class);

        // Ambil daftar user HANYA jika yang akses adalah admin
        $users = [];
        if (Auth::user()->role === 'admin') {
            $users = User::orderBy('name')->pluck('name', 'id');
        }

        return view('perjalanan-dinas.create', compact('users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Cek policy create
        $this->authorize('create', PerjalananDinas::class);

        // Validasi dasar
        $validatedData = $request->validate([
            // Jika admin, user_id wajib & harus ada. Jika personil, tidak perlu kirim.
            'user_id' => Auth::user()->role === 'admin' ? 'required|exists:users,id' : 'nullable',
            'tanggal_berangkat' => 'required|date',
            'perkiraan_tanggal_pulang' => 'required|date|after_or_equal:tanggal_berangkat',
            'jurusan' => 'required|string|max:255',
            // tanggal_pulang, lama_dinas, status, is_processed dihandle model/update
        ]);

        // Tentukan user_id
        $userId = Auth::user()->role === 'admin' ? $validatedData['user_id'] : Auth::id();

        // Siapkan data untuk disimpan
        $createData = [
            'user_id' => $userId,
            'tanggal_berangkat' => $validatedData['tanggal_berangkat'],
            'perkiraan_tanggal_pulang' => $validatedData['perkiraan_tanggal_pulang'],
            'jurusan' => $validatedData['jurusan'],
            'status' => 'berlangsung', // Status awal
            // lama_dinas akan dihitung oleh model event 'saving'
        ];

        try {
            PerjalananDinas::create($createData);
            Alert::success('Sukses', 'Data perjalanan dinas berhasil ditambahkan.');
            return redirect()->route('perjalanan-dinas.index');
        } catch (\Exception $e) {
            Log::error("Error creating Perjalanan Dinas: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal menyimpan data perjalanan dinas.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified resource. (Tidak digunakan sesuai info user)
     */
    // public function show(PerjalananDinas $perjalananDinas)
    // {
    //     $this->authorize('view', $perjalananDinas);
    //     // return view('perjalanan_dinas.show', compact('perjalananDinas'));
    // }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PerjalananDinas $perjalananDina) // <-- Ganti jadi $perjalananDina
    {
        $this->authorize('update', $perjalananDina);
        $users = [];
        if (Auth::user()->role === 'admin') {
            $users = User::orderBy('name')->pluck('name', 'id');
        }
        // Kirim dengan nama variabel yang sama
        return view('perjalanan-dinas.edit', compact('perjalananDina', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PerjalananDinas $perjalananDina)
    {
        // Cek policy update
        $this->authorize('update', $perjalananDina);

        // Validasi (sesuaikan field yg boleh diubah)
        // Contoh: hanya boleh ubah tanggal pulang, status, jurusan?
        $validatedData = $request->validate([
            // user_id sebaiknya tidak diubah saat edit
            'tanggal_berangkat' => 'required|date',
            'perkiraan_tanggal_pulang' => 'required|date|after_or_equal:tanggal_berangkat',
            'tanggal_pulang' => 'nullable|date|after_or_equal:tanggal_berangkat', // Boleh null
            'jurusan' => 'required|string|max:255',
            'status' => 'required|in:berlangsung,selesai', // Validasi status
            // is_processed tidak diinput user
        ]);

        // Siapkan data update
        $updateData = $validatedData;
        // Hapus user_id agar tidak terupdate
        // unset($updateData['user_id']); // Jika user_id tidak boleh diubah

        try {
            // Model event 'saving' akan otomatis menghitung ulang lama_dinas
            // Model event 'saved' akan otomatis cek & tambah kuota jika status 'selesai' & belum diproses
            $perjalananDina->update($updateData);

            Alert::success('Sukses', 'Data perjalanan dinas berhasil diperbarui.');
            return redirect()->route('perjalanan-dinas.index');
        } catch (\Exception $e) {
            Log::error("Error updating Perjalanan Dinas ID {$perjalananDina->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal memperbarui data perjalanan dinas.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PerjalananDinas $perjalananDinas)
    {
        // Cek policy delete
        $this->authorize('delete', $perjalananDinas);

        try {
            $perjalananDinas->delete();
            Alert::success('Sukses', 'Data perjalanan dinas berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error("Error deleting Perjalanan Dinas ID {$perjalananDinas->id}: " . $e->getMessage());
            Alert::error('Gagal', 'Gagal menghapus data perjalanan dinas.');
        }
        return redirect()->route('perjalanan-dinas.index');
    }
}
