<div class="text-center">
    <h2>Nama Lengkap Pasien</h2>
</div>
<div id="info_nama" class="alert alert-info">
    Hanya bisa memasukkan 1 nama pasien saja
</div>
<input placeholder="Klik disini untuk mulai mengisi..." type="text" value="" name="nama" class="form-control value" onkeydown="preventNumeric(event);" id="nama"/>
<br>
<button id="submit_nama_button" class="btn btn-info btn-lg btn-block not_a_value" onclick="nama(this, 'nama');return false;">
    Submit
</button>
<button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi(this);return false;">
    Ulangi
</button>
<script charset="utf-8">
    $("#nama").focus();
</script>

