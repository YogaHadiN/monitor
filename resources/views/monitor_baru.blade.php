<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <meta name="description" content="">
  <meta name="author" content="">
  <title>Antrian Pasien</title>
  <!-- Bootstrap core CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css?1" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
<link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
<script src="https://kit.fontawesome.com/888ab79ab3.js" crossorigin="anonymous"></script>
<link href="{!! asset('css/animate.css') !!}" rel="stylesheet">
<link href="{!! asset('css/style.css') !!}" rel="stylesheet">
<style type="text/css" media="all">
.kirim_ke {
    font-size: 45px !important;
    margin: 10px 0;
    font-weight:900;
}
.text-right {
    text-align: right !important;
}
.keterangan_waktu_tunggu {
    font-size: 29px;
}
.waktu_tunggu {
    font-size: 35px;
    font-weight: 900;
}
#activate_if_danger{
    font-size: 35px;
    color: #C63D2F;
    font-weight: 900;
    animation-name: complete;
    animation-delay: 0s;
    animation-duration: 1.0s;
    animation-timing-function: ease-out;
    animation-fill-mode: forwards;
}
.carousel.carousel-fade .item {
    -webkit-transition: opacity 0.5s ease-in-out;
    -moz-transition: opacity 0.5s ease-in-out;
    -ms-transition: opacity 0.5s ease-in-out;
    -o-transition: opacity 0.5s ease-in-out;
    transition: opacity 0.5s ease-in-out;
    opacity:0;
}

.carousel.carousel-fade .active.item {
    opacity:1;
}

.carousel.carousel-fade .active.left,
.carousel.carousel-fade .active.right {
    left: 0;
    z-index: 2;
    opacity: 0;
    filter: alpha(opacity=0);
}

.carousel.carousel-fade .next,
.carousel.carousel-fade .prev {
    left: 0;
    z-index: 1;
}

