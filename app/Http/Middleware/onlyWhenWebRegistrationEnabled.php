<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\Classes\Yoga;

class onlyWhenWebRegistrationEnabled
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
        $tenant_id = session()->get('tenant_id');
        $tenant = Tenant::find( $tenant_id );
        if (!$tenant->website_registration_enabled) {
            $pesan = Yoga::gagalFlash('Tidak dapat melakukan daftar online melalui website karena sedang di non aktifkan');
            return redirect('/');
        }
        return $next($request);
    }
}
