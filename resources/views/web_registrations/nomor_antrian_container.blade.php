@foreach ($antrians as $antrian)
    <div class="alert alert-info">
        <p>Nomor Antrian Anda</p>
        <h4 class="nomor_antrian">{{ $antrian->nomor_antrian }}  ( {{ ucwords( $antrian->nama ) }} )</h4>
        {{-- Aturan khusus walk-in (per instruksi dr. Yoga 2026-10-04):
             pindahan dari generic rules box di nomor_antrian.blade.php.
             Walk-in beda dgn reservasi terjadwal — tidak ada deadline
             scan absolut, pasien datang berdasar sisa antrian di depan. --}}
        <div class="mb-10 mt-10" style="padding:10px; background:#fff; border-radius:4px; border:1px solid #5bc0de;">
            <strong>Scan QR berikut saat tiba di klinik</strong>
            <ul style="padding-left:18px; margin:8px 0 0; font-size:13px; color:#31708f;">
                <li>Mohon kedatangan <strong>30 menit sebelum</strong> perkiraan panggilan atau saat sisa <strong>10 antrian</strong> di depan.</li>
                <li>Scan QR di klinik untuk konfirmasi kehadiran.</li>
                <li>Apabila antrian terlewat, mohon ambil antrian baru.</li>
            </ul>
        </div>
        <div>
            @if ( !is_null( $antrian->qr_code_path_s3 ) )
                <img class="center-fit" src="{{ \Storage::disk('s3')->url($antrian->qr_code_path_s3) }}" alt=''/>
            @endif
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
