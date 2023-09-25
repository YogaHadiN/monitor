@extends('layouts.app')

@section('title')
    {{ __('Welcome') }} | {{ config('app.name') }}
@endsection

@section('content')
    <link href="{{ asset('dashboard.css') }}" rel="stylesheet">
    @include('layouts.navbar')

    <div class="row">
        <div class="col-md-4">
            <div class="card ml-5" style="height:23vh; border-radius: 15px;">
                <div style="background-color: #FFD737; border-radius: 40px; box-shadow: 4px 4px 10px rgba(249, 71, 1, 0.842);" class="m-3 mx-4">
                    <a href="{{ route('take-queue') }}">
                        <h1 class="p-2 mt-2 text-center text-dark">Ambil Antrian</h1>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mx-2" style="height:23vh; border-radius: 15px;">
                <div style="background-color: #FFD737; border-radius: 40px; box-shadow: 4px 4px 10px rgba(249, 71, 1, 0.842);"
                    class="m-3 mx-5">
                    <a href="{{ route('doctor-queue') }}">
                        <h1 class="p-2 mt-2 text-center text-dark">Antrian Dokter</h1>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mr-5" style="height:23vh; border-radius: 15px;">
                <div style="background-color: #FFD737; border-radius: 40px; box-shadow: 4px 4px 10px rgba(249, 71, 1, 0.842);" class="m-3 mx-5">
                    <a href="{{ route('pharmacy-queue') }}">
                        <h1 class="p-2 mt-2 text-center text-dark">Antrian Obat</h1>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-md-6">
            <div class="card mx-2" style="height:23vh; border-radius: 15px;">
                <div style="background-color: rgba(249, 71, 1, 0.842);; border-radius: 40px; box-shadow: 4px 4px 10px #FFD737;"
                    class="m-3 mx-5">
                    <a href="{{ route('general-consent') }}">
                        <h1 class="p-2 mt-2 text-center text-dark">General Consent</h1>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mx-2" style="height:23vh; border-radius: 15px;">
                <div style="background-color: rgba(249, 71, 1, 0.842);; border-radius: 40px; box-shadow: 4px 4px 10px #FFD737;"
                    class="m-3 mx-5">
                    <a href="{{ route('minutes-of-meeting') }}">
                        <h1 class="p-2 mt-2 text-center text-dark">Minutes of Meeting</h1>
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
