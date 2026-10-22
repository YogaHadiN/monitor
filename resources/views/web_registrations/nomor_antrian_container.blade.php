@foreach ($antrians as $antrian)
    <div class="alert alert-info">
        <p>Nomor Antrian Anda</p>
        <h4 class="nomor_antrian">{{ $antrian->nomor_antrian }}  ( {{ ucwords( $antrian->nama ) }} )</h4>
        {{-- Aturan khusus walk-in (per instruksi dr. Yoga 2026-10-04):
             pindahan dari generic rules box di nomor_antrian.blade.php.
             Walk-in beda dgn reservasi terjadwal — tidak ada deadline
             scan absolut, pasien datang berdasar sisa antrian di depan. --}}
        @php
            // Peringatan sama dgn WA/Telegram (dr. Yoga 2026-10-22): hanya
            // kalau belum hadir DAN sudah lewat batas (≤10 antrian / ≤30 menit).
            $sisaWeb = (int) ($antrian->sisa_antrian ?? 0);
            $nPetugasWeb = 1;
            if ((int) $antrian->tipe_konsultasi_id > 0) {
                $nowWeb = \Carbon\Carbon::now('Asia/Jakarta');
                $nPetugasWeb = max(1, (int) \App\Models\PetugasPemeriksa::whereDate('tanggal', $nowWeb->toDateString())
                    ->where('tipe_konsultasi_id', (int) $antrian->tipe_konsultasi_id)
                    ->where('jam_mulai', '<=', $nowWeb->format('H:i:s'))
                    ->where('jam_akhir', '>=', $nowWeb->format('H:i:s'))
                    ->count());
            }
            $menitWeb = $sisaWeb === 0 ? 0 : (int) ceil($sisaWeb * 6 / $nPetugasWeb);
            $lewatBatasWeb = (int) $antrian->sudah_hadir_di_klinik !== 1 && ($sisaWeb <= 10 || $menitWeb <= 30);
        @endphp
        @if ($lewatBatasWeb)
            <div class="alert alert-danger mb-10 mt-10" style="padding:10px;">
                <strong>⚠️ ANTRIAN ANDA BERESIKO TERHAPUS</strong><br>
                Kakak sudah <strong>melewati batas waktu 30 menit</strong> harus datang sebelum panggilan <strong>dan 10 antrian di depan</strong>. Antrian ini bisa terhapus kapan saja. Silakan buat antrian baru apabila antrian terlewat.
            </div>
        @endif
        <div class="mb-10 mt-10" style="padding:10px; background:#fff; border-radius:4px; border:1px solid #5bc0de;">
            <strong>Scan QR berikut saat tiba di klinik</strong>
            <ul style="padding-left:18px; margin:8px 0 0; font-size:13px; color:#31708f;">
                <li>Harap datang paling lambat <strong>30 menit sebelum</strong> antrian Anda dipanggil, atau saat sisa <strong>10 antrian di depan</strong>.</li>
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
