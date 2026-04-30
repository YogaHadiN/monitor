<div class="text-center">
    <h2>Pilih Dokter</h2>
</div>
@foreach ($petugas_pemeriksas as $k => $petugas)
    @php
        $isScheduled = (int) ($petugas->schedulled_booking_allowed ?? 0) === 1;
        $btnClass    = $isScheduled ? 'btn btn-warning btn-lg btn-block' : 'btn btn-info btn-lg btn-block';
    @endphp
    <button class="{{ $btnClass }}" value="{{$petugas->id}}" onclick="submit(this, 'staf');return false;">
        {{ $petugas->staf->nama_dengan_gelar }}
        @if ($isScheduled)
            <span class="label label-default">Reservasi Terjadwal</span>
            <div><small>Jam {{ $petugas->jam_mulai }} - {{ $petugas->jam_akhir }}</small></div>
        @else
            ( Sisa {{ $petugas->sisa_antrian }} Antrian )
        @endif
        @if ( $petugas->belum_waktunya_praktek )
            <div>
                Dokter Mulai Praktek Jam {{ $petugas->jam_mulai_default }}
            </div>
        @endif
        @if (
                !$isScheduled &&
                $petugas_pemeriksas->count() > 1 &&
                $petugas->antrian_terpendek
            )
            <br>
            (Antrian Terpendek)
        @endif
    </button>
@endforeach
<button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi(this);return false;">
    Ulangi
</button>
