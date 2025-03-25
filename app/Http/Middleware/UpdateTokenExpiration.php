<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateTokenExpiration
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (auth('sanctum')->check()) {
            $user = auth('sanctum')->user();
            $token = $user->currentAccessToken();

            // Perbarui waktu kadaluarsa token setiap kali digunakan
            if ($token) {
                $token->forceFill(['expires_at' => Carbon::now()->addDays(1)])->save();
            }
        }

        return $response;
    }
}
