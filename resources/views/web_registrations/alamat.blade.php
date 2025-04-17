<div class="text-center">
    <h2>Alamat Lengkap</h2>
</div>
<div id="info_alamat">

</div>
<textarea placeholder="Klik disini untuk mulai mengisi..." type="text" name="alamat" onkeyup="alamat_keyup(this);return false;" class="form-control textareacustom value" id="alamat"/>
<br>
<button id="submit_alamat_button" class="btn btn-info btn-lg btn-block not_a_value" onclick="alamat(this);return false;">
    Submit
</button>
<button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi(this);return false;">
    Ulangi
</button>
<script charset="utf-8">
    $("#alamat").focus();
</script>

