    <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
            <button onclick="pglPasien([]); return false" class="btn btn-success btn-sm">
                Aktifkan Notifikasi
            </button>
            
        </div>
    </div>
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
        Mohon ambil antrian kembali apabila antrian terlewat
    </div>
    <div class="alert alert-warning">
        Update terakhir : {{ date('Y-m-d H:i:s')}}
        <button class="btn btn-sm btn-success" onclick="view();">Refresh Antrian</button>
    </div>

    <div class="text-left">
        <h4>Anda memiliki {{ $antrians->count() }} Antrian : </h4>
    </div>
    <h2 id="nomor_panggilan_mobile"></h2>

        @foreach ($antrians as $antrian)
            <div class="alert alert-info">
                <p>Antrian Terpanggil</p>
                <p>{{ $antrian->ruangan->antrian?->nomor_antrian }}</p>
                <p>Nomor Antrian Anda</p>
                <h4 class="nomor_antrian">{{ $antrian->nomor_antrian }}  ( {{ ucwords( $antrian->nama ) }} )</h4>
                <div class="mb-10">
                    Scan QR berikut saat tiba di klinik
                </div>
                <div>
                    <img class="center-fit" src="{{ \Storage::disk('s3')->url($antrian->qr_code_path_s3) }}" alt=''/>
                </div>
                <div class="row mb-10 mt-10">
                    <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
                        <button class="btn btn-danger btn-block" onclick="hapusAntrian({{ $antrian->id }}, this);return false;">
                            Hapus Antrian
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
</div>
