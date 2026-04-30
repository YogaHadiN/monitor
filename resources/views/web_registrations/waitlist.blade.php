<div class="alert alert-warning">
    <h3>Slot Sudah Penuh</h3>
    @if (!is_null($petugas_pemeriksa))
        <p>
            Slot reservasi terjadwal untuk
            <strong>{{ $petugas_pemeriksa->staf->nama_dengan_gelar }}</strong>
            hari ini sudah penuh.
        </p>
    @endif
    <p>Apakah Anda ingin <strong>gabung waitlist</strong>? Anda akan otomatis diproses bila ada pasien yang membatalkan slotnya.</p>
</div>

<button class="btn btn-success btn-lg btn-block" value="1" onclick="submit(this, 'waitlist');return false;">
    Ya, Gabung Waitlist
</button>
<button class="btn btn-default btn-lg btn-block" value="0" onclick="submit(this, 'waitlist');return false;">
    Tidak, Batalkan
</button>
