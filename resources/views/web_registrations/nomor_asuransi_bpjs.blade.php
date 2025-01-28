<div class="text-center">
    <h2>Nomor Asuransi BPJS</h2>
</div>
<input type="text" value="" onpaste="validasiBpjs(this);return false;" onkeyup="validasiBpjs(this);return false;" name="nomor_asuransi_bpjs" class="form-control" id="nomor_asuransi_bpjs"/>
<br>
<div class="alert alert-info" id="info_nomor_asuransi_bpjs">
    Nomor BPJS terdiri dari 13 angka
</div>
<button class="btn btn-info btn-lg btn-block" id="submit_nomor_asuransi_bpjs_button" onclick="nomor_asuransi_bpjs();return false;">
    Submit
</button>

<script charset="utf-8">
    $("#nomor_asuransi_bpjs").attr('maxlength','13');
    $("#nomor_asuransi_bpjs").focus();
    $('#submit_nomor_asuransi_bpjs_button').hide();
</script>

