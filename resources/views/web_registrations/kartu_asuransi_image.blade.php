<div class="text-center">
    <h2>Upload Kartu BPJS</h2>
    <p>Foto kartu BPJS Anda. Boleh dilewati jika tidak punya foto saat ini.</p>
</div>

<div id="info_kartu_asuransi" class="alert alert-info">
    Pilih foto (.jpg / .jpeg / .png) lalu klik <strong>Upload & Lanjutkan</strong>.
</div>

<input type="file"
       id="kartu_asuransi_file"
       name="file"
       accept="image/*"
       class="form-control"
       style="margin-bottom: 10px;">

<button class="btn btn-info btn-lg btn-block" onclick="uploadKartuAsuransi(this);return false;">
    Upload & Lanjutkan
</button>
<button class="btn btn-default btn-lg btn-block" onclick="skipKartuAsuransi(this);return false;">
    Lewati (Tidak Upload)
</button>
<button class="btn btn-lg btn-danger btn-block ulangi" onclick="ulangi(this);return false;">
    Ulangi
</button>
