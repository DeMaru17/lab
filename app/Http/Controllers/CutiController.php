<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cuti;
use App\Models\CutiQuota;
use App\Models\JenisCuti;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CutiController extends Controller
{
    public function index()
    {
        // Periksa role pengguna
        if (Auth::user()->role === 'admin' || Auth::user()->role === 'manajemen') {
            // Admin dan manajemen dapat melihat semua pengajuan cuti
            $cuti = Cuti::with('user', 'jenisCuti')->orderBy('created_at', 'desc')->get();
        } else {
            // Pengguna biasa hanya dapat melihat pengajuan cuti mereka sendiri
            $cuti = Cuti::where('user_id', Auth::id())->with('jenisCuti')->orderBy('created_at', 'desc')->get();
        }

        // Ambil kuota cuti untuk setiap jenis cuti
        $cutiQuota = CutiQuota::where('user_id', Auth::id())->get()->keyBy('jenis_cuti_id');

        return view('cuti.index', compact('cuti', 'cutiQuota'));
    }
    // Menampilkan form pengajuan cuti
    public function create()
    {
        $jenisCuti = JenisCuti::all();
        return view('cuti.create', compact('jenisCuti'));
    }

    // Menyimpan pengajuan cuti
    public function store(Request $request)
    {
        $request->validate([
            'jenis_cuti_id' => 'required|exists:jenis_cuti,id',
            'mulai_cuti' => 'required|date',
            'selesai_cuti' => 'required|date|after_or_equal:mulai_cuti',
            'keperluan' => 'required|string',
            'alamat_selama_cuti' => 'required|string',
            'surat_sakit' => 'nullable|file|mimes:pdf,jpg,png|max:2048',
        ]);

        $lamaCuti = Carbon::parse($request->mulai_cuti)->diffInDays(Carbon::parse($request->selesai_cuti)) + 1;

        // Validasi kuota cuti
        $cutiQuota = CutiQuota::where('user_id', Auth::id())
            ->where('jenis_cuti_id', $request->jenis_cuti_id)
            ->first();

        if ($cutiQuota && $cutiQuota->durasi_cuti < $lamaCuti) {
            return back()->withErrors(['error' => 'Kuota cuti tidak mencukupi.']);
        }

        // Simpan pengajuan cuti
        $cuti = new Cuti();
        $cuti->user_id = Auth::id();
        $cuti->jenis_cuti_id = $request->jenis_cuti_id;
        $cuti->mulai_cuti = $request->mulai_cuti;
        $cuti->selesai_cuti = $request->selesai_cuti;
        $cuti->lama_cuti = $lamaCuti;
        $cuti->keperluan = $request->keperluan;
        $cuti->alamat_selama_cuti = $request->alamat_selama_cuti;

        if ($request->hasFile('surat_sakit')) {
            $cuti->surat_sakit = $request->file('surat_sakit')->store('surat_sakit', 'public');
        }

        $cuti->save();

        return redirect()->route('cuti.index')->with('success', 'Pengajuan cuti berhasil diajukan.');
    }

    public function getQuota(Request $request)
    {
        $jenisCutiId = $request->jenis_cuti_id;

        // Ambil kuota cuti berdasarkan user dan jenis cuti
        $cutiQuota = CutiQuota::where('user_id', Auth::id())
            ->where('jenis_cuti_id', $jenisCutiId)
            ->first();

        return response()->json([
            'durasi_cuti' => $cutiQuota ? $cutiQuota->durasi_cuti : 0,
        ]);
    }
}
