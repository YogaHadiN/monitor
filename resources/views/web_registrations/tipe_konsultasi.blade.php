<div class="text-center">
    <h2>Tujuan Poli</h2>
</div>
<button id="dokter_umum" class="btn btn-info btn-lg btn-block" onclick="dokter_umum();return false;">
    Dokter Umum
</button>
<button id="dokter_gigi" class="btn btn-info btn-lg btn-block" onclick="dokter_gigi();return false;">
    Dokter Gigi
</button>
<button id="bidan" class="btn btn-info btn-lg btn-block" onclick="bidan();return false;">
    Bidan
</button>

@if ($antrians->count())
    <button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi();return false;">
        Kembali
    </button>
@endif
