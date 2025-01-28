<div class="text-center">
    <h2>Alamat Lengkap</h2>
</div>
<textarea type="text" value="" name="alamat" onkeyup="alamat_keyup(this);return false;" class="form-control textareacustom" id="alamat"/>
<br>
<button id="submit_alamat_button" class="btn btn-info btn-lg btn-block" onclick="alamat();return false;">
    Submit
</button>

<script charset="utf-8">
    $("#alamat").focus();
    $("#submit_alamat_button").hide();
</script>

