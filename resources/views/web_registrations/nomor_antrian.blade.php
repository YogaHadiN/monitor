<div class="text-center">
    <p>Antrian Terpanggil</p>
    <p>{{ $antrians->first()->ruangan->antrian?->nomor_antrian }}</p>
    <p>Nomor Antrian Anda</p>
    <div class="alert alert-info">
        @foreach ($antrians as $antrian)
            <h4>{{ $antrian->nomor_antrian }}  ( {{ ucwords( $antrian->nama ) }} )</h4>
        @endforeach
    </div>
    <div class="row mb-10">
        <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
            <button class="btn btn-danger btn-block" onclick="batalkan();return false;">
                Batalkan
            </button>
        </div>
        <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
            <button class="btn btn-success btn-block" onclick="daftar_lagi();return false;">
                Daftar Lagi
            </button>
        </div>
    </div>
    <div class="alert alert-danger">
        Mohon ambil antrian kembali apabila antrian terlewat
    </div>
    <div class="alert alert-info">
        Scan QR berikut saat tiba di klinik
    </div>
    <div>
        <img class="center-fit" src="{{ \Storage::disk('s3')->url($antrian->qr_code_path_s3) }}" alt=''/>
    </div>
</div>
