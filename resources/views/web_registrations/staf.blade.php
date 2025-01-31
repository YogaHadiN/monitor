<div class="text-center">
    <h2>Pilih Dokter</h2>
</div>
@foreach ($petugas_pemeriksas as $k => $petugas)
    <button class="btn btn-info btn-lg btn-block" value="{{$petugas->id}}" onclick="submit(this, 'staf');return false;">
        {{ $petugas->staf->nama_dengan_gelar }} ( Sisa {{ $petugas->sisa_antrian }} Antrian )
        @if (
                $petugas_pemeriksas->count() > 1 &&
                $petugas->antrian_terpendek
            )
            <br>
            (Antrian Terpendek)
        @endif
    </button>
@endforeach
<button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi();return false;">
    Ulangi
</button>
