<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AntrianApotek;
use App\Models\Antrian;

class AntrianApotekController extends Controller
{
    public function monitorData()
    {
        $antrianapoteks = AntrianApotek::with('antrian.ruangan', 'periksa.terapii')
            ->whereDate('tanggal', date('Y-m-d'))
            ->orderBy('id', 'asc')
            ->get();

        $jadi = [];
        $racikan = [];

        foreach ($antrianapoteks as $a) {
            if (is_null($a->periksa)) {
                continue;
            }

            $row = [
                'nomor_antrian' => optional($a->antrian)->nomor_antrian ?? '-',
                'status'        => (int) $a->status,
                'status_kode'   => $a->status_kode,
                'status_label'  => $a->status_label,
            ];

            if ($a->obat_racikan) {
                $racikan[] = $row;
            } else {
                $jadi[] = $row;
            }
        }

        return response()->json(compact('jadi', 'racikan'));
    }

    public function lookup()
    {
        return view('antrian_obat.lookup', ['hasil' => null, 'nomor' => '']);
    }

    public function lookupCari(Request $request)
    {
        $nomor = trim((string) $request->input('nomor'));
        $hasil = null;

        if ($nomor !== '') {
            $kandidat = Antrian::with('antriable.periksa.terapii', 'ruangan')
                ->whereDate('created_at', date('Y-m-d'))
                ->get();

            $match = $kandidat->first(function ($row) use ($nomor) {
                return strcasecmp((string) $row->nomor_antrian, $nomor) === 0;
            });

            if (is_null($match)) {
                $hasil = ['status' => 'tidak_ditemukan'];
            } elseif ($match->antriable_type !== AntrianApotek::class) {
                $hasil = ['status' => 'belum_apotek'];
            } else {
                $apotek = $match->antriable;
                $hasil = [
                    'status'        => 'ok',
                    'nomor_antrian' => $match->nomor_antrian,
                    'status_kode'   => $apotek->status_kode,
                    'status_label'  => $apotek->status_label,
                    'racikan'       => (bool) $apotek->obat_racikan,
                ];
            }
        }

        return view('antrian_obat.lookup', compact('hasil', 'nomor'));
    }
}
