@if (isset( $web_registration ))
    <br>
    <div class="table-responsive">
        <table class="table table-hover table-condensed table-bordered" width="100%">
            <tbody>
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
                        <td>{{ $web_registration->staf->nama }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
@endif
