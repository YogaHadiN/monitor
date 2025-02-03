<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
<meta name="viewport" content= "width=device-width, user-scalable=no">

    <meta name="csrf-token" content="{{ csrf_token() }}" />
  <meta name="description" content="">
  <meta name="author" content="">
  <title>Antrian Pasien</title>
  <!-- Bootstrap core CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css?1" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
<link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
<script src="https://kit.fontawesome.com/888ab79ab3.js" crossorigin="anonymous"></script>
<style type="text/css" media="all">
.float-right {
    float: right;
}
.text-center {
    text-align: center;
}
.textareacustom {
    height: 75px !important;
}
.mt-10 {
    margin-top: 10px;
}
.mb-10 {
    margin-bottom: 10px;
}
.ulangi {
    margin-top: 10px;
}
.text-left{
    text-align: left;
}
.table-condensed{
  font-size: 10px;
}
.center-fit {
        max-width: 70%;
        max-height: 70vh;
        margin: auto;
    }
table { 
    table-layout:fixed;
}
td { 
    overflow: hidden; 
    text-overflow: ellipsis; 
    word-wrap: break-word;
}
@media only screen and (max-width: 480px) {
    /* horizontal scrollbar for tables if mobile screen */
    .tablemobile {
        overflow-x: auto;
        display: block;
    }
}
.glyphicon.spinning {
    animation: spin 1s infinite linear;
    -webkit-animation: spin2 1s infinite linear;
}

@keyframes spin {
    from { transform: scale(1) rotate(0deg); }
    to { transform: scale(1) rotate(360deg); }
}

@-webkit-keyframes spin2 {
    from { -webkit-transform: rotate(0deg); }
    to { -webkit-transform: rotate(360deg); }
}



</style>


</head>

