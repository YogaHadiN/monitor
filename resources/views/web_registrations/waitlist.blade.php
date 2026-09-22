@php
    $jam_mulai = !is_null($petugas_pemeriksa) ? substr((string) $petugas_pemeriksa->jam_mulai, 0, 5) : null;
    $jam_akhir = !is_null($petugas_pemeriksa) ? substr((string) $petugas_pemeriksa->jam_akhir, 0, 5) : null;
@endphp
<div class="alert alert-warning">
    <h3>Slot Sudah Penuh</h3>
    @if (!is_null($petugas_pemeriksa))
        <p>
            Kuota <strong>Reservasi Terjadwal</strong> untuk
            <strong>{{ $petugas_pemeriksa->staf->nama_dengan_gelar }}</strong>
            sudah penuh.
        </p>
        @if ($jam_mulai && $jam_akhir)
            <p>Jam pelayanan: <strong>{{ $jam_mulai }}–{{ $jam_akhir }}</strong></p>
        @endif
    @endif
    <p>Apakah Anda ingin <strong>gabung waitlist</strong>?</p>
    <ul style="padding-left: 18px; margin-bottom: 0;">
        <li>Bila ada pasien yang membatalkan, Anda akan diproses <strong>secara berurutan</strong>.</li>
        <li>Lanjutkan mengisi data sampai selesai untuk mengonfirmasi slot waitlist.</li>
        <li>Bila menolak, reservasi akan dibatalkan dan Anda dapat memilih jadwal lain.</li>
    </ul>
</div>

@if (!empty($other_dokters_gigi) && $other_dokters_gigi->isNotEmpty())
    <div class="alert alert-info">
        <h4 style="margin-top:0;">Dokter Gigi Lain Praktek Hari Ini</h4>
        <p style="margin-bottom:8px;">Kakak juga bisa pertimbangkan dokter berikut di jam berbeda:</p>
        <ul style="padding-left: 18px; margin-bottom: 0;">
            @foreach ($other_dokters_gigi as $other)
                @php
                    $namaOther = optional($other->staf)->nama_dengan_gelar
                        ?? optional($other->staf)->nama
                        ?? 'Dokter';
                    $jm = !empty($other->jam_mulai_default)
                        ? substr((string) $other->jam_mulai_default, 0, 5)
                        : (!empty($other->jam_mulai) ? substr((string) $other->jam_mulai, 0, 5) : '-');
                    $ja = !empty($other->jam_akhir_default)
                        ? substr((string) $other->jam_akhir_default, 0, 5)
                        : (!empty($other->jam_akhir) ? substr((string) $other->jam_akhir, 0, 5) : '-');
                    $onlineOk = (int) ($other->online_registration_enabled ?? 0) === 1;
                @endphp
                <li>
                    <strong>{{ $namaOther }}</strong> — {{ $jm }}–{{ $ja }}
                    @if ($onlineOk)
                        <span class="label label-success">bisa daftar online</span>
                    @else
                        <span class="label label-danger">hanya datang langsung</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif

<button class="btn btn-success btn-lg btn-block" value="1" onclick="submit(this, 'waitlist');return false;">
    Ya, Gabung Waitlist
</button>
<button class="btn btn-default btn-lg btn-block" value="0" onclick="submit(this, 'waitlist');return false;">
    Tidak, Batalkan
</button>
