view();
function view(message = null, web_registration = null) {
    $.get(
        base + "/daftar_online_by_phone/view/refresh",
        {
            no_telp: $("#no_telp").val(),
        },
        function (data, textStatus, jqXHR) {
            $("#container").html(data);
            console.log("message");
            console.log(message);
            $("#message").html(message);
            console.log("web_registration");
            console.log(web_registration);
            $("#web_registration").html(web_registration);
        }
    );
}

function dokter_umum() {
    submitTipeKonsultasi(1);
}
function dokter_gigi() {
    submitTipeKonsultasi(2);
}
function bidan() {
    submitTipeKonsultasi(3);
}

function submitTipeKonsultasi(tipe_konsultasi_id) {
    $.post(
        base + "/daftar_online_by_phone/submit/tipe_konsultasi",
        {
            no_telp: $("#no_telp").val(),
            tipe_konsultasi_id: tipe_konsultasi_id,
        },
        function (data, textStatus, jqXHR) {
            view(data.message, data.web_registration);
        }
    );
}

function biaya_pribadi() {
    submitPembayaran(1);
}
function bpjs() {
    submitPembayaran(2);
}
function lainnya() {
    submitPembayaran(3);
}

function submitPembayaran(registrasi_pembayaran_id) {
    $.post(
        base + "/daftar_online_by_phone/submit/pembayaran",
        {
            no_telp: $("#no_telp").val(),
            registrasi_pembayaran_id: registrasi_pembayaran_id,
        },
        function (data, textStatus, jqXHR) {
            view(data.message, data.web_registration);
        }
    );
}
function nomor_asuransi_bpjs_submit() {
    $.post(
        base + "/daftar_online_by_phone/submit/nomor_asuransi_bpjs",
        {
            no_telp: $("#no_telp").val(),
            nomor_asuransi_bpjs: $("#nomor_asuransi_bpjs").val(),
        },
        function (data, textStatus, jqXHR) {
            view(data.message, data.web_registration);
        }
    );
}

function nama() {
    $.post(
        base + "/daftar_online_by_phone/submit/nama",
        {
            no_telp: $("#no_telp").val(),
            nama: $("#nama").val(),
        },
        function (data, textStatus, jqXHR) {
            view(data.message, data.web_registration);
        }
    );
}
function tanggal_lahir() {
    $.post(
        base + "/daftar_online_by_phone/submit/tanggal_lahir",
        {
            no_telp: $("#no_telp").val(),
            tanggal_lahir: $("#tanggal_lahir").val(),
        },
        function (data, textStatus, jqXHR) {
            view(data.message, data.web_registration);
        }
    );
}
function alamat() {
    $.post(
        base + "/daftar_online_by_phone/submit/alamat",
        {
            no_telp: $("#no_telp").val(),
            alamat: $("#alamat").val(),
        },
        function (data, textStatus, jqXHR) {
            view(data.message, data.web_registration);
        }
    );
}
function staf(petugas_pemeriksa_id) {
    $.post(
        base + "/daftar_online_by_phone/submit/staf",
        {
            no_telp: $("#no_telp").val(),
            petugas_pemeriksa_id: petugas_pemeriksa_id,
        },
        function (data, textStatus, jqXHR) {
            view(data.message, data.web_registration);
        }
    );
}
function pilihPasien(pasien_id = null) {
    $.post(
        base + "/daftar_online_by_phone/submit/pasien",
        {
            no_telp: $("#no_telp").val(),
            pasien_id: pasien_id,
        },
        function (data, textStatus, jqXHR) {
            view(data.message, data.web_registration);
        }
    );
}
function lanjutkan() {
    $("#lanjutkan").html(
        '<span class="glyphicon glyphicon-refresh spinning"></span> Mohon Tunggu...'
    );
    $.post(
        base + "/daftar_online_by_phone/submit/lanjutkan",
        {
            no_telp: $("#no_telp").val(),
        },
        function (data, textStatus, jqXHR) {
            view(data.message, data.web_registration);
        }
    );
}
function ulangi() {
    $.post(
        base + "/daftar_online_by_phone/submit/ulangi",
        {
            no_telp: $("#no_telp").val(),
        },
        function (data, textStatus, jqXHR) {
            view(data.message, data.web_registration);
        }
    );
}
function validasiBpjs(control) {
    var nomor_asuransi_bpjs = $(control).val();
    if (/\D/g.test(control.value)) {
        // Filter non-digits from input value.
        control.value = control.value.replace(/\D/g, "");
    }
    var length = $(control).val().length;
    if (length < 1) {
        $("#info_nomor_asuransi_bpjs").html("Nomor BPJS harus 13 angka");
        $("#info_nomor_asuransi_bpjs").attr("class", "alert alert-info");
    } else if (length < 13) {
        var sisa = 13 - length;
        $("#info_nomor_asuransi_bpjs").html("sisa " + sisa + " angka lagi");
        $("#info_nomor_asuransi_bpjs").attr("class", "alert alert-info");
    } else if (length == 13) {
        $("#info_nomor_asuransi_bpjs").html("Format sesuai");
        $("#info_nomor_asuransi_bpjs").attr("class", "alert alert-success");
        $("#submit_nomor_asuransi_bpjs_button").show();
    }
}

