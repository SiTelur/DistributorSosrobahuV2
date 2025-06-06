<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\UserPabrik;
use Carbon\Carbon;

class PabrikLoginAPIController extends Controller
{
    public function loginPabrik(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = UserPabrik::where('username', $request->username)->first();

        if ($user === null || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Username atau password salah.'], 401);
        }
        $expiration = config('sanctum.expiration');
        $token = $user->createToken('sosrobahu_token', ['role:pabrik'], now()->addMinutes($expiration));
        $accessToken = $token->accessToken;
        $accessToken->forceFill(['user_id' => $user->id_user_pabrik])->save();

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'user' => [
                'id' => $user->id_user_pabrik,
                'nama_lengkap' => $user->nama_lengkap,
                'role' => 'pabrik'
            ]
        ]);
    }
}
