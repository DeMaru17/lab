<?php

namespace App\Http\Controllers;

use App\Models\PerjalananDinas;
use App\Models\User;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Auth;

class PerjalananDinasController extends Controller
{
    // Menampilkan daftar perjalanan dinas
    public function index()
    {
        $title = 'Hapus Pengguna';
        $text = "Kamu yakin ingin menghapus pengguna?";
        confirmDelete($title, $text);

        $user = Auth::user(); // Ambil pengguna yang sedang login

        if (!$user) {
            return redirect()->route('login')->with('error', 'Anda harus login untuk mengakses halaman ini.');
        }

        $role = $user->role; // Ambil role pengguna
        if ($role === 'personil') {
            // Jika yang login adalah personil, hanya tampilkan perjalanan dinas miliknya
            $perjalananDinas = PerjalananDinas::where('user_id', $user->id)->get();
        } elseif ($role === 'admin') {
            // Jika yang login adalah admin, tampilkan semua data perjalanan dinas
            $perjalananDinas = PerjalananDinas::with('user')->get();
        } else {
            // Jika yang login adalah manajemen, batasi akses
            return redirect()->back()->with('error', 'Anda tidak memiliki akses ke halaman ini.');
        }

        return view('perjalanan-dinas.index', compact('perjalananDinas')); // Kirim data ke view
    }

    // Menampilkan form untuk menambahkan perjalanan dinas baru
    public function create()
    {
        $user = Auth::user(); // Ambil pengguna yang sedang login

        if ($user->role === 'personil') {
            // Jika personil, hanya kirimkan data dirinya sendiri
            $users = [$user];
        } else {
            // Jika admin atau manajemen, kirimkan semua data pengguna
            $users = User::all();
        }

        return view('perjalanan-dinas.create', compact('users')); // Kirim data pengguna ke view
    }

    // Menyimpan perjalanan dinas baru ke database
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'tanggal_berangkat' => 'required|date',
            'perkiraan_tanggal_pulang' => 'required|date|after_or_equal:tanggal_berangkat',
            'jurusan' => 'required|string|max:255',
        ]);

        PerjalananDinas::create($request->all());
        Alert::success('Sukses', 'Perjalanan dinas berhasil ditambahkan');
        return redirect()->route('perjalanan-dinas.index')->with('success', 'Perjalanan dinas berhasil ditambahkan.');
    }

    // Menampilkan form untuk mengedit perjalanan dinas
    public function edit(PerjalananDinas $perjalanan_dina)
    {
        $users = User::all(); // Ambil semua data pengguna
        return view('perjalanan-dinas.edit', compact('perjalanan_dina', 'users')); // Kirim data ke view
    }

    // Memperbarui data perjalanan dinas di database
    public function update(Request $request, PerjalananDinas $perjalanan_dina)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'tanggal_berangkat' => 'required|date',
            'perkiraan_tanggal_pulang' => 'required|date|after_or_equal:tanggal_berangkat',
            'tanggal_pulang' => 'nullable|date|after_or_equal:tanggal_berangkat',
            'jurusan' => 'required|string|max:255',
        ]);

        // Update data perjalanan dinas
        $data = $request->all();

        // Jika tanggal_pulang diisi, ubah status menjadi "selesai"
        if (!empty($data['tanggal_pulang'])) {
            $data['status'] = 'selesai';
        }

        $perjalanan_dina->update($data);

        Alert::success('Sukses', 'Perjalanan dinas berhasil diperbarui');
        return redirect()->route('perjalanan-dinas.index')->with('success', 'Perjalanan dinas berhasil diperbarui.');
    }

    // Menghapus perjalanan dinas dari database
    public function destroy(string $id)
    {
        PerjalananDinas::destroy($id);
        Alert::success('Sukses', 'Perjalanan dinas berhasil dihapus');
        return redirect()->route('perjalanan-dinas.index');
    }
}
