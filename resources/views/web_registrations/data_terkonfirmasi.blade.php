@php
    $isScheduled = (int) ($web_registration->schedulled_booking ?? 0) >= 1;
    $isWaitlist  = (int) ($web_registration->schedulled_booking ?? 0) === 2 && (int) ($web_registration->waitlist_flag ?? 0) === 1;
    $petugas     = $web_registration->petugas_pemeriksa ?? null;
    $jam_mulai   = $petugas ? substr((string) $petugas->jam_mulai, 0, 5) : null;
    $jam_akhir   = $petugas ? substr((string) $petugas->jam_akhir, 0, 5) : null;
@endphp
<h2>Konfirmasi Data</h2>
@if ($isScheduled)
    <div class="alert alert-warning">
        <strong>{{ $isWaitlist ? 'Reservasi Waitlist' : 'Reservasi Terjadwal' }}</strong>
        @if ($jam_mulai && $jam_akhir)
            <div>Jam pelayanan: <strong>{{ $jam_mulai }}–{{ $jam_akhir }}</strong></div>
        @endif
        <ul class="mt-10" style="padding-left: 18px; margin-bottom: 0;">
            @if ($isWaitlist)
                <li>Anda akan dimasukkan ke <strong>waitlist</strong> setelah klik <em>Lanjutkan</em>.</li>
                <li>Kami proses secara berurutan bila ada slot batal.</li>
            @else
                <li>Anda akan dibuatkan <strong>slot reservasi terjadwal</strong>, bukan antrian walk-in.</li>
                <li>QR Code muncul setelah konfirmasi.</li>
            @endif
            <li>Reservasi bisa dibatalkan via tombol <em>Hapus Reservasi</em>.</li>
        </ul>
        <p style="margin-top: 12px; margin-bottom: 6px;">
            <strong>Dengan melanjutkan berarti Anda setuju dengan ketentuan berikut:</strong>
        </p>
        <ul style="padding-left: 18px; margin-bottom: 0;">
            <li>Jika antrean terlewat, silakan mengambil antrean kembali.</li>
            <li>Melakukan scan QR di klinik sebelum pukul 16:45 (15 menit sebelum jam praktik dimulai) atau reservasi ini dihapus oleh sistem.</li>
            <li>Nomor Antrian diberikan setelah Scan QR Code dan urutan nomor antrian berdasarkan urutan Scan QR Code.</li>
            <li>Pelayanan Dokter Gigi adalah pelayanan tindakan sehingga tidak bisa diperkirakan durasi layanan.</li>
            <li>Pendaftaran dokter gigi secara langsung dimulai jam 17:00 hanya bila slot pendaftaran masih tersedia.</li>
        </ul>
    </div>
@endif
<p>Mohon dicek kembali data yang sudah diinput</p>
<div class="table-responsive">
    <table class="table table-hover table-condensed table-bordered" width="100%">
        <tbody>
            @if ($web_registration->nama)
                <tr>
                    <td>Nama</td>
                    <td>{{ ucwords(  $web_registration->nama  ) }}</td>
                </tr>
            @endif
            @if ($web_registration->alamat)
                <tr>
                    <td>Alamat</td>
                    <td>{{ $web_registration->alamat }}</td>
                </tr>
            @endif
            @if ($web_registration->tanggal_lahir)
                <tr>
                    <td>Tanggal Lahir</td>
                    <td>{{ \Carbon\Carbon::parse( $web_registration->tanggal_lahir )->format('d M Y') }}</td>
                </tr>
            @endif
            @if ($web_registration->tipe_konsultasi_id)
                <tr>
                    <td>Poli</td>
                    <td>{{ ucwords( $web_registration->tipe_konsultasi->tipe_konsultasi ) }}</td>
                </tr>
            @endif
            @if ($web_registration->registrasi_pembayaran_id)
                <tr>
                    <td>Pembayaran</td>
                    <td>{{ $web_registration->registrasi_pembayaran->pembayaran }}</td>
                </tr>
            @endif
            @if (
                    $web_registration->registrasi_pembayaran_id == 2 &&
                    $web_registration->nomor_asuransi_bpjs
                )
                <tr>
                    <td>No Bpjs</td>
                    <td>{{ $web_registration->nomor_asuransi_bpjs }}</td>
                </tr>
            @endif
            @if ($web_registration->staf_id)
                <tr>
                    <td>Pemeriksa</td>
                    <td>{{ $web_registration->staf->nama_dengan_gelar }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>

<button class="btn btn-lg btn-info btn-block" id="lanjutkan" onclick="submit(this, 'lanjutkan');return false;">
    Lanjutkan
</button>
<button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi(this);return false;">
    Ulangi
</button>
