<div class="text-center">
    <h2>Pilih Pasien</h2>
</div>
@foreach ($pasiens as $k => $pasien)
    <button class="btn btn-info btn-lg btn-block" value="{{ $pasien->pasien_id }}" onclick="submit(this, 'pasien');return false;">
        {{ ucwords(  strtolower(  $pasien->nama  )  ) }}
    </button>
@endforeach
    <button class="btn btn-success btn-lg btn-block" value="" onclick="submit(this, 'pasien');return false;">
        Lainnya
    </button>
    <br>
<button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi();return false;">
    Ulangi
</button>
