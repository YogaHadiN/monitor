@extends('layout.app')

@section('title')
    {{ __('Take Queue') }} | {{ config('app.name') }}
@endsection

@section('content')
    <link href="{{ asset('dashboard.css') }}" rel="stylesheet">
    @include('layout.navbar')

    <div class="text-center mb-3">
        <h1>Ambil Antrian</h1>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card ml-5" style="height:70vh; border-radius: 15px;">
                <div style="background-color: #FFD737; border-radius: 40px; box-shadow: 4px 4px 10px rgba(249, 71, 1, 0.842);" class="m-3 mx-4">
                    <a href="#">
                        <h1 class="p-2 mt-2 text-center text-dark">Dokter Umum</h1>
                    </a>
                </div>
                <div class="card-header text-center">
                    <h3>Jumlah antrian sebelumnya:</h3> <br>
                    <h1>19 Antrian</h1> <br>
                    <h3>Perkiraan waktu tunggu Dokter:</h3>
                    <h1>20-30 menit</h1> <br>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mx-2" style="height:70vh; border-radius: 15px;">
                <div style="background-color: #FFD737; border-radius: 40px; box-shadow: 4px 4px 10px rgba(249, 71, 1, 0.842);"
                    class="m-3 mx-5">
                    <a href="#">
                        <h1 class="p-2 mt-2 text-center text-dark">Dokter Gigi</h1>
                    </a>
                </div>
                <div class="card-header text-center">
                    <h3>Jumlah antrian sebelumnya:</h3> <br>
                    <h1>19 Antrian</h1> <br>
                    <h3>Perkiraan waktu tunggu Dokter:</h3>
                    <h1>20-30 menit</h1> <br>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mr-5" style="height:70vh; border-radius: 15px;">
                <div style="background-color: #FFD737; border-radius: 40px; box-shadow: 4px 4px 10px rgba(249, 71, 1, 0.842);" class="m-3 mx-5">
                    <a href="#">
                        <h1 class="p-2 mt-2 text-center text-dark">Bidan</h1>
                    </a>
                </div>
                <div class="card-header text-center">
                    <h3>Jumlah antrian sebelumnya:</h3> <br>
                    <h1>19 Antrian</h1> <br>
                    <h3>Perkiraan waktu tunggu Bidan:</h3>
                    <h1>20-30 menit</h1> <br>
                </div>
            </div>
        </div>
    </div>
@endsection