.carousel.carousel-fade .carousel-control {
    z-index: 3;
}
    .yellow {
        background-color: #FFBB5C !important;
        color: #fff;
    }
    .panel_antrian_terakhir{
        height: auto !important;
        min-height: 0 !important;
        padding-bottom: 8px !important;
    }
    .panel_antrian_terakhir table.below_antrian_pemeriksaan {
        margin-bottom: 0 !important;
    }
    .panel_antrian_terakhir table.below_antrian_pemeriksaan > tbody > tr > td,
    .panel_antrian_terakhir table.below_antrian_pemeriksaan > thead > tr > th {
        padding: 6px 8px !important;
        line-height: 1.2;
    }
    .table-farmasi{
        font-size: 18px;
    }
    .container_antrian_pemeriksaan{
        padding-right: 6px !important;
        width: 100%;
    }
    .container_antrian_farmasi{
        height: 459px;
    }
    #qr{
        position: relative;
        top: -10px;
    }
    .keterangan_wa{
        padding: 4px 0 !important;
        font-size: 28px;
        font-weight: 900;
    }
    .align-top{
        text-align: top;
    }
    .wa_position {
        position: relative;
        top: -28px;
    }
    .table>thead>tr>th {
        border-top: none;
        border-left: none;
        border-right: none;
    }
    .table>tbody>tr>td,
    .table>tbody>tr>th,
    .table>tfoot>tr>td,
    .table>tfoot>tr>th,
    .table>thead>tr>td,
    .table>thead>tr>th {
        border-left: none;
        border-right: none;
        border-bottom: none;
    }
    .no-padding-margin{
        padding: 0;
        margin: 0;
    }
    .wa_container .{
        width: 70px;
        height: 70px;
        border-radius: 35px;
        background: #3AC371;
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%,-50%);
    }
    .white{
        width: 80px;
        height: 80px;
        border-radius: 40px;
        background-color: #fff;
        box-shadow: 2px 2px 3px 2px rgba(0,0,0,.3);
    }
    .white::before{
        content: "";
        top: 65px;
        left: -15px;
        border-width: 20px;
        border-style: solid;
        border-color: transparent #fff transparent transparent;
        position: absolute;
        transform: rotate(-50deg) rotateX(-55deg);
    }
    .white::after{
        content: "";
        top: 63px;
        left: -4px;
        border-width: 15px;
        border-style: solid;
        border-color: transparent #3AC371 transparent transparent;
        position: absolute;
        transform: rotate(-51deg) rotateX(-50deg);
    }
    .fas{
        left: 17px;
        top: 18px;
        position: absolute;
        font-size: 35px;
        color: #fff;
        transform: rotate(90deg);
    }
	.animate__animated.animate__bounce {
	  --animate-duration: 1s;
	}
    .text-left {
        text-align: left;
    }
	* {
		box-sizing: border-box;
		text-align: center;
	}
    .row {
		background-color: #3AA6B9;
    }
	.column2 {
	  float: left;
	  width: 20%;
	}
	/* Create two unequal columns that floats next to each other */
	.column {
		float: left;
	}
    .col-lg-4 {
        margin: 0 !important;
    }
    .mr-10 {
        margin-right: 0 !important;
    }
    .pr-10 {
        padding-right: 10px;
    }

	.left {
		width: 70%;
	}

	.right {
		width: 30%;
	}

	/* Clear floats after the columns */
	.big{
		font-size: 40px;
		padding : 50 25 !important;
		border-radius: 10px;
		background-color: #fff;
		width: 70%;
		margin : 0 auto;
		font-weight: 900;
	}
	.list {
		font-size : 25px;
	}
	.full-width {
		width: 100%;
	}
	html, body {
		background-color: #3AA6B9;
		color: #636b6f;
		font-family: 'Nunito', sans-serif;
		font-weight: 200;
		margin: 0;
		overflow: hidden;
		/* Padding uniform (dr. Yoga 2026-09-27 rev-3): L=R=20px,
		   Top=12px, Bottom=24px lebih besar supaya footer QR
		   tidak nempel bottom edge TV (dan safe zone overscan
		   bawah). */
		padding: 12px 20px 24px 20px;
		box-sizing: border-box;
		width: 100vw;
		height: 100vh;
	}
	@media (min-width: 1px){
		.container {
			width: 100% !important;
			max-width: 100% !important;
			height: 100%;
			padding-left: 0;
			padding-right: 0;
			margin: 0;
			box-sizing: border-box;
			display: flex;
			flex-direction: column;
		}
	}
	/* Bootstrap 3 row negative margin — netralkan SEMUA .row (bukan
	   hanya direct child) supaya nested row di dalam col-md-4 kiri
	   tidak dorong konten keluar. */
	.row {
		margin-left: 0 !important;
		margin-right: 0 !important;
	}
	[class*="col-"] {
		padding-left: 8px !important;
		padding-right: 8px !important;
	}
	/* container_wa (footer) span full width — align padding sama dengan
	   3-col row di atas supaya visual edge-alignment konsisten. */
	.container_wa {
		margin-left: 8px !important;
		margin-right: 8px !important;
	}
	/* Flex fill layout — cegah empty space di bawah (dr. Yoga 2026-09-27).
	   Header pas isi, row antrian STRETCH isi space kosong, footer
	   carousel pas isi. */
	.container > .row.header {
		flex: 0 0 auto;
		min-height: 70px;
		display: flex;
		align-items: center;
	}
	/* Logo klinik: pastikan visible + tetap punya background pill hijau
	   (per instruksi dr. Yoga 2026-09-27 revisi). Fixed max-height +
	   display block. */
	.container > .row.header .logo {
		max-height: 60px;
		width: auto !important;
		max-width: 100%;
		display: block;
		background-color: #C1ECE4;
		border-radius: 20px;
		padding: 6px 15px;
	}
	.container > .row.row-no-padding {
		flex: 1 1 auto;
		display: flex;
		align-items: stretch;
		min-height: 0;
	}
	.container > .row.row-no-padding > [class*="col-"] {
		display: flex;
		flex-direction: column;
	}
	.container > .row.container_wa {
		flex: 0 0 auto;
	}
	/* Panel farmasi fleksibel — flex fill dari parent col */
	.container_antrian_farmasi {
		height: auto !important;
		min-height: 0;
		flex: 1 1 auto;
		overflow: hidden;
		display: flex;
		flex-direction: column;
	}
	.container_antrian_farmasi table {
		flex: 1 1 auto;
	}
	.container_antrian {
		padding: 6px !important;
	}
	/* Nomor panggilan biggest — clamp scale dgn viewport TV */
	.biggest {
		font-size: clamp(60px, 8vw, 140px) !important;
		padding: 0 !important;
		line-height: 1;
	}
	/* Responsive font (dr. Yoga 2026-09-27): pakai clamp(min, vw, max)
	   supaya font auto-scale di berbagai ukuran TV — kecil di layar
	   1366×768, besar di 1920×1080 / 4K. */
	body {
		font-size: clamp(14px, 1.1vw, 22px);
	}
	.title_antrian_farmasi {
		font-size: clamp(18px, 1.8vw, 32px) !important;
		padding: 6px 14px !important;
	}
	.header .waktu {
		font-size: clamp(16px, 1.5vw, 28px) !important;
	}
	.header .waktu #jam {
		font-size: clamp(22px, 2.2vw, 40px) !important;
	}
	.below_antrian_pemeriksaan td,
	.below_antrian_pemeriksaan th {
		font-size: clamp(14px, 1.3vw, 22px) !important;
		padding: 6px 10px !important;
		line-height: 1.15;
	}
	.table-farmasi td, .table-farmasi th {
		font-size: clamp(13px, 1.15vw, 20px) !important;
		padding: 5px 8px !important;
		line-height: 1.2;
	}
	.keterangan_wa {
		font-size: clamp(16px, 1.6vw, 30px) !important;
		font-weight: 900;
	}
	.wa_no {
		font-size: clamp(20px, 2vw, 36px) !important;
	}
	.container_wa img[src^="data:image"] {
		height: clamp(70px, 8vh, 130px) !important;
	}
	.keterangan_waktu_tunggu {
		font-size: clamp(15px, 1.4vw, 24px) !important;
	}
	.waktu_tunggu {
		font-size: clamp(18px, 1.8vw, 30px) !important;
	}
	#dipanggil .text-red {
		font-size: clamp(16px, 1.6vw, 28px) !important;
	}
	/* Kolom kiri: split jadi 2 panel (dipanggil ~40%, ruangan_terakhir ~60%) */
	.col-md-4:first-child .row:first-child {
		flex: 0 0 auto;
	}
	.col-md-4:first-child .row:last-child {
		flex: 1 1 auto;
		display: flex;
	}
	.col-md-4:first-child .row:last-child > div {
		display: flex;
		flex-direction: column;
		width: 100%;
	}
	/* Kotak "Antrian Pemeriksaan" (kiri atas):
	   - Title pill stretch full width sesuai container
	   - Content center vertikal supaya empty state (- / -) tidak
	     jomplang di tengah kotak besar
	   - Height auto tapi min-height cukup utk nomor besar */
	#dipanggil.container_antrian_pemeriksaan {
		padding: 12px !important;
		min-height: 200px;
		display: flex !important;
		flex-direction: column;
		justify-content: center;
		align-items: center;
	}
	#dipanggil .title_antrian_farmasi {
		margin: 0 0 12px 0 !important;
		width: 100%;
		text-align: center;
		box-sizing: border-box;
	}
	#dipanggil #nomor_panggilan {
		display: block;
		margin: 8px 0;
	}
	#dipanggil .text-red {
		font-size: 22px;
		margin-top: 4px;
	}
	.container_antrian_pemeriksaan.panel_antrian_terakhir {
		height: 100%;
		flex: 1 1 auto;
	}
	.container_antrian_pemeriksaan {
		padding: 8px !important;
	}
	.title_antrian_farmasi {
		font-size: 20px !important;
		padding: 3px 0 !important;
	}
	.header .waktu {
		font-size: 18px !important;
	}
	.header .waktu #jam {
		font-size: 24px !important;
	}
	.panel_antrian_terakhir {
		max-height: none;
		overflow: hidden;
	}
	.below_antrian_pemeriksaan td,
	.below_antrian_pemeriksaan th {
		padding: 3px 6px !important;
		font-size: 16px !important;
		line-height: 1.1;
	}
	.table-farmasi td, .table-farmasi th {
		font-size: 15px !important;
		padding: 3px 5px !important;
		line-height: 1.15;
	}
	/* Row bawah (carousel container_wa) — tidak fix 120px, biar auto
	   sesuai konten. Padding vertical kecil. */
	.container_wa {
		height: auto !important;
		min-height: 100px;
		max-height: 20vh;
		padding: 12px 20px !important;
		margin-top: 6px !important;
		overflow: hidden;
	}
	.container_wa .item {
		padding: 4px 0;
	}
	.keterangan_wa {
		font-size: 22px !important;
		padding: 4px 0 !important;
		font-weight: 900;
	}
	.wa_no {
		font-size: 26px !important;
		word-break: break-all;
	}
	/* QR code Telegram bot lebih besar & prominent */
	.container_wa img[src^="data:image"] {
		height: 100px !important;
		width: auto !important;
		margin-left: 12px !important;
	}
	.keterangan_waktu_tunggu {
		font-size: 18px !important;
	}
	.waktu_tunggu {
		font-size: 22px !important;
	}
	.wa_position {
		top: 0 !important;
	}
	#qr {
		height: 60px !important;
		top: 0 !important;
	}
	/* Danger/tindakan text ukuran seimbang */
	#activate_if_danger, #activate_if_tindakan_ruangan {
		max-height: 22vh;
		overflow: hidden;
		margin-top: 4px;
		font-size: 22px !important;
	}
	/* TV overflow fix (dr. Yoga 2026-09-27): kolom 3 (Racikan) +
	   footer text + jam kadang terpotong di kanan. Force wrap kata
	   panjang di sel tabel + shrink cell padding + hide horizontal
	   overflow di container. */
	.table-farmasi td, .table-farmasi th {
		word-break: break-word;
		overflow-wrap: anywhere;
		padding: 4px 6px !important;
	}
	.table-farmasi td.text-left {
		font-size: 16px;
		line-height: 1.15;
	}
	.container_antrian_farmasi {
		overflow: hidden;
	}
	.container_antrian.mr-10 {
		margin-right: 0 !important;
	}
	.waktu {
		text-align: right;
		overflow: hidden;
	}
	.waktu #jam, .waktu #hari {
		white-space: nowrap;
	}
	#activate_if_danger, #activate_if_tindakan_ruangan {
		width: 100%;
		overflow-wrap: anywhere;
		word-break: break-word;
		padding: 0 12px;
		box-sizing: border-box;
	}
	#text_notifikasi, #text_notifikasi_tindakan_ruangan {
		max-width: 100%;
		font-size: 32px;
	}
	.wa_no {
		font-size: 48px !important;
		word-break: break-all;
	}
	.keterangan_wa {
		font-size: 22px !important;
	}
	.wa_no{
		font-weight: 900;
		font-size: 69px;
		background-color: #fff;
        padding-top: -40px;
	}
	.biggest{
		font-weight: 900;
		font-size: 100px;
		background-color: #fff;
	}
    .container_wa {
      [class*="col-"] {
          background-color: #fff;
      }
    }
	.text-orange {
		color: #3B6345;
		margin : 15px 0px;
		font-weight: 900;
		font-size: 20px;
		padding : 100 50 !important;
	}
	.text-red {
		background-color: #fff;
		color: #093829;
		font-weight: 900;
		font-size: 20px;
	}
	#poli_panggilan {
		background-color: #fff;
	}
	.antrian {
		color: #fff;
		font-size: 25px;
		font-weight: 900;
		padding : 10px;
	}
    .container_wa{
		background-color: #ffffff !important;
		border-radius: 15px !important;
		margin :  0px 0px 0px 0px;
        padding : 10px 30px;
        height : 120px;
    }
    .container_antrian{
		background-color: #ffffff;
		border-radius: 17px;
		padding: 10px 5px;
		margin :  0px 0px 15px 0px;
    }
    .mt-40 {
        margin-top: 40px;
    }
    .title_antrian_farmasi {
		border-radius: 25px;
		padding: 10px 30px;
		margin: 0px 20px;
        border-radius: 200px;
        color: #ffffff;
        font-weight: 900;
        font-size: 20px;
		background-color: #3AA6B9;
    }
    .m-l-6{
        margin-left: 60px;
    }
    .m-r-6{
        margin-right: 60px;
    }
    .bw {
        background-color: #ffffff !important;
    }
    table tr td, table tr th {
        background-color: #fff;
    }
    .row-no-padding {
      [class*="col-"] {
        padding-left: 10 !important;
        padding-right: 0;
      }
    }

    [class*="col-"] {
        background-color: #3AA6B9;
    }
    .float-right{
        float: right;
    }
    .header {
        font-size: 30px;
        font-weight: 900;
        text-align: left;
        height: 40px;
    }
    .logo {
        width: 100%;
        background-color: #C1ECE4;
        border-radius: 20px;
        margin: 10px auto;
        padding: 0px 15px;
    }
    .waktu {
        color: #fff;
        text-align: right;
        font-weight: 1200;
        padding: 0px 20px 0px 0;
    }
    #jam {
        font-size: 50px;
        margin-left: 20px;
    }
    h3{
        background-color: #fff;
    }
    .below_antrian_pemeriksaan {
        font-size: 18px;
        font-weight: 900;
    }
    .borderless {
        border: none;
    }
    table tr td:nth-child(1){
        text-align: left;
    }
    table tr th{
        text-align: center;
    }
    .logo {
        cursor:pointer;
    }
    @keyframes flickerAnimation {
      0%   { opacity:1; }
      50%  { opacity:0; }
      100% { opacity:1; }
    }
    @-o-keyframes flickerAnimation{
      0%   { opacity:1; }
      50%  { opacity:0; }
      100% { opacity:1; }
    }
    @-moz-keyframes flickerAnimation{
      0%   { opacity:1; }
      50%  { opacity:0; }
      100% { opacity:1; }
    }
    @-webkit-keyframes flickerAnimation{
      0%   { opacity:1; }
      50%  { opacity:0; }
      100% { opacity:1; }
    }
    .animate-flicker {
       -webkit-animation: flickerAnimation .5s infinite;
       -moz-animation: flickerAnimation .5s infinite;
       -o-animation: flickerAnimation .5s infinite;
        animation: flickerAnimation .5s infinite;
    }
