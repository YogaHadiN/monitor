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
            @if ($libur)
                {{ $text_libur }}
            @else
                <h1>Daftar Online</h1>
                <a href="{{ url('daftar_online') }}" class="mt-20 btn btn-info btn-block">Klik Disini</a>
            @endif

            {{-- CTA Telegram Bot (dr. Yoga 2026-09-30). Route /tg
                 track click di review_link_clicks (slug=telegram_bot,
                 src=home_page) sebelum redirect ke t.me/KlinikJatiElokBot.
                 Note (2026-10-01): tegaskan chat admin HANYA via Telegram
                 — WA sudah dinonaktifkan per cutoff. --}}
            <div class="mt-20" style="margin-top:24px;">
                <div style="border-top:1px solid #ddd; padding-top:20px;">
                    <p style="font-size:15px; color:#555; margin-bottom:8px;">
                        📲 Aktifkan notifikasi antrian, jadwal dokter, dan info klinik via Telegram Bot
                    </p>
                    <p style="font-size:14px; color:#c0392b; font-weight:600; margin-bottom:12px;">
                        💬 Chat dengan admin klinik <u>hanya</u> melalui Telegram.
                        <br>
                        <span style="font-weight:normal; color:#777;">
                            (WhatsApp sudah tidak aktif)
                        </span>
                    </p>
                    <a href="{{ url('tg?src=home_page') }}"
                       target="_blank" rel="noopener noreferrer"
                       class="btn btn-block"
                       style="background:#0088cc; color:#fff; font-weight:600; padding:12px;">
                        Chat Admin via Telegram Bot
                        <br>
                        <small style="font-weight:normal; opacity:0.9;">@KlinikJatiElokBot</small>
                    </a>
                </div>
            </div>
            {{-- <a href='finspot:FingerspotReg;{{ $url_register }}' class='btn btn-sm btn-primary'>Register</a> --}}
        </div>
    </body>
</html>