function validasiNomorKartuAktif() {
    var nomor_asuransi_bpjs = $("#nomor_asuransi_bpjs").val();
    if (nomor_asuransi_bpjs.match(/[^$,.\d]/)) {
        $("#info_nomor_asuransi_bpjs").html("Nomor BPJS harus semuanya angka");
        $("#info_nomor_asuransi_bpjs").attr("class", "alert alert-danger");
    } else if (nomor_asuransi_bpjs.length !== 13) {
        $("#info_nomor_asuransi_bpjs").html("Nomor BPJS harus 13 angka");
        $("#info_nomor_asuransi_bpjs").attr("class", "alert alert-danger");
    } else {
        $("#submit_nomor_asuransi_bpjs_button").html(
            '<span class="glyphicon glyphicon-refresh spinning"></span> Mohon Tunggu...'
        );
        $.post(
            base + "/daftar_online_by_phone/validasi/bpjs",
            { nomor_asuransi_bpjs: $("#nomor_asuransi_bpjs").val() },
            function (data, textStatus, jqXHR) {
                if (data.bisa_digunakan) {
                    nomor_asuransi_bpjs_submit();
                } else {
                    $("#submit_nomor_asuransi_bpjs_button").show();
                    $("#info_nomor_asuransi_bpjs").attr(
                        "class",
                        "alert alert-danger"
                    );
                }
                $("#info_nomor_asuransi_bpjs").html(data.pesan);
                $("#submit_nomor_asuransi_bpjs_button").html("Submit");
            }
        );
    }
}
function namaKeyup(control) {
    if ($(control).val().length > 1) {
        $("#submit_nama_button").show();
    } else {
        $("#submit_nama_button").hide();
    }
}
function preventNumeric(e) {
    var keyCode = e.keyCode ? e.keyCode : e.which;
    if (keyCode > 47 && keyCode < 58) {
        e.preventDefault();
    }
}
function tanggal_lahir_keypress(evt) {
    var charCode = evt.which ? evt.which : event.keyCode;
    if (
        charCode != 46 &&
        charCode != 45 &&
        charCode > 31 &&
        (charCode < 48 || charCode > 57)
    )
        return false;

    return true;
}

function tanggal_lahir_keyup(control) {
    console.log("s");
    var tanggal_lahir = $(control).val();
    if (moment(tanggal_lahir, "DD-MM-YYYY", true).isValid()) {
        $("#submit_tanggal_lahir_button").show();
    } else {
        $("#submit_tanggal_lahir_button").hide();
    }
    // var tanggal_lahir = $(control).val();
    // if (
    //     tanggal_lahir.length == 2 &&
    //     tanggal_lahir.charAt(tanggal_lahir.length - 1) !== "-"
    // ) {
    //     tanggal_lahir = tanggal_lahir.toString();
    //     $(control).val(tanggal_lahir + "-");
    //     $("#info_tanggal").html("masukkan 2 angka bulan");
    // } else if (tanggal_lahir.length == 5) {
    //     tanggal_lahir = tanggal_lahir.toString();
    //     $(control).val(tanggal_lahir + "-");
    //     $("#info_tanggal").html("masukkan 4 angka tahun");
    // }
}
function alamat_keyup(control) {
    var alamat = $(control).val();
    if (alamat.length > 1) {
        $("#submit_alamat_button").show();
    } else {
        $("#submit_alamat_button").hide();
    }
}

function nomor_asuransi_bpjs_oninput(control) {
    console.log("pasted");
}
