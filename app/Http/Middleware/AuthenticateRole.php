<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

class AuthenticateRole
{
  public function handle(Request $request, Closure $next, $role): Response
  {

    if (!$request->user() || !$request->user()->tokenCan('role:' . $role)) {
      info(auth()->user()->id);
      return response()->json(['message' => 'Unauthorized'], 403);
    }

    return $next($request);
  }
}
