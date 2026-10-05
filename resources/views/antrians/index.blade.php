<!DOCTYPE html>
<html moznomarginboxes mozdisallowselectionprint>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Daftar Online Klinik Jati Elok</title>
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

    <!-- Optional theme -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">

    <style type="text/css" media="screen">
        .mt-20 {
            margin-top: 20px;
        }
        .text-center {
            text-align: center;
        }
    </style>
    </head>
    <body class="text-center">
        <div class="container">
            <div class="row">
                <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
                    <h4>Klinik Jati Elok</h4>
                </div>
            </div>
            {{-- Daftar Online — dua jalur (per instruksi dr. Yoga 2026-10-05):
                 1. Telegram (rekomendasi) — notifikasi antrian/jadwal/chat admin
                    terintegrasi, tidak kena cutoff WhatsApp
                 2. Web — fallback untuk pasien yg belum pakai Telegram --}}
            <div class="mt-20" style="margin-top:24px;">
                <div style="border-top:1px solid #ddd; padding-top:20px;">
                    <p style="font-size:15px; color:#555; margin-bottom:8px;">
                        📲 Aktifkan notifikasi antrian, jadwal dokter, dan info klinik via Telegram Bot
                    </p>
                    <p style="font-size:14px; color:#c0392b; font-weight:600; margin-bottom:16px;">
                        💬 Chat dengan admin klinik <u>hanya</u> melalui Telegram.
                        <br>
                        <span style="font-weight:normal; color:#777;">
                            (WhatsApp sudah tidak aktif)
                        </span>
                    </p>

                    {{-- Rekomendasi: Telegram --}}
                    <a href="{{ url('tg?src=home_page') }}"
                       target="_blank" rel="noopener noreferrer"
                       class="btn btn-block"
                       style="background:#0088cc; color:#fff; font-weight:600; padding:14px; margin-bottom:6px;">
                        <span style="display:block; font-size:12px; font-weight:normal; opacity:0.9; margin-bottom:2px;">
                            ⭐ Rekomendasi
                        </span>
                        Daftar Lewat Telegram
                        <br>
                        <small style="font-weight:normal; opacity:0.9;">@KlinikJatiElokBot</small>
                    </a>

                    {{-- Fallback: Web --}}
                    @if (!($libur ?? false))
                        <a href="{{ url('daftar_online') }}"
                           class="btn btn-block"
                           style="background:#f0f3f5; color:#333; font-weight:500; padding:12px; border:1px solid #d4d9dd;">
                            Daftar Lewat Web
                            <br>
                            <small style="font-weight:normal; color:#777;">
                                tanpa notifikasi
                            </small>
                        </a>
                    @else
                        <div class="alert alert-warning" style="margin-top:8px; font-size:13px;">
                            {{ $text_libur ?? 'Pendaftaran web sedang libur.' }}
                        </div>
                    @endif
                </div>
            </div>
            {{-- <a href='finspot:FingerspotReg;{{ $url_register }}' class='btn btn-sm btn-primary'>Register</a> --}}
        </div>
    </body>
</html>
