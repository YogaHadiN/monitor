@php /** @var \App\Models\ReservasiOnline $schedulled_reservation */ @endphp
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>QR Reservasi #{{ $schedulled_reservation->id }}</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f6f8fb}
    .card{border:none;border-radius:1rem;box-shadow:0 10px 30px rgba(0,0,0,.06)}
    .qr-wrap{background:#fff;border-radius:1rem;padding:16px}
    img.qr{display:block;margin:0 auto;max-width:100%;height:auto;image-rendering:pixelated}
    .mono{font-family:ui-monospace,Menlo,Consolas,monospace}
  </style>
</head>
<body class="py-4">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-6">
      <div class="card p-4">
        <div class="mb-3">
          <h1 class="h4 mb-1">QR Reservasi Online</h1>
          <div class="text-muted">Scan QR berikut saat anda tiba di klinik</div>
        </div>

        <div class="mb-3">
            <div><strong>Reservasi Id:</strong> {{ $schedulled_reservation->id }}</div>
          <div><strong>Pasien:</strong> {{ $pasienNama }}</div>
          <div><strong>Dokter:</strong> {{ $dokterNama }}</div>
          <div><strong>Jam Mulai:</strong> {{ $jamMulaiStr }} WIB</div>
          <div class="small text-muted mt-1">Pastikan kecerahan layar cukup agar mudah dipindai.</div>
          <div class="small text-muted mt-1"> <strong><i>Mohon scan qr di klinik sebelum jam {{ $jam_schedulled_reservation_dihapus }}. Agar reservasi ini tidak terhapus secara otomatis</i></strong></div>
        </div>

        <div class="qr-wrap mb-3 text-center">
          {{-- Jika pakai disk publik (URL langsung) --}}
          <img class="qr" src="{{ $qrUrl }}" alt="QR Reservasi #{{ $schedulled_reservation->id }}">

          {{-- Jika disk private, gunakan ini (hapus yang atas):
          <img class="qr" src="{{ route('schedulled_reservations.qr.image', $schedulled_reservation) }}" alt="QR Reservasi #{{ $schedulled_reservation->id }}">
          --}}
        </div>

        <div class="d-flex gap-2">
          <a class="btn btn-outline-secondary w-100" href="{{ $qrUrl }}" download>Unduh</a>
        <button
          type="button"
          id="btn-cancel"
          class="btn btn-danger w-100"
          data-cancel-url="{{ route('reservations.destroy', $schedulled_reservation->id) }}">
          Batalkan Reservasi
        </button>
        </div>

        <div class="mt-3 small text-center text-muted">
          Jika QR tidak muncul, coba refresh halaman ini.
        </div>
      </div>
    </div>
  </div>
</div>
</body>
    <script src="{{ url('js/schedulled_reservation_qr.js') }}"></script>
</html>
