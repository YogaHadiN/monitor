<div class="text-center">
    <h2>Nama Lengkap Pasien</h2>
</div>
<div class="alert alert-info">
    Hanya bisa memasukkan 1 nama pasien saja
</div>
<input type="text" value="" name="nama" class="form-control" onkeydown="preventNumeric(event);" onkeyup="namaKeyup(this);return false;" id="nama"/>
<br>
<button id="submit_nama_button" class="btn btn-info btn-lg btn-block" onclick="nama();return false;">
    Submit
</button>

<script charset="utf-8">
    $("#nama").focus();
    $("#submit_nama_button").hide();
</script>

