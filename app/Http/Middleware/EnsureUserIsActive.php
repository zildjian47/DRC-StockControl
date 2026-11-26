<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->isActive()) {
            $message = $user->isPending()
                ? 'Akun Anda masih menunggu persetujuan (ACC) dari PPIC.'
                : 'Akun Anda telah dinonaktifkan oleh PPIC. Silakan hubungi PPIC untuk informasi lebih lanjut.';

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'ok'      => false,
                    'message' => $message,
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => $message,
            ]);
        }

        return $next($request);
    }
}