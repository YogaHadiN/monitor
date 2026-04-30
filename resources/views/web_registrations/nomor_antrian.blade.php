<div class="text-center">
    <div class="row mb-10">
        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
            <button class="btn btn-success btn-block" onclick="daftar_lagi();return false;">
                Daftar Lagi
            </button>
        </div>
    </div>
    <div class="row mb-10">
        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
            <button class="btn btn-danger btn-block" onclick="batalkan();return false;">
                Batalkan Semua Antrian
            </button>
        </div>
    </div>
    <div class="alert alert-danger">
        Mohon kehadiran nya 30 menit sebelum perkiraan panggilan <br>
        pastikan <strong>Scan QR CODE</strong> dibawah ini saat sudah tiba di klinik <br>
        Mohon ambil antrian kembali apabila antrian terlewat
    </div>
    <div class="row mb-10">
        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
            <div class="alert alert-info">
                <h5>Lihat Antrian Terakhir</h5>
                <button class="btn btn-warning btn-block" onclick="cekAntrian(this);return false;">
                    Klik Disini
                </button>
            </div>
        </div>
    </div>
    @isset($schedulled_reservations)
        @if ($schedulled_reservations->count())
            <div class="text-left">
                <h4>Reservasi Terjadwal Anda :</h4>
            </div>
            @foreach ($schedulled_reservations as $sr)
                <div class="alert alert-warning">
                    <p><strong>Reservasi Terjadwal</strong>
                        @if ((int) ($sr->waitlist_flag ?? 0) === 1)
                            <span class="label label-default">Waitlist</span>
                        @endif
                    </p>
                    <h4>{{ ucwords($sr->nama) }}</h4>
                    @if ($sr->staf)
                        <div>Dokter: {{ $sr->staf->nama_dengan_gelar }}</div>
                    @endif
                    <div class="mb-10 mt-10">Scan QR berikut saat tiba di klinik</div>
                    <div>
                        @if (!is_null($sr->qrcode))
                            <img class="center-fit" src="{{ \Storage::disk('s3')->url($sr->qrcode) }}" alt=''/>
                        @endif
                    </div>
                    <div class="row mb-10 mt-10">
                        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
                            <button class="btn btn-danger btn-block" onclick="hapusSchedulledReservation({{ $sr->id }}, this);return false;">
                                Hapus Reservasi
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    @endisset
    @if ($antrians->count())
        <div class="text-left">
            <h4>Anda memiliki {{ $antrians->count() }} Antrian : </h4>
        </div>
        <h2 id="nomor_panggilan_mobile"></h2>
        <div id="container_antrian">
            @include('web_registrations.nomor_antrian_container')
        </div>
    @endif
</div>
