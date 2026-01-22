var interval;
console.log(new Date().toLocaleString());
$("#text_notifikasi").fadeOut();
fadeContent();
function fadeContent() {
    $("#text_notifikasi")
        .fadeIn(1000)
        .delay(500)
        .fadeOut(1000, function () {
            $(this).appendTo($(this).parent());
            fadeContent();
        });
}

var timer = 0;

if (menangani_gawat_darurat) {
    startEmergencyNotification();
} else {
    clearInterval(timer);
}

channel.bind(event_name, function (data) {
    console.log(data);
    if (
        typeof data.panggil !== "undefined" &&
        typeof data.ruangan !== "undefined"
    ) {
        if (!isNumber(data.panggil)) {
            if (data.panggil) {
                var panggil_pasien = 1;
            } else {
                var panggil_pasien = 0;
            }
            var ruangan = data.ruangan;
            var antrian_id = data.ruangan;
            $.get(
                base +
                    "/antrianperiksa/monitor/getDataBaru/" +
                    antrian_id +
                    "/" +
                    panggil_pasien,
                {
                    ruangan: ruangan,
                },
                function (data, textStatus, jqXHR) {
                    if (panggil_pasien == 1) {
                        if (data.antrian_dipanggil.nomor_antrian !== null) {
                            var nomor_panggilan =
                                data.antrian_dipanggil.nomor_antrian;
                            var ruangan_panggilan =
                                data.antrian_dipanggil.ruangan;
                            $("#poli_panggilan").html(ruangan_panggilan);
                            $("#nomor_panggilan").html(nomor_panggilan);
                        } else {
                            $("#poli_panggilan").html("-");
                            $("#nomor_panggilan").html("-");
                        }
                    }

                    if (panggil_pasien && interval == null) {
                        var times = 0;
                        interval = setInterval(function () {
                            $("#dipanggil").toggleClass("yellow");
                            $("#nomor_panggilan").toggleClass("yellow");
                            $("#poli_panggilan").toggleClass("yellow");
                            $(".text-red").toggleClass("yellow");
                            times++;
                            if (times > 21) {
                                clearInterval(interval);
                                $("#dipanggil").removeClass("yellow");
                                $("#nomor_panggilan").removeClass("yellow");
                                $("#poli_panggilan").removeClass("yellow");
                                $(".text-red").removeClass("yellow");
                                interval = null;
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
                        panggilPasien(ruangan);
                    }

                    console.log(
                        "status_gawat_darurat_saat_ini = " +
                            status_gawat_darurat_saat_ini
                    );
                    console.log(
                        "data.menangani_gawat_darurat = " +
                            data.menangani_gawat_darurat
                    );

                    if (
                        status_gawat_darurat_saat_ini !==
                        data.menangani_gawat_darurat
                    ) {
                        updateNotifikasiDarurat(data);
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
    if (!x) {
        console.warn("Audio base #myAudio tidak ditemukan");
        return;
    }

    // pastikan sound array
    if (!Array.isArray(sound)) {
        console.warn("sound bukan array:", sound);
        return;
    }

    // ambil element audio per token sound (yang tidak ada akan null)
    var m = sound.map(function (s) {
        return document.getElementById("audio_" + s);
    });

    // filter hanya yang benar-benar ada (bukan null)
    m = m.filter(Boolean);

    if (m.length === 0) {
        console.warn("Tidak ada audio_* yang ditemukan untuk:", sound);
        return;
    }

    // putus rantai onended lama biar gak numpuk
    x.onended = null;
    m.forEach(function (el) {
        el.onended = null;
    });

    // chain: myAudio -> m[0] -> m[1] -> ...
    x.onended = function () {
        m[0].play();
    };

    for (let i = 0; i < m.length - 1; i++) {
        m[i].onended = function () {
            m[i + 1].play();
        };
    }

    // mulai
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
            console.log("========================");
            console.log("data sound panggilan");
            console.log(data);
            console.log("========================");
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
function updateNotifikasiDarurat(data) {
    menangani_gawat_darurat = data.menangani_gawat_darurat;
    if (!menangani_gawat_darurat) {
        clearInterval(timer);
        if ($("#activate_if_not_danger").hasClass("hide")) {
            $("#activate_if_not_danger").removeClass("hide");
        }
        if (!$("#activate_if_danger").hasClass("hide")) {
            $("#activate_if_danger").addClass("hide");
        }
        status_gawat_darurat_saat_ini = menangani_gawat_darurat;
    } else {
        startEmergencyNotification();
        if (!$("#activate_if_not_danger").hasClass("hide")) {
            $("#activate_if_not_danger").addClass("hide");
        }
        if ($("#activate_if_danger").hasClass("hide")) {
            $("#activate_if_danger").removeClass("hide");
        }
    }
}

function startEmergencyNotification() {
    timer = setInterval(function () {
        var menunggu = document.getElementById("audio_menunggu");
        var ding = document.getElementById("ding");
        ding.onended = function () {
            menunggu.play();
        };
        ding.play();
        var pesan = "mainkan ";
        pesan += new Date().toLocaleString();
    }, 300000);
    status_gawat_darurat_saat_ini = menangani_gawat_darurat;
}