</style>


</head>

<body>
  <!-- Page Conten -->
  <div class="container">
      <div class="row header">
          <div class="col-xs-12 col-sm-2 col-md-2 col-lg-2">
              <img src="{{ secure_url('images/logo.png') }}" onclick="pglPasien([]); return false" class="logo">
          </div>
        <div class="col-xs-12 col-sm-10 col-md-10 col-lg-10 waktu">
            <span id="hari">
            Minggu, 24 September 2023
            </span>
            <span id="jam">
                13:35
            </span>
          </div>
      </div>
    <div class="row row-no-padding">
          <div class="col-xs-12 col-sm-4 col-md-4 col-lg-4">
            <div class="row">
                <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
                  <div id="dipanggil" class="container_antrian container_antrian_pemeriksaan">
                    <div class="title_antrian_farmasi">
                        Antrian Pemeriksaan
                    </div>
                      <span id="nomor_panggilan" class="biggest" >-</span>
                      <div class="text-red"><strong id="poli_panggilan">-</strong></div>
                  </div>
                </div>
            </div>
            <div class="row">
                <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
                  <div class="container_antrian container_antrian_pemeriksaan panel_antrian_terakhir">
                      <table class="table below_antrian_pemeriksaan borderless">
                          <thead>
                              <tr>
                                  <th>Ruangan</th>
                                  <th>No Antrian</th>
                              </tr>
                          </thead>
                          <tbody id="container_antrian_terakhir">
                              <tr>
                                  <td>
                                    Ruang Periksa 1
                                  </td>
                                    <td id="antrian_ruang_periksa_1">
                                        A32
                                  </td>
                              </tr>
                              <tr>
                                    <td>
                                    Ruang Periksa 2
                                  </td>
                                    <td id="antrian_ruang_periksa_2">
                                        A32
                                  </td>
                              </tr>
                              <tr>
                                    <td>
                                    Ruang Periksa 3
                                  </td>
                                    <td id="antrian_ruang_periksa_3">
                                        -
                                  </td>
                              </tr>
                              <tr>
                                    <td>
                                    Ruang Periksa Gigi
                                  </td>
                                    <td id="antrian_ruang_periksa_gigi">
                                        A32
                                  </td>
                              </tr>
                          </tbody>
                      </table>
                  </div>
                </div>
            </div>
          </div>
          <div class="col-xs-12 col-sm-4 col-md-4 col-lg-4">
            <div class="container_antrian container_antrian_farmasi">
                <div class="title_antrian_farmasi">
                    Antrian Obat Jadi
                </div>
                <br>
                <table class="table bw table-farmasi">
                    <thead>
                        <tr>
                            <th class="text-center">No</th>
                            <th class="text-center">Nama Pasien</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody id="container_antrian_obat_jadi">
                        <tr>
                            <td>A11</td>
                            <td class="text-left">
                                Yoga Hadi Nugroho
                            </td>
                            <td>
                                <span class="badge badge-primary">
                                    Selesai
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>A12</td>
                            <td class="text-left">
                                Sukma Wahyu Wijayanti
                            </td>
                            <td>
                                <span class="badge badge-warning">
                                    Proses
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>A13</td>
                            <td class="text-left">
                                R Puri Widiyani M
                            </td>
                            <td>
                                <span class="badge badge-danger">
                                    Menunggu
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
          </div>
          <div class="col-xs-12 col-sm-4 col-md-4 col-lg-4">
            <div class="container_antrian mr-10 container_antrian_farmasi">
                <div class="title_antrian_farmasi">
                    Antrian Obat Racikan
                </div>
                <br>
                <table class="table bw table-farmasi">
                    <thead>
                        <tr>
                            <th class="text-center">No</th>
                            <th class="text-center">Nama Pasien</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody id="container_antrian_obat_racikan">
                        <tr>
                            <td>A11</td>
                            <td class="text-left">
                                Yoga Hadi Nugroho
                            </td>
                            <td>
                                <span class="badge badge-primary">
                                    Selesai
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>A12</td>
                            <td class="text-left">
                                Sukma Wahyu Wijayanti
                            </td>
                            <td>
                                <span class="badge badge-warning">
                                    Proses
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>A13</td>
                            <td class="text-left">
                                R Puri Widiyani M
                            </td>
                            <td>
                                <span class="badge badge-danger">
                                    Menunggu
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
          </div>
      </div>
    <div class="row container_wa align-top text-left">
            @php
                $__ruanganTindakan = (isset($ruangan) && $ruangan && (int) ($ruangan->sedang_tindakan ?? 0) === 1);
                $__daruratOrTindakan = (bool) (\App\Models\Tenant::find(1)->menangani_gawat_darurat || $__ruanganTindakan);
            @endphp

            {{-- Overlay tindakan per-ruangan (scoped monitor). Layar TV
                 di ruang tunggu ruangan tsb akan menampilkan flicker warning
                 saat staf toggle "Mulai Tindakan". JS di bawah polling
                 tindakanStatus tiap 5s utk auto-update tanpa reload. --}}
            @if(isset($ruangan) && $ruangan)
            <div id="activate_if_tindakan_ruangan" class="animate-flicker {{ $__ruanganTindakan ? '' : 'hide' }}">
                <div id="text_notifikasi_tindakan_ruangan">
                    Saat ini dokter sedang melakukan tindakan di {{ $ruangan->nama }} <br>
                    Waktu tunggu akan menjadi lebih lama dari biasanya. Terima kasih atas kesabaran Anda menunggu
                </div>
            </div>
            @endif

            <div id="activate_if_danger" class="animate-flicker {{  \App\Models\Tenant::find(1)->menangani_gawat_darurat ? '' : 'hide' }}">
                <div id="text_notifikasi" style="display: none;">
                    Saat ini dokter sedang melakukan tindakan di UGD <br>
                    Terima kasih atas kesabaran Anda menunggu
                </div>
            </div>
        <div id="activate_if_not_danger" class="ibox float-e-margins {{ $__daruratOrTindakan ? 'hide' : '' }}">
                <div class="ibox-title">
                  <div class="ibox-tools">
                  </div>
                </div>
                <div class="ibox-content">
                  <div class="carousel slide carousel-fade" id="carousel1" data-interval="3000">
                    <div class="carousel-inner">
                      <div class="item active">
                            <div class="col-xs-12 col-sm-4 col-md-4 col-lg-4 keterangan_wa text-left">
                                Keluhan Atas Pelayanan<br>
                                Chat via Telegram Bot
                            </div>
                            <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8 text-right">
                                <span class="wa_no">
                                    <span style="font-size:20px; vertical-align: middle;">@KlinikJatiElokBot</span>
                                    <img class="text-right" src="{{ $base64 }}" style="height:60px; margin-left:8px; vertical-align:middle;" />
                                </span>
                            </div>
                      </div>
                    <div class="item">
                        <div class="col-xs-12 col-sm-4 col-md-4 col-lg-4 keterangan_waktu_tunggu text-center">
                            Waktu Tunggu Obat Jadi <br>
                            <span class="waktu_tunggu">15 - 30 Menit</span>

                        </div>
                        <div class="col-xs-12 col-sm-4 col-md-4 col-lg-4 keterangan_waktu_tunggu text-center">
                            Waktu Tunggu Obat Racikan</br>
                            <span class="waktu_tunggu">30 - 45 Menit</span>
                        </div>
                        <div class="col-xs-12 col-sm-4 col-md-4 col-lg-4 keterangan_waktu_tunggu text-center">
                            Kesabaran Anda<br>
                            <span class="waktu_tunggu">Ketelitian Kami</span>
                        </div>
                    </div>
                      <div class="item">
                        <div class="col-xs-12 col-sm-4 col-md-4 col-lg-4 keterangan_wa text-left">
                            Daftar Online<br>
                            Chat via Telegram Bot
                        </div>
                        <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8 keterangan_waktu_tunggu text-right">
                            <span class="wa_no">
                                <span style="font-size:20px; vertical-align: middle;">@KlinikJatiElokBot</span>
                                <img class="text-right" src="{{ $base64_daftar_online }}" style="height:60px; margin-left:8px; vertical-align:middle;" />
                            </span>
                        </div>
                    </div>
                </div>
              </div>
            </div>
          </div>
    </div>
