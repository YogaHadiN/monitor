var interval;
channel.bind(event_name, function (data) {
    $.get(
        base + "/antrianperiksa/monitor/getDataBaru",
        {},
        function (data, textStatus, jqXHR) {
            $("#jumlah_jenis_antrian_id_1").html(
                data.antrian_dokter_umum.length
            );
            $("#jumlah_jenis_antrian_id_2").html(
                data.antrian_dokter_gigi.length
            );
            $("#jumlah_jenis_antrian_id_3").html(data.antrian_bidan.length);

            var waktu_tunggu_dokter_umum = waktuTunggu(
                data.antrian_dokter_umum,
                1
            );
            $("#waktu_tunggu_jenis_antrian_id_1").html(
                waktu_tunggu_dokter_umum
            );

            var waktu_tunggu_dokter_gigi = waktuTunggu(
                data.antrian_dokter_gigi,
                2
            );
            $("#waktu_tunggu_jenis_antrian_id_2").html(
                waktu_tunggu_dokter_gigi
            );
            var waktu_tunggu_bidan = waktuTunggu(data.antrian_bidan, 3);
            $("#waktu_tunggu_jenis_antrian_id_3").html(waktu_tunggu_bidan);
        }
    );
});
function waktuTunggu(antrian, jenis_antrian) {
    if (antrian.length == 0) {
        return "3 - 15 menit";
    } else if (antrian.length > 0 && jenis_antrian == 1) {
        return (
            String(antrian.length * 3) +
            " - " +
            String(antrian.length * 6) +
            " menit"
        );
    } else {
        return " - ";
    }
}
clearAndFocus();
var nomor_bpjs = null;
var nomor_ktp = null;
function pilihJenisAntrian(control) {
    clearAndFocus();
    $("#jenis_antrian_id").val(control);
    $("#registrasiPembayaran").modal({
        backdrop: "static",
        keyboard: false,
    });
}
$("#backspace").hide();
function submitAntrian(
    jenis_antrian_id,
    no_telp,
    pasien_id,
    registrasi_pembayaran_id
) {
    $(".modal").modal("hide");
    var ajax_url = base + "/fasilitas/antrian_pasien/ajax/" + jenis_antrian_id;
    $(":button").prop("disabled", true);
    $.get(
        ajax_url,
        {
            nomor_bpjs: nomor_bpjs,
            pasien_id: pasien_id,
            no_telp: no_telp,
            registrasi_pembayaran_id: registrasi_pembayaran_id,
        },
        function (data, textStatus, jqXHR) {
            $("#noWhatsapp").modal("hide");

            $("#nomor_antrian").html("");
            $("#jenis_antrian").html("");
            $("#kode_unik").html("");
            $("#qr_code").attr("src", "");
            $("#timestamp").html("");

            var nomor_antrian = data["nomor_antrian"];
            var jenis_antrian = data["jenis_antrian"];
            var kode_unik = data["kode_unik"];
            var qr_code = data["qr_code"];
            var timestamp = data["timestamp"];

            $("#nomor_antrian").html(nomor_antrian);
            $("#jenis_antrian").html(jenis_antrian);
            $("#kode_unik").html(kode_unik);
            $("#qr_code").attr("src", qr_code);
            $("#timestamp").html(timestamp);

            if (nomor_antrian !== null) {
                var info_text =
                    $.trim(no_telp).length == 0
                        ? jenis_antrian
                        : jenis_antrian +
                          " <br />" +
                          " <br />" +
                          " Mohon periksa pesan di whatsapp anda";
                window.print();

                Swal.fire({
                    icon: "success",
                    title: nomor_antrian,
                    html: info_text,
                    showConfirmButton: false,
                    // timer: 2500,
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "404",
                    html: "Nomor Antrian tidak ditemukan",
                    showConfirmButton: false,
                    // timer: 2500,
                });
            }
            $(":button").prop("disabled", false);
            clearAndFocus();
        }
    ).fail(function (xhr) {
        showNotificationWhenError(xhr);
    });
}
function returnFocus() {
    $("#nomor_bpjs").focus();
}
function waBtn(control) {
    var number = $(control).html();
    var existingNumber = $("#no_wa").html();
    var newNumber = existingNumber + number;
    $("#no_wa").html(newNumber);
    toggleBackspace(newNumber);
}
$("#nomor_bpjs").keyup(function (event) {
    var keycode = event.keyCode || event.which;
    if (keycode == "13") {
        nomor_bpjs = $("#nomor_bpjs").val();
        submitAntrian("1");
        nomor_bpjs = null;
    }
});
function backspace(control) {
    var existringNumber = $("#no_wa").html();
    var newNumber = existringNumber.slice(0, -1);
    $("#no_wa").html(newNumber);
    toggleBackspace(newNumber);
}
function toggleBackspace(newNumber) {
    newNumber = $.trim(newNumber);
    if (newNumber.length) {
        $("#backspace").show();
    } else {
        $("#backspace").hide();
    }
}
function lanjutkan(control) {
    no_wa = $.trim($("#no_wa").html());
    if (no_wa.length > 9) {
        var jenis_antrian_id = $("#jenis_antrian_id").val();
        $("#backspace").hide();
        $("#noWhatsapp").modal("hide");
        $("#no_telp").val(no_wa);
        submitAntrian(
            $("#jenis_antrian_id").val(),
            $("#no_telp").val(),
            null,
            $("#registrasi_pembayaran_id").val()
        );
        // $.get(
        //     base + "/fasilitas/antrian/pilihanPasien",
        //     { no_telp: no_wa },
        //     function (data, textStatus, jqXHR) {
        //         $("#jumlahPilihanPasien").val(data.length);
        //         var temp = "";
        //         var pre =
        //             '<div class="row"> <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 m-10">';
        //         var post = "</div></div>";
        //         if (data.length) {
        //             for (let i = 0, len = data.length; i < len; i++) {
        //                 temp += pre;
        //                 temp +=
        //                     '<button class="btn btn-primary btn-whatsapp btn-block" onclick="pilihPasien(' +
        //                     data[i].pasien_id +
        //                     ',this);return false">' +
        //                     data[i].nama +
        //                     " (" +
        //                     data[i].usia +
        //                     ")" +
        //                     "</button>";
        //                 temp += post;
        //             }
        //             temp += pre;
        //             temp +=
        //                 '<button class="btn btn-danger btn-whatsapp btn-block" onclick="pilihPasienLainnya(this);return false">Lainnya</button>"';
        //             temp += post;
        //             $("#pilihanPasienContainer").html(temp);
        //             $("#pilihPasienModal").modal({
        //                 backdrop: "static",
        //                 keyboard: false,
        //             });
        //         } else {
        //             submitAntrian(
        //                 $("#jenis_antrian_id").val(),
        //                 $("#no_telp").val(),
        //                 null,
        //                 $("#registrasi_pembayaran_id").val()
        //             );
        //         }
        //     }
        // );
        // submitAntrian(jenis_antrian_id, no_wa);
    } else {
        Swal.fire({
            icon: "error",
            text: "Format Nomor handphone salah",
            showConfirmButton: false,
            // timer: 2500,
        });
    }
}

