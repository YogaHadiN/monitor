var interval = null;
var times = null;
channel.bind(event_name, function (data) {
    if (
        typeof data.panggil !== "undefined" &&
        typeof data.ruangan !== "undefined"
    ) {
        //untuk antrian monitor
        if (!isNumber(data.panggil)) {
            if (data.panggil) {
                var panggil_pasien = 1;
            } else {
                var panggil_pasien = 0;
            }
            var ruangan = data.ruangan;
            $.get(
                base + "/antrianperiksa/monitor/getDataBaru/" + panggil_pasien,
                {
                    ruangan: ruangan,
                },
                function (data, textStatus, jqXHR) {
                    if (data.antrian_dipanggil.nomor_antrian !== null) {
                        var nomor_panggilan =
                            data.antrian_dipanggil.nomor_antrian;
                        var ruangan_panggilan = data.antrian_dipanggil.ruangan;
                        $("#poli_panggilan").html(ruangan_panggilan);
                        $("#nomor_panggilan").html(nomor_panggilan);
                    }

                    if (panggil_pasien && !interval) {
                        var times = 0;
                        var interval = setInterval(function () {
                            $("#dipanggil").toggleClass("yellow");
                            $("#nomor_panggilan").toggleClass("yellow");
                            $("#poli_panggilan").toggleClass("yellow");
                            $(".text-red").toggleClass("yellow");
                            times++;
                            if (times > 14) {
                                clearInterval(interval);
                                $("#dipanggil").removeClass("yellow");
                                $("#nomor_panggilan").removeClass("yellow");
                                $("#poli_panggilan").removeClass("yellow");
                                $(".text-red").removeClass("yellow");
                            }
                        }, 500);
                    }

                    var temp = "";
                    $("#antrian_ruang_periksa_1").html(
                        data.antrian_terakhir[3]
                    );
                    $("#antrian_ruang_periksa_2").html(
                        data.antrian_terakhir[4]
                    );
                    $("#antrian_ruang_periksa_gigi").html(
                        data.antrian_terakhir[5]
                    );
                    $("#antrian_ruang_periksa_3").html(
                        data.antrian_terakhir[16]
                    );
                    $("#container_antrian_obat_jadi").html(
                        prosesAntrianObat(data.antrian_obat_jadi)
                    );
                    $("#container_antrian_obat_racikan").html(
                        prosesAntrianObat(data.antrian_obat_racikan)
                    );

                    if (
                        typeof ruangan !== "undefined" &&
                        ruangan !== "" &&
                        ruangan !== null
                    ) {
                        console.log("ruangan");
                        console.log(ruangan);
                        panggilPasien(ruangan);
                    }
                }
            );
        }
    }
});

function prosesAntrianObat(data) {
    var temp = "";
    if (data.length) {
        for (var i = 0; i < data.length; i++) {
            if (i < 7) {
                temp += "<tr>";
                temp += "<td>" + data[i].nomor_antrian + "</td>";
                temp +=
                    "<td class='text-left'>" +
                    data[i].nama.substring(0, 21) +
                    "</td>";
                temp += "<td>";
                if (data[i].status == "Proses") {
                    temp += '<span class="badge badge-warning">Proses</span>';
                } else if (data[i].status == "Menunggu") {
                    temp += '<span class="badge badge-danger">Menunggu</span>';
                } else if (data[i].status == "Selesai") {
                    temp += '<span class="badge badge-primary">Selesai</span>';
                }
                temp += "</td>";
                temp += "</tr>";
            }
        }
    } else {
        temp += "<tr>";
        temp += '<td colspan="3" style="text-align:center">';
        temp += "Tidak ada antrian";
        temp += "</td>";
        temp += "</tr>";
    }
    return temp;
}

