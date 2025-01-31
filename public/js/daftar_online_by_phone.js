view();
function view(message = null, web_registration = null) {
    $.get(
        base + "/daftar_online_by_phone/view/refresh",
        {
            no_telp: $("#no_telp").val(),
        },
        function (data, textStatus, jqXHR) {
            $("#container").html(data);
            $("#message").html(message);
        }
    );
}

function submit(control, route_parameter) {
    if ($(control).hasClass("not_a_value")) {
        var value = $(".value:first").val();
    } else {
        var value = $(control).val();
    }

    $(control).html(
        '<span class="glyphicon glyphicon-refresh spinning"></span> Mohon Tunggu...'
    );
    $.post(
        base + "/daftar_online_by_phone/submit/" + route_parameter,
        {
            no_telp: $("#no_telp").val(),
            value: value,
        },
        function (data, textStatus, jqXHR) {
            view(data.message, data.web_registration);
        }
    );
}

function nama(control) {
    var nama = $("#nama").val();
    if (nama.length < 3) {
        $("#info_nama").html("Minimal 3 huruf");
        $("#info_nama").attr("class", "alert alert-danger");
    } else if (/\d/.test(nama)) {
        $("#info_nama").html("Nama tidak boleh mengandung angka");
        $("#info_nama").attr("class", "alert alert-danger");
    } else {
        submit(control, "nama");
    }
}
function tanggal_lahir(control) {
    var tanggal_lahir = $("#tanggal_lahir").val();
    if (moment(tanggal_lahir, "DD-MM-YYYY", true).isValid()) {
        submit(control, "tanggal_lahir");
    } else {
        $("#info_tanggal").html("Format tanggal salah. Contoh : 19-07-2003");
        $("#info_tanggal").attr("class", "alert alert-danger");
    }
}
function alamat(control) {
    var alamat = $("#alamat").val();
    if (alamat.length < 3) {
        $("#info_alamat").html("Minimal 3 huruf");
        $("#info_alamat").attr("class", "alert alert-danger");
    } else {
        submit(control, "alamat");
    }
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

function validasiNomorKartuAktif(control) {
    var nomor_asuransi_bpjs = $("#nomor_asuransi_bpjs").val();
    if (nomor_asuransi_bpjs.match(/[^$,.\d]/)) {
        $("#info_nomor_asuransi_bpjs").html("Nomor BPJS harus semuanya angka");
        $("#info_nomor_asuransi_bpjs").attr("class", "alert alert-danger");
    } else if (nomor_asuransi_bpjs.length !== 13) {
        $("#info_nomor_asuransi_bpjs").html("Nomor BPJS harus 13 angka");
        $("#info_nomor_asuransi_bpjs").attr("class", "alert alert-danger");
    } else {
        submit(control, "nomor_asuransi_bpjs");
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

function batalkan() {
    Swal.fire({
        title: "Konfirmasi",
        text: "Anda akan mengahpus semua antrian yang anda buat hari ini",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#5cb85c",
        confirmButtonText: "Hapus Semua",
        cancelButtonText: "Kembali",
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(
                base + "/daftar_online_by_phone/submit/batalkan",
                {
                    no_telp: $("#no_telp").val(),
                },
                function (data, textStatus, jqXHR) {
                    view(data.message);
                }
            );
        }
    });
}

function daftar_lagi() {
    $.post(
        base + "/daftar_online_by_phone/submit/daftar_lagi",
        {
            no_telp: $("#no_telp").val(),
        },
        function (data, textStatus, jqXHR) {
            view(data.message);
        }
    );
}
function hapusAntrian(antrian_id, control) {
    var nomor_antrian = $(control)
        .closest(".alert-info")
        .find(".nomor_antrian")
        .html();
    Swal.fire({
        title: "Konfirmasi",
        text: "Anda akan menghapus antrian " + nomor_antrian,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#5cb85c",
        confirmButtonText: "Hapus Antrian Ini",
        cancelButtonText: "Kembali",
    }).then((result) => {
        if (result.isConfirmed) {
            $(control).html(
                '<span class="glyphicon glyphicon-refresh spinning"></span> Mohon Tunggu...'
            );
            $.post(
                base + "/daftar_online_by_phone/submit/hapus_antrian",
                {
                    no_telp: $("#no_telp").val(),
                    antrian_id: antrian_id,
                },
                function (data, textStatus, jqXHR) {
                    view(data.message, data.web_registration);
                }
            );
        }
    });
}
