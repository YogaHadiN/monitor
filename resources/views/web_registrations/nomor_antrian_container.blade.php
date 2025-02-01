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
