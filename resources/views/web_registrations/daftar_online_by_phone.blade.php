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
        <div class="alert alert-danger">
            <ul>
                <li>Mohon kedatangannya 30 menit sebelum perkiraan panggilan antrian</li>
                <li>Jangan Lupa SCAN QR CODE saat sudah tiba di klinik</li>
                <li>Apabila antrian terlewat mohon ambil antrian baru</li>
            </ul>

        </div>
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
<script src="{!! url("js/daftar_online_by_phone.js?ver=3") !!}"></script>
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
