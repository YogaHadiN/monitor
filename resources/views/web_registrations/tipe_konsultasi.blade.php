<div class="text-center">
    <h2>Tujuan Poli</h2>
</div>
<button id="dokter_umum" class="btn btn-info btn-lg btn-block" value="1" onclick="submit(this, 'tipe_konsultasi');return false;">
    Dokter Umum ( {{ $tipe_konsultasi_dokter_umum->sisa_antrian }} antrian )
</button>
<button id="dokter_gigi" class="btn btn-info btn-lg btn-block" value="2" onclick="submit(this, 'tipe_konsultasi');return false;">
    Dokter Gigi ( {{ $tipe_konsultasi_dokter_gigi->sisa_antrian }} antrian )
</button>
<button id="bidan" class="btn btn-info btn-lg btn-block" value="3" onclick="submit(this, 'tipe_konsultasi');return false;">
    Bidan ( {{ $tipe_konsultasi_bidan->sisa_antrian }} antrian )
</button>

@if ($antrians->count())
    <button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi(this);return false;">
        Kembali
    </button>
@endif
