<div class="text-center">
    <h2>Nomor Asuransi BPJS</h2>
</div>
<input placeholder="Klik disini untuk mulai mengisi..." type="text" value="" onkeyup="validasiBpjs(this);return false;" name="nomor_asuransi_bpjs" class="form-control value" id="nomor_asuransi_bpjs"/>
<br>
<div class="alert alert-info" id="info_nomor_asuransi_bpjs">
    Nomor BPJS terdiri dari 13 angka
</div>
<button class="btn btn-info btn-lg btn-block not_a_value" id="submit_nomor_asuransi_bpjs_button" onclick="validasiNomorKartuAktif(this);return false;">
    Submit
</button>
<button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi(this);return false;">
    Ulangi
</button>
<script charset="utf-8">
    $("#nomor_asuransi_bpjs").attr('maxlength','13');
    $("#nomor_asuransi_bpjs").focus();
</script>

