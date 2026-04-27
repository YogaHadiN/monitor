<?php

namespace App\Http\Middleware;

use App\Models\AntrianOnlineApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogAntrianOnlineApi
{
    private const SENSITIVE_HEADERS = ['x-password', 'authorization', 'cookie'];

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        try {
            $this->writeLog($request, $response, (int) round((microtime(true) - $start) * 1000));
        } catch (\Throwable $e) {
            Log::warning('ANTRIAN_ONLINE_API_LOG_FAIL', ['err' => $e->getMessage()]);
        }

        return $response;
    }

    private function writeLog(Request $request, Response $response, int $durationMs): void
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $key = strtolower($name);
            if ( in_array($key, self::SENSITIVE_HEADERS, true) ) {
                $headers[$key] = '***redacted***';
                continue;
            }
            $headers[$key] = is_array($values) && count($values) === 1 ? $values[0] : $values;
        }

        $requestBody = $request->getContent();
        if ( strlen($requestBody) > 50000 ) {
            $requestBody = substr($requestBody, 0, 50000) . '... [truncated]';
        }

        $responseBody = '';
        try {
            $responseBody = (string) $response->getContent();
        } catch (\Throwable $e) {
            $responseBody = '[unreadable: ' . $e->getMessage() . ']';
        }
        if ( strlen($responseBody) > 50000 ) {
            $responseBody = substr($responseBody, 0, 50000) . '... [truncated]';
        }

        $route = $request->route();
        $routeName = null;
        $params = [];
        if ( $route ) {
            $routeName = $route->getName() ?? ($route->getActionName() ?? null);
            $params    = $route->parameters();
        }

        $nomorKartu = $params['nomorkartu_jkn']
            ?? $params['noKartu']
            ?? $request->input('nomorkartu')
            ?? $request->input('noKartu')
            ?? null;

        $kodePoli = $params['kode_poli']
            ?? $params['kodepoli']
            ?? $request->input('kodepoli')
            ?? $request->input('kdPoli')
            ?? null;

        $tanggalPeriksa = $params['tanggalperiksa']
            ?? $params['tanggal']
            ?? $request->input('tanggalperiksa')
            ?? $request->input('tglDaftar')
            ?? null;
        if ( $tanggalPeriksa ) {
            try {
                $tanggalPeriksa = \Carbon\Carbon::parse($tanggalPeriksa)->toDateString();
            } catch (\Throwable $e) {
                $tanggalPeriksa = null;
            }
        }

        AntrianOnlineApiLog::create([
            'method'          => $request->method(),
            'url'             => substr($request->fullUrl(), 0, 500),
            'route_name'      => $routeName ? substr($routeName, 0, 200) : null,
            'ip'              => $request->ip(),
            'request_headers' => $headers,
            'request_body'    => $requestBody !== '' ? $requestBody : null,
            'response_code'   => $response->getStatusCode(),
            'response_body'   => $responseBody !== '' ? $responseBody : null,
            'nomor_kartu'     => $nomorKartu ? substr((string) $nomorKartu, 0, 30) : null,
            'kode_poli'       => $kodePoli ? substr((string) $kodePoli, 0, 10) : null,
            'tanggal_periksa' => $tanggalPeriksa,
            'duration_ms'     => $durationMs,
            'tenant_id'       => 1,
        ]);
    }
}
