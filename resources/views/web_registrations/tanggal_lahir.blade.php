<div class="text-center">
    <h2>Tanggal Lahir</h2>
</div>

<div class="alert alert-info" id="info_tanggal">
    Contoh : 19-07-2003
</div>

<input onkeypress="return tanggal_lahir_keypress(event);" placeholder="Klik disini untuk mulai mengisi..." onkeyup="tanggal_lahir_keyup(this);return false;" type="text" value="" name="tanggal_lahir" class="form-control" id="tanggal_lahir"/>
<br>
<button id="submit_tanggal_lahir_button" class="btn btn-info btn-lg btn-block" onclick="tanggal_lahir();return false;">
    Submit
</button>
<button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi();return false;">
    Ulangi
</button>
<script charset="utf-8">
    $("#tanggal_lahir").focus();
</script>

