<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Antrian Obat | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">
    <style type="text/css">
        html, body {
            background-color: #3AA6B9;
            color: #636b6f;
            font-family: 'Nunito', sans-serif;
            font-weight: 200;
            margin: 0;
        }
        * { box-sizing: border-box; text-align: center; }
        @media (min-width: 1px){
            .container { width: 1280px; height: 100vh; }
        }
        [class*="col-"] { background-color: #3AA6B9; }
        table tr td, table tr th { background-color: #fff; }
        .bw { background-color: #ffffff !important; }

        .header { font-size: 30px; font-weight: 900; text-align: left; height: 40px; }
        .logo { width: 100%; background-color: #C1ECE4; border-radius: 20px; margin: 10px auto; padding: 0px 15px; }
        .waktu { color: #fff; text-align: right; font-weight: 1200; padding: 0px 20px 0px 0; }
        #jam { font-size: 50px; margin-left: 20px; }

        .container_antrian {
            background-color: #ffffff;
            border-radius: 17px;
            padding: 10px 5px;
            margin: 0px 0px 15px 0px;
        }
        .container_antrian_farmasi { height: 72vh; }

        .title_antrian_farmasi {
            border-radius: 200px;
            padding: 10px 30px;
            margin: 0px 20px;
            color: #ffffff;
            font-weight: 900;
            font-size: 22px;
            background-color: #3AA6B9;
        }

        .table-farmasi { font-size: 18px; }
        .table>thead>tr>th,
        .table>tbody>tr>td { border: none; }
        table tr td:nth-child(1) { text-align: center; font-weight: 900; }
        table tr th { text-align: center; }

        .badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 20px;
            font-weight: 900;
            font-size: 14px;
            color: #fff;
        }
        .badge-menunggu         { background-color: #C63D2F; }
        .badge-diproses         { background-color: #FFBB5C; color: #222; }
        .badge-tunggu_dipanggil { background-color: #3AA6B9; }
        .badge-siap_diambil     { background-color: #3B6345; }

        .wt-box { font-weight: 700; font-size: 16px; }
        .wt-ok      { color: #3B6345; }
        .wt-warn    { color: #D97706; }
        .wt-over    { color: #C63D2F; }
        .wt-sub     { font-size: 11px; color: #777; font-weight: 400; display: block; }

        tr.row-over td { background-color: #FFE4E1 !important; }
        tr.row-over td:first-child { border-left: 4px solid #C63D2F; }
        .wt-alert {
            display: inline-block;
            margin-top: 3px;
            padding: 2px 8px;
            border-radius: 10px;
            background-color: #C63D2F;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            animation: blink 1.2s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0.45; }
        }

        .alert-banner {
            background-color: #C63D2F;
            color: #fff;
            font-weight: 700;
            font-size: 13px;
            padding: 6px 14px;
            border-radius: 12px;
            margin: 0 12px 8px;
            text-align: left;
            display: none;
            animation: blink 1.6s infinite;
        }
        .alert-banner.show { display: block; }

        .std-info {
            color: #ffffff;
            font-size: 12px;
            padding: 6px 16px;
            background-color: rgba(0,0,0,0.15);
            border-radius: 12px;
            margin: 4px 12px 10px;
            text-align: left;
        }

        .empty-row td { text-align: center !important; color: #999; font-style: italic; font-weight: 200; }

        .row-no-padding [class*="col-"] { padding-left: 10px; padding-right: 0; }
        .mr-10 { margin-right: 15px !important; }

        .disclaimer {
            background-color: #ffffff;
            border-radius: 17px;
            margin: 0 10px;
            padding: 10px 20px;
            color: #3AA6B9;
            text-align: center;
        }
        .disclaimer .note {
            font-size: 13px;
            color: #555;
            font-weight: 400;
            line-height: 1.4;
        }
        .disclaimer .tagline {
            font-size: 18px;
            font-weight: 900;
            color: #3B6345;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row header">
        <div class="col-xs-12 col-sm-2 col-md-2 col-lg-2">
            <img src="{{ secure_url('images/logo.png') }}" class="logo">
        </div>
        <div class="col-xs-12 col-sm-10 col-md-10 col-lg-10 waktu">
            <span id="hari">&nbsp;</span>
            <span id="jam">&nbsp;</span>
        </div>
    </div>

    <div class="row row-no-padding">
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">
            <div class="container_antrian container_antrian_farmasi">
                <div class="title_antrian_farmasi">Antrian Obat Jadi</div>
                <div class="std-info">Standar waktu tunggu obat jadi: &le; 30 menit (Permenkes 129/2008)</div>
                <div id="alert_jadi" class="alert-banner"></div>
                <table class="table bw table-farmasi">
                    <thead>
                        <tr>
                            <th class="text-center">No</th>
                            <th class="text-center">Nama Pasien</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Waktu Tunggu</th>
                        </tr>
                    </thead>
                    <tbody id="container_antrian_obat_jadi">
                        <tr class="empty-row"><td colspan="4">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">
            <div class="container_antrian mr-10 container_antrian_farmasi">
                <div class="title_antrian_farmasi">Antrian Obat Racikan</div>
                <div class="std-info">Standar waktu tunggu obat racikan: &le; 60 menit (Permenkes 129/2008)</div>
                <div id="alert_racikan" class="alert-banner"></div>
                <table class="table bw table-farmasi">
                    <thead>
                        <tr>
                            <th class="text-center">No</th>
                            <th class="text-center">Nama Pasien</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Waktu Tunggu</th>
                        </tr>
                    </thead>
                    <tbody id="container_antrian_obat_racikan">
                        <tr class="empty-row"><td colspan="4">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row row-no-padding">
        <div class="col-xs-12">
            <div class="disclaimer">
                <div class="note">
                    Waktu tunggu yang ditampilkan merupakan <b>perkiraan</b> dan dapat berubah sewaktu-waktu tergantung situasi dan kondisi pelayanan.
                </div>
                <div class="tagline">Kesabaran Anda, Ketelitian Kami</div>
            </div>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.24.0/moment.min.js"></script>
<script src="{!! secure_url('js/moment.locale.js') !!}"></script>
<script>
    moment.locale('id');
    window.setInterval(function () {
        $('#hari').html(moment().format('dddd, DD MMMM YYYY'));
        $('#jam').html(moment().format('HH:mm:ss'));
    }, 1000);

    var dataUrl = "{{ url('project/antrian_farmasi/data') }}";

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderWaktuTunggu(r) {
        var menit  = Number(r.waktu_tunggu_menit || 0);
        var batas  = Number(r.estimasi_menit || 0);
        var cls    = 'wt-ok';
        var sub    = '';
        var alert  = '';

        if (r.selesai) {
            sub = 'total layanan';
        } else if (menit >= batas) {
            cls    = 'wt-over';
            var lewat = menit - batas;
            sub    = 'melebihi standar ' + batas + ' mnt';
            alert  = '<span class="wt-alert">! MELEBIHI ' + lewat + ' MNT</span>';
        } else if (menit >= Math.round(batas * 0.75)) {
            cls = 'wt-warn';
            sub = 'estimasi selesai ' + Math.max(0, batas - menit) + ' mnt lagi';
        } else {
            sub = 'estimasi selesai ' + Math.max(0, batas - menit) + ' mnt lagi';
        }

        return '<span class="wt-box ' + cls + '">' + menit + ' mnt</span>'
             + '<span class="wt-sub">' + escapeHtml(sub) + '</span>'
             + alert;
    }

    function isOverdue(r) {
        return !r.selesai && Number(r.waktu_tunggu_menit || 0) >= Number(r.estimasi_menit || 0);
    }

    function renderRows(rows) {
        if (!rows || rows.length === 0) {
            return '<tr class="empty-row"><td colspan="4">Tidak ada antrian obat saat ini</td></tr>';
        }
        var html = '';
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            var trCls = isOverdue(r) ? ' class="row-over"' : '';
            html += '<tr' + trCls + '>'
                 +   '<td>' + escapeHtml(r.nomor_antrian) + '</td>'
                 +   '<td>' + escapeHtml(r.nama_pasien) + '</td>'
                 +   '<td><span class="badge badge-' + escapeHtml(r.status_kode) + '">'
                 +     escapeHtml(r.status_label)
                 +   '</span></td>'
                 +   '<td>' + renderWaktuTunggu(r) + '</td>'
                 + '</tr>';
        }
        return html;
    }

    function renderAlertBanner($el, rows, label) {
        var over = (rows || []).filter(isOverdue);
        if (over.length === 0) {
            $el.removeClass('show').html('');
            return;
        }
        var nomor = over.map(function (r) { return r.nomor_antrian; }).join(', ');
        $el.addClass('show').html(
            '&#9888; ' + over.length + ' antrian ' + label + ' melebihi standar: ' + escapeHtml(nomor)
        );
    }

    function refresh() {
        $.ajax({ url: dataUrl, cache: false, dataType: 'json' })
            .done(function (data) {
                $('#container_antrian_obat_jadi').html(renderRows(data.jadi));
                $('#container_antrian_obat_racikan').html(renderRows(data.racikan));
                renderAlertBanner($('#alert_jadi'), data.jadi, 'obat jadi');
                renderAlertBanner($('#alert_racikan'), data.racikan, 'obat racikan');
            });
    }

    $(function () {
        refresh();
        setInterval(refresh, 5000);
    });
</script>
</body>
</html>
