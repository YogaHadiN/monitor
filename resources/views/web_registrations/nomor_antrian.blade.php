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
    <div class="text-left">
        <h4>Anda memiliki {{ $antrians->count() }} Antrian : </h4>
    </div>
    <h2 id="nomor_panggilan_mobile"></h2>
    <div id="container_antrian">
        @include('web_registrations.nomor_antrian_container')
    </div>
</div>
