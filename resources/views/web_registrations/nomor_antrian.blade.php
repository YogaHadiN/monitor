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
         arahkan mereka onboard bot Telegram supaya semua notif
         (panggilan antrian, survey, followup) auto route ke TG.
         Per instruksi dr. Yoga 2026-09-23. --}}
    <div class="row mb-10">
        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
            <div class="alert alert-info" style="border-left: 4px solid #0088cc;">
                <h4 style="margin-top:0;">📲 Aktifkan Notifikasi Telegram</h4>
                <p style="margin-bottom:8px;">
                    Terima notifikasi panggilan antrian, konfirmasi jadwal,
                    dan info klinik langsung via <strong>Telegram Bot</strong>
                    &mdash; tanpa gangguan spam.
                </p>
                <p style="margin-bottom:12px; font-size:12px; color:#555;">
                    Tap tombol di bawah &rarr; tap <strong>Start</strong> di Telegram &rarr;
                    kirim nomor HP Kakak.
                </p>
                <a
                    href="https://www.klinikjatielok.com/tg?src=web_reg"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-lg btn-block"
                    style="background:#0088cc; color:#fff; font-weight:600;"
                >
                    Aktifkan Telegram Bot
                </a>
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
