<?php

namespace App\Http\Controllers;

use App\Models\CutiQuota;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CutiQuotaController extends Controller
{
    // Menampilkan kuota cuti
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'personil') {
            // Jika user adalah personil, hanya tampilkan kuota miliknya
            $cutiQuota = CutiQuota::with('jenisCuti')->where('user_id', $user->id)->get();
        } else {
            // Jika user adalah admin/manajemen, tampilkan form pencarian
            $cutiQuota = [];
            if ($request->filled('search')) {
                $cutiQuota = CutiQuota::with('jenisCuti', 'user')
                    ->whereHas('user', function ($query) use ($request) {
                        $query->where('name', 'like', '%' . $request->search . '%')
                            ->orWhere('email', 'like', '%' . $request->search . '%');
                    })
                    ->get();
            }
        }

        return view('cuti.quota.index', compact('cutiQuota'));
    }

    // Menambah atau mengurangi kuota cuti
    public function update(Request $request, $id)
    {
        $request->validate([
            'durasi_cuti' => 'required|integer|min:0',
        ]);

        $cutiQuota = CutiQuota::findOrFail($id);
        $cutiQuota->durasi_cuti = $request->durasi_cuti;
        $cutiQuota->save();

        return redirect()->route('cuti.quota.index')->with('success', 'Kuota cuti berhasil diperbarui.');
    }
}
