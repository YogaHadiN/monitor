<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AntrianApotek;
use App\Models\Antrian;

class AntrianApotekController extends Controller
{
    public function monitorData()
    {
        $antrianapoteks = AntrianApotek::with('antrian.ruangan', 'periksa.terapii', 'periksa.pasien')
            ->whereDate('tanggal', date('Y-m-d'))
            ->orderBy('id', 'asc')
            ->get();

        $jadi = [];
        $racikan = [];

        foreach ($antrianapoteks as $a) {
            if (is_null($a->periksa)) {
                continue;
            }

            $isRacikan = $a->obat_racikan;
            $estimasi  = $isRacikan ? 60 : 30;

            $mulai = optional($a->antrian)->created_at ?? $a->created_at;
            $akhir = AntrianApotek::hasTs(optional($a->periksa)->jam_selesai_obat)
                ? \Carbon\Carbon::parse($a->periksa->jam_selesai_obat)
                : now();

            $waktuTunggu = $mulai ? max(0, $mulai->diffInMinutes($akhir)) : 0;

            $row = [
                'nomor_antrian'       => optional($a->antrian)->nomor_antrian ?? '-',
                'nama_pasien'         => $this->samarkanNama(optional(optional($a->periksa)->pasien)->nama),
                'status'              => (int) $a->status,
                'status_kode'         => $a->status_kode,
                'status_label'        => $a->status_label,
                'waktu_tunggu_menit'  => $waktuTunggu,
                'estimasi_menit'      => $estimasi,
                'selesai'             => $a->status_kode === 'siap_diambil' || $a->status_kode === 'tunggu_dipanggil',
            ];

            if ($isRacikan) {
                $racikan[] = $row;
            } else {
                $jadi[] = $row;
            }
        }

        return response()->json([
            'jadi'             => $jadi,
            'racikan'          => $racikan,
            'estimasi_jadi'    => 30,
            'estimasi_racikan' => 60,
        ]);
    }

    protected function samarkanNama($nama)
    {
        $nama = trim((string) $nama);
        if ($nama === '') {
            return '-';
        }
        $parts = preg_split('/\s+/', $nama);
        $hasil = [array_shift($parts)];
        foreach ($parts as $p) {
            $hasil[] = mb_strtoupper(mb_substr($p, 0, 1)) . '.';
        }
        return implode(' ', $hasil);
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
