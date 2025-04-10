<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;

class LoginController extends Controller
{
    public function index()
    {
        return view('auth.login');
    }

    public function actionLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        $credential = $request->only(['email', 'password']);
        if (Auth::attempt($credential)) {
            toast('Berhasil Masuk', 'success');
            return redirect()->intended('index');
        }
        Alert::error('Gagal Masuk', 'Periksa kembali isian Anda');
        return redirect()->back()->withErrors(['Login gagal. Mohon periksa kembali email dan password anda!']);
    }

    public  function logout(Request $request)
    {
        Auth::logout();
        return redirect()->route('dashboard');
    }
}