</div>
<p id="hitung">

</p>
<div>
<audio id="ding">
  <source src="{{ secure_url('sound/bell-ding.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="myAudio">
  <source src="{{ secure_url('sound/bel.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_nomorantrian">
  <source src="{{ secure_url('sound/nomorantrian.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_1">
  <source src="{{ secure_url('sound/1.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_2">
  <source src="{{ secure_url('sound/2.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_3">
  <source src="{{ secure_url('sound/3.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_4">
  <source src="{{ secure_url('sound/4.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_5">
  <source src="{{ secure_url('sound/5.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_6">
  <source src="{{ secure_url('sound/6.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_7">
  <source src="{{ secure_url('sound/7.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_8">
  <source src="{{ secure_url('sound/8.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_9">
  <source src="{{ secure_url('sound/9.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_10">
  <source src="{{ secure_url('sound/10.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_11">
  <source src="{{ secure_url('sound/11.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_belas">
  <source src="{{ secure_url('sound/belas.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_puluh">
  <source src="{{ secure_url('sound/puluh.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_100">
  <source src="{{ secure_url('sound/100.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_a">
  <source src="{{ secure_url('sound/a.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_b">
  <source src="{{ secure_url('sound/b.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_c">
  <source src="{{ secure_url('sound/c.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_d">
  <source src="{{ secure_url('sound/d.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_e">
  <source src="{{ secure_url('sound/e.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_f">
  <source src="{{ secure_url('sound/f.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_g">
  <source src="{{ secure_url('sound/g.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_h">
  <source src="{{ secure_url('sound/h.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_i">
  <source src="{{ secure_url('sound/i.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_j">
  <source src="{{ secure_url('sound/j.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_k">
  <source src="{{ secure_url('sound/k.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_farmasi">
  <source src="{{ secure_url('sound/farmasi.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_kasir">
  <source src="{{ secure_url('sound/kasir.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_pendaftaran">
  <source src="{{ secure_url('sound/pendaftaran.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_rapidtest">
  <source src="{{ secure_url('sound/rapidtest.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ratus">
  <source src="{{ secure_url('sound/ratus.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ruangperiksa">
  <source src="{{ secure_url('sound/ruangperiksa.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ruangperiksasatu">
  <source src="{{ secure_url('sound/ruangperiksasatu.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ruangperiksadua">
  <source src="{{ secure_url('sound/ruangperiksadua.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ruangperiksagigi">
  <source src="{{ secure_url('sound/ruangperiksagigi.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ruangpf">
  <source src="{{ secure_url('sound/ruangpf.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_loketsatu">
  <source src="{{ secure_url('sound/loketsatu.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_loketdua">
  <source src="{{ secure_url('sound/loketdua.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ruangperiksatiga">
  <source src="{{ secure_url('sound/ruangperiksatiga.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_silahkanmenuju">
  <source src="{{ secure_url('sound/silahkanmenuju.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_menunggu">
  <source src="{{ secure_url('sound/menunggu.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