function lewati(control) {
    var jenis_antrian_id = $("#jenis_antrian_id").val();
    var no_telp = $("#no_telp").val();
    var pasien_id = $("#pasien_id").val();
    var registrasi_pembayaran_id = $("#registrasi_pembayaran_id").val();

    $("#backspace").hide();

    submitAntrian(
        jenis_antrian_id,
        no_telp,
        pasien_id,
        registrasi_pembayaran_id
    );
}
function showNotificationWhenError(xhr) {
    if (xhr.status === 0) {
        clearAndFocus();
        alert("Not connect.\n Verify Network.");
    } else if (xhr.status == 404) {
        clearAndFocus();
        alert("Requested page not found. [404]");
    } else if (xhr.status == 500) {
        clearAndFocus();
        alert("Internal Server Error [500].");
    } else {
        clearAndFocus();
        alert("Uncaught Error.\n" + xhr.responseText);
    }
}
function registrasiPembayaran(registrasi_pembayaran_id, control) {
    $("#registrasi_pembayaran_id").val(registrasi_pembayaran_id);
    $("#registrasiPembayaran").modal("hide");
    $("#noWhatsapp").modal({ backdrop: "static", keyboard: false });
}
function pilihPasien(pasien_id, control) {
    $("#pasien_id").val(pasien_id);
    $("#pilihPasienModal").modal("hide");
    var jenis_antrian_id = $("#jenis_antrian_id").val();
    var no_telp = $("#no_telp").val();
    var registrasi_pembayaran_id = $("#registrasi_pembayaran_id").val();

    submitAntrian(
        jenis_antrian_id,
        no_telp,
        pasien_id,
        registrasi_pembayaran_id
    );
}
function pilihPasienLainnya(control) {
    $("#pilihPasienModal").modal("hide");
    var jenis_antrian_id = $("#jenis_antrian_id").val();
    var no_telp = $("#no_telp").val();
    var registrasi_pembayaran_id = $("#registrasi_pembayaran_id").val();
    submitAntrian(jenis_antrian_id, no_telp, null, registrasi_pembayaran_id);
}
function clearAndFocus() {
    $("#no_telp").val("");
    $("#no_wa").html("");
    $("#registrasi_pembayaran_id").val("");
    $("#pasien_id").val("");
    $("#jumlahPilihanPasien").val("");
    $("#jenis_antrian_id").val("");
    $("#nomor_bpjs").val("");
    $("#nomor_bpjs").focus();
}
function ulang(control) {
    $(".modal").modal("hide");
    clearAndFocus();
}
function isNumber(num) {
    return !isNaN(parseFloat(num)) && isFinite(num);
}
