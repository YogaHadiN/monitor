<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">
  <title>Antrian Pasien</title>
  <!-- Bootstrap core CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
<script src="https://kit.fontawesome.com/888ab79ab3.js" crossorigin="anonymous"></script>
<link href="{!! asset('css/animate.css') !!}" rel="stylesheet">
<link href="{!! asset('css/style.css') !!}" rel="stylesheet">
<style type="text/css" media="all">
    .yellow {
        background-color: #FFBB5C !important;
        color: #fff;
    }
    .panel_antrian_terakhir{
        height: 267px !important;
    }
    .table-farmasi{
        font-size: 18px;
    }
    .container_antrian_pemeriksaan{
        padding-right: 3px !important;
        width: 96%;
    }
    .container_antrian_farmasi{
        height: 522px;
    }
    #qr{
        position: relative;
        top: -10px;
    }
    .keterangan_wa{
        padding: auto 0;
        font-size: 25px;
        font-weight: 900;
    }
    .align-top{
        text-align: top;
    }
    .wa_position {
        position: relative;
        top: -28px;
        

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
		background-color: #3AA6B9;
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
        margin-right: 15px !important;
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
	}
	@media (min-width: 1px){
		.container {
			width: 1280px;
            height: 100vh;
		}
	}
	.wa_no{
		font-weight: 900;
		font-size: 80px;
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
        background-color:  alt="Clinic Icon";
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
        font-size: 20px;
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
</style>


</head>

<body>
  <!-- Page Conten -->
  <div class="container">
      <div class="row header">
          <div class="col-xs-12 col-sm-2 col-md-2 col-lg-2">
              <img src="{{ secure_url('images/logo.png') }}" onclick="pglPasien([]); return false" class="logo">
                {{-- <button type="button" class="btn btn-primary" onclick='pglPasien([]); return false'> Play Bel</button> --}}
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
                      <span id="nomor_panggilan" class="biggest" >A32</span>
                      <div class="text-red"><strong id="poli_panggilan">Ruang Periksa 1</strong></div>
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
                                        A32
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
        <div class="col-xs-12 col-sm-3 col-md-3 col-lg-3 keterangan_wa text-center">
            Keluhan Atas Pelayanan Mohon Kirim Whatsapp Ke 
        </div>
        <div class="col-xs-12 col-sm-9 col-md-9 col-lg-9">
            <img src="{{ secure_url('images/wa.png') }}" width="10%" class="bw wa_position"/>
            <span class="wa_no">
                081381912803
                <img id="qr" height="100px" class="text-right" src="{{ $base64 }}" />
            </span>
        </div>
    </div>
</div>
<p id="hitung">
	
</p>
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
<audio id="audio_silahkanmenuju">
  <source src="{{ secure_url('sound/silahkanmenuju.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
</div>


<!-- Bootstrap core JavaScript -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script src="https://js.pusher.com/5.1/pusher.min.js"></script>
<script src="{!! asset('https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.24.0/moment.min.js') !!}"></script>
<script src="{!! secure_url("js/moment.locale.js") !!}"></script>

<script>
    moment.locale('id')
    window.setInterval(function () {
        $('#hari').html(moment().format('dddd, DD MMMM YYYY'))
        $('#jam').html(moment().format('HH:mm:ss'))
    }, 1000);
	var base = "{{ secure_url('/') }}";
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
</script>

<script src="{!! secure_url("js/antrian.js") !!}"></script>
</body>
</html>
