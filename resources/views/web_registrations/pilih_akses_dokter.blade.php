<div class="text-center">
    <h2>Pilih Cara Akses Dokter</h2>
    <p style="color:#666;font-size:14px;">Pilih salah satu:</p>
</div>

<button class="btn btn-success btn-lg btn-block"
        value="tercepat"
        onclick="submit(this, 'pilih_akses_dokter');return false;">
    <span class="glyphicon glyphicon-time"></span>
    Antrian Tercepat <small>(Rekomendasi)</small>
</button>

<button class="btn btn-warning btn-lg btn-block"
        value="pilih_dokter"
        onclick="confirmPilihDokter(this);return false;">
    <span class="glyphicon glyphicon-user"></span>
    Pilih Dokter Tertentu
</button>

<button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi(this);return false;">
    Ulangi
</button>

<script>
    function confirmPilihDokter(control) {
        var msg = "PERINGATAN\n\n" +
            "Dengan memilih dokter tertentu:\n" +
            "- Antrian Anda bisa menjadi lebih lama.\n" +
            "- Antrian Anda bisa dilewati oleh antrian lain yang " +
            "mengambil antrian lebih lambat tetapi tidak memilih dokter.\n\n" +
            "Lanjutkan?";
        if (confirm(msg)) {
            submit(control, 'pilih_akses_dokter');
        }
    }
</script>