</div>


<!-- Bootstrap core JavaScript -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNL5UyIl/XNwTMqvzeRMZH2w8c5cRVpzpU8Y5bApTppSuUkhZXN0VxHd" crossorigin="anonymous"></script>
<script src="https://js.pusher.com/5.1/pusher.min.js"></script>
<script src="{!! asset('https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.24.0/moment.min.js') !!}"></script>
<script src="{!!secure_url("js/moment.locale.js") !!}"></script>

<script>
    $('#carousel1').carousel({
      interval: 7000,
      cycle: true
    });
    moment.locale('id')
    window.setInterval(function () {
        $('#hari').html(moment().format('dddd, DD MMMM YYYY'))
        $('#jam').html(moment().format('HH:mm:ss'))
    }, 1000);

    if (location.protocol !== 'https:') {
        var base = "{{ secure_url('/') }}";
    } else {
        var base = "{{ secure_url('/') }}";
    }
	var hitung = 0

	var channel_name = 'my-channel';
	var event_name   = 'form-submitted';

	Pusher.logToConsole = true;

	var pusher = new Pusher("{{ env('PUSHER_APP_KEY') }}", {
	  cluster:"{{ env('PUSHER_APP_CLUSTER') }}",
	  forceTLS: true
	});

	var channel = pusher.subscribe(channel_name);
	var nomor_antrian = '';

	function getChannelName(){
		@if( gethostname() == 'Yogas-Mac.local' )
			var channel_name = 'my-channel2';
		@else
			var channel_name = 'my-channel';
		@endif
		return channel_name;
	}

    var menangani_gawat_darurat = {{ $menangani_gawat_darurat }};
    var status_gawat_darurat_saat_ini = {{ $menangani_gawat_darurat }};

