<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\BlokirWa;

class PastikanBukanNomorBlokir
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $no_telp = $request->phone;

        if (
            !BlokirWa::where('no_telp', $no_telp)->exists()
        ) {
            return $next($request);
        }
        echo 'Pesan anda tidak dapat diproses. Mohon dapat telpon ke 0215977529. Mohon maaf atas ketidak nyamanannya';
    
    }
}
