<div class="text-center">
    <h2>Pilih Dokter</h2>
</div>
@foreach ($petugas_pemeriksas as $k => $petugas)
    <button class="btn btn-info btn-lg btn-block" onclick="staf({{$petugas->id}});return false;">
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
