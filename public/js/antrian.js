var interval;
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
    if (typeof data.panggil !== "undefined") {
        if (!isNumber(data.panggil)) {
            if (data.panggil) {
                var panggil_pasien = 1;
            } else {
                var panggil_pasien = 0;
            }
            var ruangan = data.ruangan;
            var antrian_id = data.antrian_id;
            var seq = data.seq || "";
            $.get(
                base +
                    "/antrianperiksa/monitor/getDataBaru/" +
                    antrian_id +
                    "/" +
                    panggil_pasien,
                { seq: seq },
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

                    console.log("=========================");
                    console.log("ruangan_panggilan");
                    console.log(ruangan_panggilan);
                    console.log("=========================");

                    if (
                        typeof ruangan_panggilan !== "undefined" &&
                        ruangan_panggilan !== "" &&
                        ruangan_panggilan !== null
                    ) {
                        panggilPasien(antrian_id);
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
                    temp +=
                        '<span class="badge badge-primary">Akan dipanggil</span>';
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
// Global audio queue — prevent race kalau 2 dokter Panggil bersamaan
// dari 2 ruangan berbeda. Panggilan berikut antre, play setelah yg
// sebelumnya selesai. Per instruksi dr. Yoga 2026-09-05.
window.__pglQueue = window.__pglQueue || [];
window.__pglBusy  = window.__pglBusy  || false;

function pglPasien(sound) {
    if (!sound || !sound.length) return;

    // Dedupe: kalau panggilan identik sudah di queue / sedang play,
    // skip. Deteksi via join key. Menghindari double-broadcast yg
    // fire event 2x utk antrian sama.
    var key = sound.join("|");
    if (window.__pglLastKey === key && window.__pglBusy) {
        console.log("pglPasien: skip duplicate sound", key);
        return;
    }
    for (var q = 0; q < window.__pglQueue.length; q++) {
        if (window.__pglQueue[q].join("|") === key) {
            console.log("pglPasien: skip already queued", key);
            return;
        }
    }

    window.__pglQueue.push(sound);
    if (!window.__pglBusy) {
        __pglDrain();
    } else {
        console.log("pglPasien: queued (" + window.__pglQueue.length + " waiting)");
    }
}

function __pglDrain() {
    if (window.__pglQueue.length === 0) {
        window.__pglBusy = false;
        window.__pglLastKey = null;
        return;
    }
    window.__pglBusy = true;
    var sound = window.__pglQueue.shift();
    window.__pglLastKey = sound.join("|");
    __pglPlaySound(sound, function () {
        // Selesai — proses queue berikutnya
        __pglDrain();
    });
}

function __pglPlaySound(sound, onDone) {
    var bell = document.getElementById("myAudio");

    // Build src map dari audio element yg sudah ada di blade.
    var srcMap = {};
    for (var i = 0; i < sound.length; i++) {
        var key = sound[i];
        if (srcMap[key] !== undefined) continue;
        var el = document.getElementById("audio_" + key);
        if (!el) {
            srcMap[key] = null;
            continue;
        }
        var srcEl = el.querySelector("source");
        srcMap[key] = srcEl
            ? srcEl.getAttribute("src")
            : el.src || null;
    }

    function playAt(idx) {
        if (idx >= sound.length) {
            // Chain selesai
            if (typeof onDone === "function") onDone();
            return;
        }
        var key = sound[idx];
        var src = srcMap[key];
        if (!src) {
            console.warn("pglPasien: audio_" + key + " tidak ada, skip");
            playAt(idx + 1);
            return;
        }
        var a = new Audio(src);
        a.onended = function () {
            playAt(idx + 1);
        };
        // Error handler — kalau audio fail load, jangan stuck
        a.onerror = function () {
            console.warn("pglPasien: audio " + key + " error, skip");
            playAt(idx + 1);
        };
        var p = a.play();
        if (p && typeof p.catch === "function") {
            p.catch(function (err) {
                console.error("pglPasien play fail:", key, err);
                playAt(idx + 1);
            });
        }
    }

    bell.onended = function () {
        playAt(0);
    };
    // Reset bell posisi ke 0 supaya kalau bell sebelumnya belum finish
    // (edge case) tetap start dari awal.
    try { bell.currentTime = 0; } catch (e) {}
    var bp = bell.play();
    if (bp && typeof bp.catch === "function") {
        bp.catch(function () {
            // Bell fail (autoplay policy) — tetap play sound chain
            playAt(0);
        });
    }
}
function panggilPasien(antrian_id) {
    $.get(
        base + "/antrianperiksa/monitor/convert_sound_to_array/" + antrian_id,
        {},
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
    } else if (ruangan == "ruangperiksatiga") {
        return "Ruang Periksa 3";
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
