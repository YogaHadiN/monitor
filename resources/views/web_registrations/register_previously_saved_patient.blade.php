<div class="text-center">
    <h2>Pilih Pasien</h2>
</div>
@foreach ($pasiens as $k => $pasien)
    <button class="btn btn-info btn-lg btn-block" onclick="pilihPasien({{$pasien->pasien_id}});return false;">
        {{ ucwords(  strtolower(  $pasien->nama  )  ) }}
    </button>
@endforeach
    <button class="btn btn-success btn-lg btn-block" onclick="pilihPasien();return false;">
        Lainnya
    </button>
    <br>
<button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi();return false;">
    Ulangi
</button>
