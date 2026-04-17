<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Cek Status Obat | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,600,900" rel="stylesheet">
    <style type="text/css">
        html, body {
            background-color: #3AA6B9;
            color: #222;
            font-family: 'Nunito', sans-serif;
            font-weight: 200;
            margin: 0;
            min-height: 100vh;
        }
        * { box-sizing: border-box; }

        .wrap {
            max-width: 520px;
            margin: 40px auto;
            padding: 0 16px;
        }
        .logo-wrap { text-align: center; margin-bottom: 20px; }
        .logo-wrap img {
            background-color: #C1ECE4;
            border-radius: 20px;
            padding: 6px 15px;
            max-width: 260px;
        }

        .container_antrian {
            background-color: #ffffff;
            border-radius: 17px;
            padding: 20px 20px 28px;
        }
        .title_antrian_farmasi {
            border-radius: 200px;
            padding: 10px 30px;
            color: #ffffff;
            font-weight: 900;
            font-size: 22px;
            background-color: #3AA6B9;
            text-align: center;
            margin-bottom: 24px;
        }

        label { font-weight: 600; }
        input.form-control {
            border-radius: 12px;
            border: 1px solid #ccc;
            padding: 10px 14px;
            font-size: 18px;
            width: 100%;
            box-shadow: none;
        }
        .btn-primary {
            background-color: #3AA6B9;
            border-color: #3AA6B9;
            border-radius: 200px;
            padding: 10px 16px;
            font-weight: 900;
            font-size: 18px;
            color: #fff;
            width: 100%;
            margin-top: 14px;
        }
        .btn-primary:hover, .btn-primary:focus { background-color: #2f8a9a; border-color: #2f8a9a; }

        hr { margin: 28px 0; border-top: 1px solid #e5e5e5; }

        .result { text-align: center; }
        .label-small { font-size: 14px; color:#888; margin: 0; font-weight: 600; }
        .nomor-big {
            font-size: 64px; font-weight: 900; color:#3AA6B9; margin: 4px 0 14px;
        }
        .jenis { font-size: 16px; margin-bottom: 16px; }
        .jenis strong { color: #3B6345; }

        .badge {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 200px;
            font-weight: 900;
            font-size: 20px;
            color: #fff;
        }
        .badge-menunggu         { background-color: #C63D2F; }
        .badge-diproses         { background-color: #FFBB5C; color: #222; }
        .badge-tunggu_dipanggil { background-color: #3AA6B9; }
        .badge-siap_diambil     { background-color: #3B6345; }

        .alert { border-radius: 12px; margin: 20px 0 0; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="logo-wrap">
        <img src="{{ secure_url('images/logo.png') }}" alt="logo">
    </div>
    <div class="container_antrian">
        <div class="title_antrian_farmasi">Cek Status Obat</div>

        <form method="post" action="{{ url('antrian_obat/lookup') }}">
            @csrf
            <div class="form-group">
                <label for="nomor">Nomor Antrian</label>
                <input type="text" name="nomor" id="nomor" class="form-control"
                       placeholder="Contoh: A12"
                       value="{{ $nomor }}" autofocus required>
            </div>
            <button type="submit" class="btn btn-primary">Cek</button>
        </form>

        @if(!is_null($hasil))
            <hr>
            @if($hasil['status'] === 'ok')
                <div class="result">
                    <p class="label-small">Nomor Antrian</p>
                    <div class="nomor-big">{{ $hasil['nomor_antrian'] }}</div>
                    <div class="jenis">
                        Jenis Obat: <strong>{{ $hasil['racikan'] ? 'Racikan' : 'Jadi' }}</strong>
                    </div>
                    <p class="label-small">Status</p>
                    <span class="badge badge-{{ $hasil['status_kode'] }}">
                        {{ $hasil['status_label'] }}
                    </span>
                </div>
            @elseif($hasil['status'] === 'belum_apotek')
                <div class="alert alert-warning">
                    Nomor antrian ditemukan, namun obat belum masuk antrian apotek.
                </div>
            @else
                <div class="alert alert-danger">
                    Nomor antrian tidak ditemukan untuk hari ini.
                </div>
            @endif
        @endif
    </div>
</div>
</body>
</html>
