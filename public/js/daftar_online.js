function button_submit() {
    var no_telp = $("#no_telp").val();
    $(location).prop("href", base + "/daftar_online_by_phone/" + no_telp);
}
