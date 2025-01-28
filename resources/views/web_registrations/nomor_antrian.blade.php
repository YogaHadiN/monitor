<div class="text-center">
    <h3>Antrian Terpanggil</h3>
    <h4>{{ $antrian->ruangan->antrian?->nomor_antrian }}</h4>
    <br>
    <h3>Nomor Antrian</h3>
    <h4>{{ $antrian->nomor_antrian }}</h4>
    <div class="alert alert-info">
        Scan QR berikut saat tiba di klinik
    </div>
    <div>
        <img class="center-fit" src="{{ \Storage::disk('s3')->url($antrian->qr_code_path_s3) }}" alt=''/>
    </div>
    <div class="alert alert-danger">
        Mohon ambil antrian kembali apabila antrian terlewat
    </div>
</div>
