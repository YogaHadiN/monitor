<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Input;
use Log;
use App\Models\Tenant;
use App\Models\WebRegistration;

class returnErrorIfAntrianDokterGigiNonAktif
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
        $no_telp = Input::get('no_telp');
        $web_registration = WebRegistration::whereDate('created_at', date('Y-m-d'))
                                            ->where('no_telp', $no_telp)
                                            ->first();
        $tenant = Tenant::find( session()->get('tenant_id') );
        if (
            !is_null( $web_registration ) &&
            $web_registration->tipe_konsultasi_id == 2 &&
            !$tenant->dentist_queue_enabled
        ) {
            $web_registration->tipe_konsultasi_id = null;
            $web_registration->save();
            return false;
        } else {
            return $next($request);
        }
    }
}
