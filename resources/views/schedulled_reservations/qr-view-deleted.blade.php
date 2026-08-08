<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reservasi #{{ $schedulled_reservation->id }} — Terhapus</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f6f8fb; }
    .card { border: none; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
    .icon-big { font-size: 72px; line-height: 1; }
  </style>
</head>
<body class="py-4">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-6">
      <div class="card p-4">
        <div class="text-center mb-3">
          <div class="icon-big">❌</div>
          <h1 class="h4 mt-3 mb-1">Reservasi Sudah Terhapus</h1>
          <div class="text-muted">Antrian terjadwal Anda tidak lagi aktif</div>
        </div>

        <hr>

        <div class="mb-3">
          <div><strong>Reservasi ID :</strong> #{{ $schedulled_reservation->id }}</div>
          <div><strong>Pasien       :</strong> {{ $pasienNama }}</div>
          <div><strong>Dokter       :</strong> {{ $dokterNama }}</div>
        </div>

        <div class="alert alert-warning">
          <div class="mb-2">
            <strong>Dihapus pada:</strong>
            {{ \Carbon\Carbon::parse($deletedAt)->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB
          </div>
          <div>
            <strong>Dihapus oleh:</strong> {{ $deletedByLabel }}
          </div>
        </div>

        @if($isSystem)
          <div class="alert alert-info">
            Reservasi Anda otomatis dihapus oleh sistem karena scan QR dilakukan lewat dari batas waktu
            (harusnya <strong>minimal 15 menit sebelum</strong> jam praktik dokter).
          </div>
        @elseif($isSelfCancel)
          <div class="alert alert-info">
            Reservasi ini Anda batalkan sendiri sebelumnya.
          </div>
        @endif

        <div class="alert alert-primary mb-0">
          <strong>Silakan daftar manual</strong> di petugas administrasi klinik untuk mendapatkan antrian baru.
          Terima kasih 🙏
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
