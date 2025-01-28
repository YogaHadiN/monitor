<h2>Konfirmasi Data</h2>
<p>Mohon dicek kembali data yang sudah diinput</p>
<div class="table-responsive">
    <table class="table table-hover table-condensed table-bordered" width="100%">
        <tbody>
            @if ($web_registration->tipe_konsultasi_id)
                <tr>
                    <td>Poli Tujuan</td>
                    <td>{{ ucwords(  $web_registration->tipe_konsultasi->tipe_konsultasi  ) }}</td>
                </tr>
            @endif
            @if ($web_registration->registrasi_pembayaran_id)
                <tr>
                    <td>Pembayaran</td>
                    <td>{{ $web_registration->registrasi_pembayaran->pembayaran }}</td>
                </tr>
            @endif
            @if ($web_registration->nama)
                <tr>
                    <td>Nama</td>
                    <td>{{ $web_registration->nama }}</td>
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
                    <td>{{ $web_registration->tanggal_lahir }}</td>
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

<button class="btn btn-sm btn-info" id="lanjutkan" onclick="lanjutkan();return false;">
    Lanjutkan
</button>
<button class="btn btn-sm btn-danger" onclick="ulangi();return false;">
    Ulangi
</button>
