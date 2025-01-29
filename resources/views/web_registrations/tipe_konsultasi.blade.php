<div class="text-center">
    <h2>Tujuan Poli</h2>
</div>
<button id="dokter_umum" class="btn btn-info btn-lg btn-block" onclick="submitTipeKonsultasi(1, this);return false;">
    Dokter Umum ( {{ $tipe_konsultasi_dokter_umum->sisa_antrian }} antrian )
</button>
<button id="dokter_gigi" class="btn btn-info btn-lg btn-block" onclick="submitTipeKonsultasi(2, this);return false;">
    Dokter Gigi ( {{ $tipe_konsultasi_dokter_gigi->sisa_antrian }} antrian )
</button>
<button id="bidan" class="btn btn-info btn-lg btn-block" onclick="submitTipeKonsultasi(3, this);return false;">
    Bidan ( {{ $tipe_konsultasi_bidan->sisa_antrian }} antrian )
</button>

@if ($antrians->count())
    <button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi();return false;">
        Kembali
    </button>
@endif
