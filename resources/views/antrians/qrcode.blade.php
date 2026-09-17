<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
    <title>QR Code Antrian — Klinik Jati Elok</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            min-height: 100vh;
            background: linear-gradient(180deg, #3AA6B9 0%, #2b8695 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #222;
            padding: 16px;
        }
        .container {
            max-width: 480px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 8px 0;
            margin-bottom: 12px;
        }
        .logo-wrap {
            background: #fff;
            border-radius: 10px;
            padding: 6px 12px;
            display: inline-flex;
            align-items: center;
        }
        .logo-wrap img { height: 38px; display: block; }
        .header .title {
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.18);
            margin-bottom: 16px;
        }
        .error-card {
            background: #fff3f3;
            border: 2px solid #d9534f;
            color: #a83232;
            text-align: center;
            padding: 24px;
        }
        .error-card h2 {
            font-size: 18px;
            margin-bottom: 8px;
        }
        .error-card p {
            font-size: 14px;
            line-height: 1.5;
        }
        .nomor-antrian {
            text-align: center;
            padding: 12px 0;
            border-bottom: 2px dashed #e0e0e0;
            margin-bottom: 16px;
        }
        .nomor-antrian .label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .nomor-antrian .value {
            font-size: 56px;
            font-weight: 900;
            color: #1c4c56;
            line-height: 1;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 8px 12px;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .info-grid dt {
            color: #666;
            font-weight: 600;
        }
        .info-grid dd {
            color: #222;
            font-weight: 500;
        }
        .qr-section {
            text-align: center;
            padding: 8px 0;
        }
        .qr-section img {
            max-width: 100%;
            width: 300px;
            height: 300px;
            border: 8px solid #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background: #fff;
        }
        .qr-section .hint {
            margin-top: 12px;
            font-size: 13px;
            color: #555;
            line-height: 1.5;
        }
        .instructions {
            background: #fff8e1;
            border: 2px solid #f0ad4e;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 13px;
            line-height: 1.55;
            margin-bottom: 12px;
        }
        .instructions .title {
            font-weight: 900;
            color: #8a6d3b;
            margin-bottom: 6px;
            display: block;
        }
        .instructions ol {
            margin-left: 18px;
        }
        .instructions ol li { margin-bottom: 4px; }
        .warning {
            background: #fbe9e7;
            border: 2px solid #d9534f;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13px;
            line-height: 1.5;
            color: #a83232;
        }
        .warning b { color: #7a1f1f; }
        .footer {
            text-align: center;
            color: rgba(255,255,255,0.85);
            font-size: 12px;
            padding: 16px 0 8px;
            line-height: 1.5;
        }
        .footer a {
            color: #fff;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-wrap">
                <img src="{{ secure_url('images/logo.png') }}" alt="Klinik Jati Elok">
            </div>
            <div class="title">Klinik Jati Elok</div>
        </div>

        @if($error)
            <div class="card error-card">
                <h2>⚠️ QR Code tidak tersedia</h2>
                <p>{{ $error }}</p>
            </div>
        @else
            <div class="card">
                <div class="nomor-antrian">
                    <div class="label">Nomor Antrian</div>
                    <div class="value">{{ $antrian->nomor_antrian }}</div>
                </div>

                <dl class="info-grid">
                    @if($antrian->nama)
                        <dt>Nama</dt>
                        <dd>{{ $antrian->nama }}</dd>
                    @endif
                    @if(optional($antrian->tipe_konsultasi)->tipe_konsultasi)
                        <dt>Poli</dt>
                        <dd>{{ ucwords($antrian->tipe_konsultasi->tipe_konsultasi) }}</dd>
                    @endif
                    @if(optional($antrian->ruangan)->nama)
                        <dt>Ruangan</dt>
                        <dd>{{ $antrian->ruangan->nama }}</dd>
                    @endif
                    <dt>Tanggal</dt>
                    <dd>{{ \Carbon\Carbon::parse($antrian->created_at)->translatedFormat('l, d F Y') }}</dd>
                </dl>

                <div class="qr-section">
                    <img src="{{ $qr_url }}" alt="QR Code Antrian">
                    <div class="hint">Tunjukkan QR di atas ke petugas saat tiba di klinik</div>
                </div>
            </div>

            <div class="instructions">
                <span class="title">📋 CARA SCAN QR CODE</span>
                <ol>
                    <li>Datang ke <b>Klinik Jati Elok</b> paling lambat <b>30 menit sebelum</b> perkiraan panggilan</li>
                    <li>Buka halaman ini di HP Anda</li>
                    <li>Tunjukkan QR code di atas ke <b>petugas pendaftaran</b></li>
                    <li>Petugas akan scan → nomor antrian otomatis terkonfirmasi</li>
                </ol>
            </div>

            <div class="warning">
                <b>⚠️ PENTING:</b> Antrian akan <b>otomatis terhapus</b> oleh sistem apabila:
                <ol style="margin-left: 18px; margin-top: 4px;">
                    <li>Tidak scan QR sebelum tiba giliran</li>
                    <li>Dipanggil 3x tapi tidak hadir di ruang periksa</li>
                </ol>
            </div>
        @endif

        <div class="footer">
            Klinik Jati Elok — SunatBoy<br>
            Komp. Bumi Jati Elok Blok A1 No. 4-5, Pagedangan, Tangerang<br>
            Halaman ini auto-refresh tiap 60 detik
        </div>
    </div>
</body>
</html>
