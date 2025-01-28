<div class="text-center">
    <h2>Pilih Pasien</h2>
</div>
@foreach ($pasiens as $k => $pasien)
    <button class="btn btn-info btn-lg btn-block" onclick="pilihPasien({{$pasien->pasien_id}});return false;">
        {{ $pasien->nama }}
    </button>
@endforeach
    <button class="btn btn-success btn-lg btn-block" onclick="pilihPasien();return false;">
        Lainnya
    </button>
