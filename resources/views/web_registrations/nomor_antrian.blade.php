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
    {{-- Aturan generic DIHAPUS dari sini (dr. Yoga 2026-10-04).
         Scan QR specific info dipindah ke dalam SETIAP box reservasi /
         antrian di bawah, dgn label disesuaikan apakah reservasi
         terjadwal (ada deadline scan + konsekuensi dibatalkan) atau
         walk-in biasa (30 menit sebelum panggilan / sisa 10 antrian). --}}

    {{-- CTA aktivasi Telegram bot. Setelah pasien daftar via web,
         arahkan mereka onboard bot Telegram supaya semua pemberitahuan
         (panggilan antrian, survey, followup) auto route ke TG.
         Per instruksi dr. Yoga 2026-09-23. Design refresh 2026-10-07. --}}
    <style>
        @keyframes tg_pulse {
            0%   { box-shadow: 0 0 0 0 rgba(0,136,204,0.55); }
            70%  { box-shadow: 0 0 0 14px rgba(0,136,204,0); }
            100% { box-shadow: 0 0 0 0 rgba(0,136,204,0); }
        }
        .tg-cta-wrap {
            background: linear-gradient(135deg, #e8f4fb 0%, #d4ecf7 100%);
            border: 1px solid #b6dcf0;
            border-radius: 10px;
            padding: 18px 16px;
            text-align: left;
        }
        .tg-cta-head {
            display:flex; align-items:center; gap:10px;
            margin-bottom: 10px;
        }
        .tg-cta-badge {
            display:inline-block;
            background:#ff5252; color:#fff;
            font-size:11px; font-weight:700;
            padding: 3px 9px; border-radius: 12px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .tg-cta-title {
            margin: 0;
            color: #0b4b73;
            font-weight: 700;
            font-size: 18px;
            line-height: 1.3;
        }
        .tg-cta-sub {
            margin: 0 0 12px;
            color: #345062;
            font-size: 13.5px;
            line-height: 1.5;
        }
        .tg-cta-list {
            list-style: none;
            padding: 0;
            margin: 0 0 14px;
        }
        .tg-cta-list li {
            padding: 5px 0;
            color: #1b3a4e;
            font-size: 13.5px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .tg-cta-list li .tg-check {
            color: #0088cc;
            font-weight: 700;
            flex-shrink: 0;
        }
        .tg-cta-btn {
            display:block;
            background: linear-gradient(180deg, #0098e0 0%, #0088cc 100%);
            color: #fff !important;
            font-weight: 700;
            font-size: 16px;
            padding: 14px 16px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none !important;
            border: none;
            animation: tg_pulse 2s infinite;
            transition: transform 0.1s ease;
        }
        .tg-cta-btn:hover, .tg-cta-btn:focus {
            background: linear-gradient(180deg, #00a3f0 0%, #0098e0 100%);
            color: #fff !important;
            transform: translateY(-1px);
            text-decoration: none !important;
        }
        .tg-cta-steps {
            text-align:center;
            margin: 10px 0 0;
            color: #4a6c80;
            font-size: 12px;
        }
        .tg-cta-footer {
            text-align:center;
            margin: 6px 0 0;
            color: #4a6c80;
            font-size: 11.5px;
            font-style: italic;
        }
    </style>
    <div class="row mb-10">
        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
            <div class="tg-cta-wrap">
                <div class="tg-cta-head">
                    <span style="font-size:28px; line-height:1;">📲</span>
                    <div>
                        <span class="tg-cta-badge">Gratis &amp; Praktis</span>
                        <h4 class="tg-cta-title">Pantau Antrian Lewat Telegram</h4>
                    </div>
                </div>
                <p class="tg-cta-sub">
                    Tidak perlu bolak-balik buka halaman ini. Semua pemberitahuan
                    langsung masuk ke <strong>Telegram</strong> Kakak.
                </p>
                <ul class="tg-cta-list">
                    <li><span class="tg-check">✓</span> <span><strong>Pemberitahuan panggilan antrian</strong> real-time saat giliran Kakak hampir tiba</span></li>
                    <li><span class="tg-check">✓</span> <span><strong>Pengingat scan QR</strong> ketika mendekati jam praktek</span></li>
                    <li><span class="tg-check">✓</span> <span><strong>Konfirmasi slot kosong</strong> jika Kakak di waitlist</span></li>
                    <li><span class="tg-check">✓</span> <span>Bisa <strong>chat langsung</strong> dengan admin klinik</span></li>
                </ul>
                <a
                    href="https://www.klinikjatielok.com/tg?src=web_reg"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="tg-cta-btn"
                >
                    🚀 Aktifkan Sekarang &mdash; Gratis
                </a>
                <p class="tg-cta-steps">
                    Tap tombol &rarr; tap <strong>Start</strong> di Telegram &rarr; kirim nomor HP Kakak
                </p>
                <p class="tg-cta-footer">
                    Tanpa spam. Hanya informasi penting seputar antrian Kakak.
                </p>
            </div>
        </div>
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
                    @php $isWaitlist = (int) ($sr->waitlist_flag ?? 0) === 1; @endphp
                    @if ($isWaitlist)
                        <div class="mb-10 mt-10 text-warning">
                            <strong>QR belum aktif</strong> — Kakak masih di waitlist.
                            Kalau ada slot batal, kami akan kirim <strong>inquiry via Telegram</strong>
                            (atau WhatsApp kalau belum onboard Telegram). Balas <strong>ya</strong>
                            di chat itu untuk konfirmasi, baru QR muncul di sini.
                        </div>
                    @else
                        @php
                            // Resolve jam praktek + deadline scan — spesifik per reservasi
                            // (petugas_pemeriksa) biar label akurat utk setiap tipe terjadwal
                            // (gigi 17:00, spesialis kulit 22:00, dsb). Per instruksi dr. Yoga
                            // 2026-10-04.
                            $srPp         = $sr->petugas_pemeriksa ?? null;
                            $srJamMulai   = $srPp ? substr((string) ($srPp->jam_mulai_default ?: $srPp->jam_mulai), 0, 5) : null;
                            $srDatangDari = $srJamMulai
                                ? \Carbon\Carbon::parse($srJamMulai)->subMinutes(30)->format('H:i')
                                : null;
                            $srDeadline   = $srJamMulai
                                ? \Carbon\Carbon::parse($srJamMulai)->subMinutes(15)->format('H:i')
                                : null;
                        @endphp
                        <div class="mb-10 mt-10" style="padding:10px; background:#fff; border-radius:4px; border:1px solid #f0ad4e;">
                            <strong>Scan QR berikut saat tiba di klinik</strong>
                            @if ($srJamMulai)
                                <ul style="padding-left:18px; margin:8px 0 0; font-size:13px;">
                                    <li>Batas akhir scan QR pukul <strong>{{ $srDeadline }}</strong> &mdash; reservasi otomatis <strong>dibatalkan sistem</strong> jika lewat.</li>
                                    <li>Nomor antrian diberikan setelah scan QR; urutan antrian mengikuti urutan scan.</li>
                                </ul>
                            @else
                                <ul style="padding-left:18px; margin:8px 0 0; font-size:13px;">
                                    <li>Scan QR paling lambat 15 menit sebelum jam praktek &mdash; reservasi otomatis dibatalkan jika lewat.</li>
                                </ul>
                            @endif
                        </div>
                        <div>
                            @if (!is_null($sr->qrcode))
                                <img class="center-fit" src="{{ \Storage::disk('s3')->url($sr->qrcode) }}" alt=''/>
                            @endif
                        </div>
                    @endif
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
