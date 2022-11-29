<!DOCTYPE html>
<html moznomarginboxes mozdisallowselectionprint>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Validasi Surat Sakit</title>
<!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

<!-- Optional theme -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">

    <style type="text/css" media="screen">
.text-center {
    text-align: center;
}
    </style>
</head>
<body class="text-center">
	<h1>Validasi Surat Keterangan Sakit</h1>
    @if (count($data))
        <div class="alert alert-success" role="alert">
            <h3>Halaman ini menyatakan bahwa benar</h3>
            <h1>{{ ucwords($nama) }}</h1>
            <h3>Mengajukan izin sakit mulai dari tanggal</h3>
            <h1>{{ $tanggal_mulai->format('d M Y') }}</h1>
            <h3>Selama</h3>
            <h1>{{ $hari }} Hari</h1>
            <h3>Di {{ $nama_klinik }}</h3>
            <h3>Saat berobat pada tanggal {{ $tanggal->format('d M Y') }}</h3>
        </div>
        
    @else
        <div class="alert alert-danger" role="alert">
            <h1>Data tidak ditemukan</h1>
            <h3>Silahkan hubungi 0215977529 untuk verifikasi lebih lanjut</h3>
        </div>
    @endif

<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
</body>
</html>