<body>
    <input type="text" value="{{ $no_telp }}" name="no_telp" class="hide" id="no_telp"/>
    <div class="container">
    <div class="row">
        <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
            <h4>Klinik Jati Elok</h4>
        </div>
        {{-- <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6 float-right mt-10 text-right"> --}}
        {{--     <button onclick="pglPasien([]); return false" class="btn btn-success btn-sm"> --}}
        {{--         Aktifkan Notifikasi --}}
        {{--     </button> --}}
        {{-- </div> --}}
    </div>
        <hr>
        <div id="message">

        </div>
        <div id="container">

        </div>
    </div>
<div>
<audio id="ding">
  <source src="{{ url('sound/bell-ding.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="myAudio">
  <source src="{{ url('sound/bel.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_nomorantrian">
  <source src="{{ url('sound/nomorantrian.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_1">
  <source src="{{ url('sound/1.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_2">
  <source src="{{ url('sound/2.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_3">
  <source src="{{ url('sound/3.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_4">
  <source src="{{ url('sound/4.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_5">
  <source src="{{ url('sound/5.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_6">
  <source src="{{ url('sound/6.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_7">
  <source src="{{ url('sound/7.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_8">
  <source src="{{ url('sound/8.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_9">
  <source src="{{ url('sound/9.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_10">
  <source src="{{ url('sound/10.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_11">
  <source src="{{ url('sound/11.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_belas">
  <source src="{{ url('sound/belas.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_puluh">
  <source src="{{ url('sound/puluh.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_100">
  <source src="{{ url('sound/100.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_a">
  <source src="{{ url('sound/a.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_b">
  <source src="{{ url('sound/b.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_c">
  <source src="{{ url('sound/c.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_d">
  <source src="{{ url('sound/d.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_e">
  <source src="{{ url('sound/e.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_f">
  <source src="{{ url('sound/f.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_g">
  <source src="{{ url('sound/g.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_h">
  <source src="{{ url('sound/h.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_i">
  <source src="{{ url('sound/i.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_j">
  <source src="{{ url('sound/j.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_k">
  <source src="{{ url('sound/k.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_farmasi">
  <source src="{{ url('sound/farmasi.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_kasir">
  <source src="{{ url('sound/kasir.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_pendaftaran">
  <source src="{{ url('sound/pendaftaran.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_rapidtest">
  <source src="{{ url('sound/rapidtest.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ratus">
  <source src="{{ url('sound/ratus.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ruangperiksa">
  <source src="{{ url('sound/ruangperiksa.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ruangperiksasatu">
  <source src="{{ url('sound/ruangperiksasatu.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ruangperiksadua">
  <source src="{{ url('sound/ruangperiksadua.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ruangperiksagigi">
  <source src="{{ url('sound/ruangperiksagigi.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ruangpf">
  <source src="{{ url('sound/ruangpf.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_loketsatu">
  <source src="{{ url('sound/loketsatu.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_loketdua">
  <source src="{{ url('sound/loketdua.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_ruangperiksatiga">
  <source src="{{ url('sound/ruangperiksatiga.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_silahkanmenuju">
  <source src="{{ url('sound/silahkanmenuju.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
<audio id="audio_menunggu">
  <source src="{{ url('sound/menunggu.mp3') }}" type="audio/mpeg">
  Your browser does not support the audio element.
</audio>
</div>
<!-- Bootstrap core JavaScript -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNL5UyIl/XNwTMqvzeRMZH2w8c5cRVpzpU8Y5bApTppSuUkhZXN0VxHd" crossorigin="anonymous"></script>
<script src="https://js.pusher.com/5.1/pusher.min.js"></script>
<script src="{!! asset('https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.24.0/moment.min.js') !!}"></script>
<script src="{!! asset("js/moment.locale.js") !!}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script charset="utf-8">
      var base = '{{ url("") }}';
		$.ajaxSetup({
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			}
		});
      var base = '{{ url("") }}';
  </script>
<script src="{!!asset("js/daftar_online_by_phone.js") !!}"></script>
{{-- <script> --}}
{{--     $('#carousel1').carousel({ --}}
{{--       interval: 7000, --}}
{{--       cycle: true --}}
{{--     }); --}} 
{{--     moment.locale('id') --}}
{{--     window.setInterval(function () { --}}
{{--         $('#hari').html(moment().format('dddd, DD MMMM YYYY')) --}}
{{--         $('#jam').html(moment().format('HH:mm:ss')) --}}
{{--     }, 1000); --}}

{{--     if (location.protocol !== 'https:') { --}}
{{--         var base = "{{ url('/') }}"; --}}
{{--     } else { --}}
{{--         var base = "{{ url('/') }}"; --}}
{{--     } --}}
{{-- 	var hitung = 0 --}}

{{-- 	var channel_name = 'my-channel'; --}}
{{-- 	var event_name   = 'form-submitted'; --}}

{{-- 	Pusher.logToConsole = true; --}}

{{-- 	var pusher = new Pusher("{{ env('PUSHER_APP_KEY') }}", { --}}
{{-- 	  cluster:"{{ env('PUSHER_APP_CLUSTER') }}", --}}
{{-- 	  forceTLS: true --}}
{{-- 	}); --}}

{{-- 	var channel = pusher.subscribe(channel_name); --}}
{{-- 	var nomor_antrian = ''; --}}

{{-- 	function getChannelName(){ --}}
{{-- 		@if( gethostname() == 'Yogas-Mac.local' ) --}}
{{-- 			var channel_name = 'my-channel2'; --}}
{{-- 		@else --}}
{{-- 			var channel_name = 'my-channel'; --}}
{{-- 		@endif --}}
{{-- 		return channel_name; --}}
{{-- 	} --}}

{{--     var menangani_gawat_darurat = {{ $menangani_gawat_darurat }}; --}}
{{--     var status_gawat_darurat_saat_ini = {{ $menangani_gawat_darurat }}; --}}

{{-- </script> --}}
{{-- <script src="{!! url("js/antrian_mobile.js?ver=23") !!}"></script> --}}
</body>
</html>
