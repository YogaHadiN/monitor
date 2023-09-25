@extends('layout.app')

@section('title')
    {{ __('Doctor Queue') }} | {{ config('app.name') }}
@endsection

@section('content')
    <link href="{{ asset('dashboard.css') }}" rel="stylesheet">
    @include('layout.navbar')
    <div class="row">
        <div class="col-md-8">
            <div class="row">
                <div class="col-md-12 pr-0">
                    <div class="card ml-5" style="height:60vh;">
                        <iframe width="809" height="515"
                            src="https://www.youtube.com/embed/DgsBeYq4sZc?si=XKBlnROwefkIhvQS" title="YouTube video player"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen></iframe>
                    </div>
                </div>
                <div class="col-md-12 pr-0 mt-3">
                    <div class="card ml-5" style="height:20vh; border-radius: 15px;">
                        <div class="ml-5 mt-2">
                            <h3>Keluhan atas pelayanan kirim Whatsapp ke</h3>
                            <div class="d-flex">
                                <img src="{{ url('images/wa.png') }}" alt="wa" width="60px" height="60px">
                                <p style="font-size: 60px; margin-top: -10px" class="ml-3">0813 8191 2803</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <div class="col-md-4">
            <div class="row">
                <div class="col-md-12 pl-0">
                    <div class="card ml-3 mr-5 text-center" style="height:25vh; border-radius: 15px;">
                        <h3 class="mt-2 mb-0">Panggilan</h3>
                        <p style="font-size: 60px;" class="mb-0">A32</p>
                        <h3>Ruang Periksa 1</h3>
                    </div>
                </div>
                <div class="col-md-12 pl-0 mt-3">
                    <div class="card ml-3 mr-5 text-center" style="height:55vh; border-radius: 15px;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ruangan</th>
                                    <th>Nomor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>R Periksa 1</td>
                                    <td>A12</td>
                                </tr>
                                <tr>
                                    <td>R Periksa 2</td>
                                    <td>A20</td>
                                </tr>
                                <tr>
                                    <td>Dokter Gigi</td>
                                    <td>B12</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