@isset($ruangan)
    // ===== Ruangan-scoped monitor: poll flag tindakan tiap 5 detik =====
    // Overlay show/hide follow flag ruangan real-time tanpa reload halaman.
    // Sinkron dgn tombol "Mulai Tindakan" / "Selesai Tindakan" di atika.
    (function () {
        var ruanganId = {{ (int) $ruangan->id }};
        var pollUrl   = base + '/antrianperiksa/monitor_baru/' + ruanganId + '/tindakan-status';
        var lastFlag  = {{ $__ruanganTindakan ? 'true' : 'false' }};

        function apply(sedangTindakan) {
            var $overlay = $('#activate_if_tindakan_ruangan');
            var $normal  = $('#activate_if_not_danger');
            var daruratActive = !!status_gawat_darurat_saat_ini;
            if (sedangTindakan) {
                $overlay.removeClass('hide');
                if (!daruratActive) $normal.addClass('hide');
            } else {
                $overlay.addClass('hide');
                if (!daruratActive) $normal.removeClass('hide');
            }
        }

        setInterval(function () {
            $.get(pollUrl, function (res) {
                if (!res || res.ok !== true) return;
                var flag = !!res.sedang_tindakan;
                if (flag !== lastFlag) {
                    lastFlag = flag;
                    apply(flag);
                }
            });
        }, 5000);
    })();
@endisset

</script>

<script src="{!! secure_url("js/antrian.js?ver=6") !!}"></script>
{{-- <script src="{!!secure_url("js/inspinia.js") !!}"></script> --}}
</body>
</html>
