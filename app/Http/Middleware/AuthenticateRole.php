<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateRole
{
  public function handle(Request $request, Closure $next, $role): Response
  {
    if (!$request->user() || !$request->user()->tokenCan('role:' . $role)) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    return $next($request);
  }
}