function refreshElement(id) {
    var el = $(id);
    el.before(el.clone(true)).remove();
}
function clear(panggilan) {
    if (typeof panggilan !== "undefined") {
        $("#nomor_panggilan").html("-");
        $("#poli_panggilan").html("-");
    }
    $("#nomor_poli_umum").html("-");
    $("#jumlah_poli_umum").html("-");
    $("#nomor_poli_gigi").html("-");
    $("#jumlah_poli_gigi").html("-");
    $("#nomor_poli_bidan").html("-");
    $("#jumlah_poli_bidan").html("-");
    $("#nomor_poli_estetik").html("-");
    $("#jumlah_poli_estetik").html("-");
    $("#antrian_terakhir_poli_umum").html("-");
    $("#antrian_terakhir_poli_gigi").html("-");
    $("#antrian_terakhir_poli_bidan").html("-");
    $("#antrian_terakhir_poli_estetik").html("-");
    $("#antrian_terakhir_poli_prolanis").html("-");
    $("#antrian_terakhir_poli_rapid_test").html("-");
    $("#antrian_terakhir_poli_mcu").html("-");
    $("#antrian_terakhir_pendaftaran").html("-");
    $("#antrian_terakhir_timbang_tensi").html("-");
    $("#antrian_poli_1").html("");
    $("#antrian_poli_7").html("");
    $("#antrian_poli_3").html("");
    $("#pendaftaran").html("");
    $("#timbang_tensi").html("");
}
function pglPasien(sound) {
    var x = document.getElementById("myAudio");
    var m = [];
    for (var i = 0, len = sound.length; i < len; i++) {
        m[i] = document.getElementById("audio_" + sound[i]);
    }
    if (typeof m[0] !== "undefined") {
        x.onended = function () {
            m[0].play();
        };
    }

    if (typeof m[1] !== "undefined") {
        m[0].onended = function () {
            m[1].play();
        };
    }
    if (typeof m[2] !== "undefined") {
        m[1].onended = function () {
            m[2].play();
        };
    }
    if (typeof m[3] !== "undefined") {
        m[2].onended = function () {
            m[3].play();
        };
    }
    if (typeof m[4] !== "undefined") {
        m[3].onended = function () {
            m[4].play();
        };
    }
    if (typeof m[5] !== "undefined") {
        m[4].onended = function () {
            m[5].play();
        };
    }
    if (typeof m[6] !== "undefined") {
        m[5].onended = function () {
            m[6].play();
        };
    }
    if (typeof m[7] !== "undefined") {
        m[6].onended = function () {
            m[7].play();
        };
    }
    if (typeof m[8] !== "undefined") {
        m[7].onended = function () {
            m[8].play();
        };
    }
    if (typeof m[9] !== "undefined") {
        m[8].onended = function () {
            m[9].play();
        };
    }
    if (typeof m[10] !== "undefined") {
        m[9].onended = function () {
            m[10].play();
        };
    }
    if (typeof m[11] !== "undefined") {
        m[10].onended = function () {
            m[11].play();
        };
    }
    if (typeof m[12] !== "undefined") {
        m[11].onended = function () {
            m[12].play();
        };
    }
    x.play();
}
function panggilPasien(ruangan) {
    $.get(
        base + "/antrianperiksa/monitor/convert_sound_to_array",
        {
            nomor_antrian: $("#nomor_panggilan").html(),
            ruangan: ruangan,
        },
        function (data, textStatus, jqXHR) {
            pglPasien(data);
        }
    );
}
function displayRuangan(ruangan) {
    if (ruangan == "ruangperiksasatu") {
        return "Ruang Periksa 1";
    } else if (ruangan == "ruangperiksadua") {
        return "Ruang Periksa 2";
    } else if (ruangan == "loketsatu") {
        return "Loket Satu";
    } else if (ruangan == "loketdua") {
        return "Loket Dua";
    } else if (ruangan == "ruangperiksagigi") {
        return "Ruang Periksa Gigi";
    } else if (ruangan == "ruangpf") {
        return "Ruang Pemeriksaan Fisik";
    }
}

function isNumber(num) {
    return !isNaN(parseFloat(num)) && isFinite(num);
}
function startInterval(func, time) {
    return setInterval(func, time);
}

function stopInterval(interval) {
    console.log("interval");
    console.log(interval);
    clearInterval(interval);
    $("#dipanggil").removeClass("yellow");
    $("#nomor_panggilan").removeClass("yellow");
    $("#poli_panggilan").removeClass("yellow");
    $(".text-red").removeClass("yellow");
}
function blinking() {
    $("#dipanggil").toggleClass("yellow");
    $("#nomor_panggilan").toggleClass("yellow");
    $("#poli_panggilan").toggleClass("yellow");
    $(".text-red").toggleClass("yellow");
}
