<div class="text-center">
    <h2>Nomor Asuransi BPJS</h2>
</div>
<input type="text" value="" oninput="nomor_asuransi_bpjs_oninput(this);return false;" onkeyup="validasiBpjs(this);return false;" name="nomor_asuransi_bpjs" class="form-control" id="nomor_asuransi_bpjs"/>
<br>
<div class="alert alert-info" id="info_nomor_asuransi_bpjs">
    Nomor BPJS terdiri dari 13 angka
</div>
<button class="btn btn-info btn-lg btn-block" id="submit_nomor_asuransi_bpjs_button" onclick="validasiNomorKartuAktif();return false;">
    Submit
</button>
<button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi();return false;">
    Ulangi
</button>
<script charset="utf-8">
    $("#nomor_asuransi_bpjs").attr('maxlength','13');
    $("#nomor_asuransi_bpjs").focus();
</script>

