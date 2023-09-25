@extends('layout.app')

@section('title')
    {{ __('Pharmacy Queue') }} | {{ config('app.name') }}
@endsection

@section('content')
    <link href="{{ asset('dashboard.css') }}" rel="stylesheet">
    @include('layout.navbar')

    <div class="row">
        <div class="col-md-6">
            <div class="card ml-5" style="height:80vh; border-radius: 15px;">
                <div style="background-color: #FFD737; border-radius: 40px;" class="m-3 mx-5">
                    <h1 class="p-2 mt-2 text-center">Antrian Obat Jadi</h1>
                </div>
                <div class="card-header h2">

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Header 1</th>
                                <th>Header 2</th>
                                <th>Header 3</th>
                            </tr>
                        </thead>
                        <tbody style="padding: 0px">
                            <?php
                            // Loop to generate 10 rows
                            for ($i = 1; $i <= 10; $i++) {
                                echo '<tr>';
                                echo "<td>Data $i.1</td>";
                                echo "<td>Data $i.2</td>";
                                echo "<td>Data $i.3</td>";
                                echo '</tr>';
                            }
                            ?>
                        </tbody>
                    </table>

                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mr-5" style="height:80vh; border-radius: 15px;">
                <div style="background-color: #FFD737; border-radius: 40px;" class="m-3 mx-5">
                    <h1 class="p-2 mt-2 text-center">Antrian Obat Racikan</h1>
                </div>
                <div class="card-header h2">

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Header 1</th>
                                <th>Header 2</th>
                                <th>Header 3</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Loop to generate 10 rows
                            for ($i = 1; $i <= 10; $i++) {
                                echo '<tr>';
                                echo "<td>Data $i.1</td>";
                                echo "<td>Data $i.2</td>";
                                echo "<td>Data $i.3</td>";
                                echo '</tr>';
                            }
                            ?>
                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>
@endsection
