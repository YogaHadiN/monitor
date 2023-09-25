@extends('layout.app')

@section('title')
    {{ __('Minutes Of Meeting') }} | {{ config('app.name') }}
@endsection

@section('content')
    {{-- Page 1 --}}
    <table width="100%" cellspacing="20" cellpadding="20">
        <tr class="">
            <td width="20%">
                <img src="{{ public_path('images/icon.png') }}" alt="Clinic Icon" width="150px" height="150px">
            </td>
            <td width="80%" style="color: #8A9E00;" class="text-center">
                <h1 class="mb-4">
                    <strong>KLINIK JATI ELOK</strong>
                </h1>
                <p style="font-size: 16px; line-height: 22px;">
                    Bumi Jati Elok A1 No. 4-7<br>
                    Jalan Raya Legok - Parung Panjang KM. 3, Malang Nengah<br>
                    Pagedangan, Tangerang, Banten, Telp. 021 5977529<br>
                    PRAKTEK DOKTER 24 JAM, DOKTER GIGI, BIDAN, BERSALIN, USG,LABORATORIUM
                </p>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="border-top: 3px solid #8A9E00;">
                <p>
                    &emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;Tangerang,
                    <br> <br>
                </p>
                <p>
                    Nomor &emsp;&emsp; : <br>
                    Hal &emsp;&emsp;&emsp;&nbsp;&nbsp;&nbsp; : &emsp; Undangan Rapat Sosialisasi SK Pelayanan Klinis, Hak
                    dan
                    Kewajiban, PPA <br>
                    &emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; (Profesional Pemberi Asuhan) Dan
                    Rekam Medis Elektronik <br>
                    Lampiran &emsp; : <br> <br> <br>
                </p>

                <p>
                    Kepada Yth : <br>
                    Seluruh karyawan Klinik Pratama Jati Elok <br>
                    Di tempat <br> <br>
                </p>

                <p>
                    Dengan hormat, <br> <br>
                    Dengan ini mengundang seluruh karyawan dan karyawati Klinik Jati Elok untuk menghadiri <br>
                    Rapat Sosialisasi SK Pelayanan Klinis, Hak dan Kewajiban, PPA (Profesional Pemberi <br>
                    Asuhan) Klinik Jati Elok yang akan dilaksanakan pada: <br>
                </p>

                <p>
                    Hari/Tanggal : <br>
                    Waktu : <br>
                    Tempat : <br>
                </p>

                <p>
                    Demikian surat undangan ini dibuat untuk dapat dilaksanakan sebagaimana mestinya. Atas <br>
                    perhatian dan kerjasamanya, kami ucapkan terima kasih. <br>
                </p>

            </td>
        </tr>
    </table>
    <table width="100%" cellspacing="20" cellpadding="20">
        <tr>
            <td width="70%">
                <p>

                </p>
            </td>
            <td width="30%">
                <p>
                    <br>
                    Penanggung Jawab PKP, <br><br><br><br>
                    (……………………………………) <br> <br> <br> <br>
                </p>
            </td>
        </tr>
    </table>
    {{-- Page 2 --}}
    <table width="100%" cellspacing="10" cellpadding="10">
        <tr class="">
            <td width="20%">
                <img src="{{ public_path('images/icon.png') }}" alt="Clinic Icon" width="150px" height="150px">
            </td>
            <td width="80%" style="color: #8A9E00;" class="text-center">
                <h1 class="mb-4">
                    <strong>KLINIK JATI ELOK</strong>
                </h1>
                <p style="font-size: 16px; line-height: 22px;">
                    Bumi Jati Elok A1 No. 4-7<br>
                    Jalan Raya Legok - Parung Panjang KM. 3, Malang Nengah<br>
                    Pagedangan, Tangerang, Banten, Telp. 021 5977529<br>
                    PRAKTEK DOKTER 24 JAM, DOKTER GIGI, BIDAN, BERSALIN, USG,LABORATORIUM
                </p>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="border-top: 3px solid #8A9E00;">
                <h5 class="text-center">
                    <strong>
                        DAFTAR HADIR PESERTA <br> <br>
                        NO. <br> <br>
                    </strong>
                </h5>

                <table width="100%"
                    style="border-right: 1px solid #000; border-left: 1px solid #000; border-top: 1px solid #000; border-bottom: 1px solid #000;">
                    <tr style="border-bottom: 1px solid #000;">
                        <td style="border-right: 1px solid #000;">&emsp;Acara</td>
                        <td style="border-right: 1px solid #000;">&nbsp;&nbsp;:&nbsp;&nbsp;</td>
                        <td>&emsp;Sosialisasi SK Pelayanan Klinis, Hak dan Kewajiban, PPA (Profesional Pemberi Asuhan) Dan
                            <br>
                            &emsp;Rekam Medis Elektronik
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #000;">
                        <td style="border-right: 1px solid #000;">&emsp;Hari/Tanggal&emsp;</td>
                        <td style="border-right: 1px solid #000;">&nbsp;&nbsp;:&nbsp;&nbsp;</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td style="border-right: 1px solid #000;">&emsp;Tempat</td>
                        <td style="border-right: 1px solid #000;">&nbsp;&nbsp;:&nbsp;&nbsp;</td>
                        <td>&emsp;Klinik Pratama Jati Elok</td>
                    </tr>
                </table> <br>

                <table width="100%" style="border: 1px solid black;" cellpadding="0">
                    <thead>
                        <tr class="text-center" style="border: 1px solid black;">
                            <th style="border: 1px solid black;">No</th>
                            <th style="border: 1px solid black;">Nama Pegawai</th>
                            <th style="border: 1px solid black;">Jabatan Pegawai</th>
                            <th colspan="2" style="border: 1px solid black;">Tanda Tangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        for ($i = 1; $i <= 20; $i++) {
                            echo '<tr>';
                            echo '<td style="border: 1px solid black; text-align: center">' . $i . '</td>';
                            echo '<td style="border: 1px solid black;"></td>';
                            echo '<td style="border: 1px solid black;"></td>';
                            if ($i % 2 == 1) {
                                // Check if it's an odd row
                                echo '<td style="border-right: 1px solid black;">' . $i . '</td>';
                                echo '<td style="border-right: 1px solid black;"></td>';
                            } else {
                                echo '<td style="border-bottom: 1px solid black; border-left: 1px solid black;"></td>';
                                echo '<td style="border-bottom: 1px solid black; border-left: 1px solid black;">' . $i . '</td>';
                            }
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
    <p class="ml-3">
        Hadir &emsp;&emsp;&emsp;&emsp;&nbsp;&nbsp;&nbsp;&nbsp; : <br>
        Izin &emsp;&emsp;&emsp;&emsp;&emsp;&emsp; : <br>
        Tanpa Keterangan :
    </p>
    <table width="100%" cellspacing="10" cellpadding="10">
        <tr>
            <td width="70%">
                <p>
                    Diketahui Oleh,<br>
                    Dikrektur Klinik Pratama Jati Elok <br><br><br><br>
                    Dr Yoga Hadi Nugroho, SPDV
                </p>
            </td>
            <td width="30%">
                <p>
                    Tangerang, …../….…./……<br>
                    Dibuat Oleh <br><br><br><br>
                    (……………………………………)
                </p>
            </td>
        </tr>
    </table>
    {{-- Page 3 --}}
    <table width="100%" cellspacing="20" cellpadding="20">
        <tr class="">
            <td width="20%">
                <img src="{{ public_path('images/icon.png') }}" alt="Clinic Icon" width="150px" height="150px">
            </td>
            <td width="80%" style="color: #8A9E00;" class="text-center">
                <h1 class="mb-4">
                    <strong>KLINIK JATI ELOK</strong>
                </h1>
                <p style="font-size: 16px; line-height: 22px; margin-bottom: -20px;">
                    Bumi Jati Elok A1 No. 4-7<br>
                    Jalan Raya Legok - Parung Panjang KM. 3, Malang Nengah<br>
                    Pagedangan, Tangerang, Banten, Telp. 021 5977529<br>
                    PRAKTEK DOKTER 24 JAM, DOKTER GIGI, BIDAN, BERSALIN, USG,LABORATORIUM
                </p>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="border-top: 3px solid #8A9E00;">
                <h5 class="text-center">
                    <strong>
                        NOTULEN RAPAT <br> <br>
                        NO. <br> <br>
                    </strong>
                </h5>
                <table>
                    <tr>
                        <td>Pembahasan</td>
                        <td>&emsp;&emsp;&emsp;:</td>
                    </tr>
                    <tr>
                        <td>Tempat</td>
                        <td>&emsp;&emsp;&emsp;:</td>
                    </tr>
                    <tr>
                        <td>Hari, Tanggal, Waktu</td>
                        <td>&emsp;&emsp;&emsp;:</td>
                    </tr>
                    <tr>
                        <td>Peserta Rapat</td>
                        <td>&emsp;&emsp;&emsp;:&emsp;</td>
                        <td>Hadir</td>
                        <td>:&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;</td>
                        <td>Izin</td>
                        <td>:&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;</td>
                        <td>TK</td>
                        <td>:</td>
                    </tr>
                    <tr>
                        <td>1. Agenda Rapat</td>
                        <td>&emsp;&emsp;&emsp;:</td>
                    </tr>
                    <tr>
                        <td><br><br><br><br><br><br>2. Pembukaan</td>
                        <td><br><br><br><br><br><br>&emsp;&emsp;&emsp;:</td>
                    </tr>
                    <tr>
                        <td><br><br><br><br><br><br>3. Pembahasan pertemuan yang lalu</td>
                        <td><br><br><br><br><br><br>&emsp;&emsp;&emsp;:</td>
                    </tr>
                    <tr>
                        <td><br><br><br><br><br><br>4. Pembahasan Rapat Hari ini</td>
                        <td><br><br><br><br><br><br>&emsp;&emsp;&emsp;:</td>
                    </tr>
                    <tr>
                        <td><br><br><br><br><br><br>5. Lain-Lain</td>
                        <td><br><br><br><br><br><br>&emsp;&emsp;&emsp;:</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    {{-- Page 4 --}}
    <table width="100%" cellspacing="20" cellpadding="20">
        <tr>
            <td width="20%">
                <img src="{{ public_path('images/icon.png') }}" alt="Clinic Icon" width="150px" height="150px">
            </td>
            <td width="80%" style="color: #8A9E00;" class="text-center">
                <h1 class="mb-4">
                    <strong>KLINIK JATI ELOK</strong>
                </h1>
                <p style="font-size: 16px; line-height: 22px;">
                    Bumi Jati Elok A1 No. 4-7<br>
                    Jalan Raya Legok - Parung Panjang KM. 3, Malang Nengah<br>
                    Pagedangan, Tangerang, Banten, Telp. 021 5977529<br>
                    PRAKTEK DOKTER 24 JAM, DOKTER GIGI, BIDAN, BERSALIN, USG,LABORATORIUM
                </p>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="border-top: 3px solid #8A9E00;">
                <table>
                    <tr>
                        <td>6. Kesimpulan</td>
                        <td>&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;:</td>
                    </tr>
                    <tr>
                        <td><br><br><br><br><br><br>7. Penutup</td>
                        <td><br><br><br><br><br><br>&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;:
                        </td>
                    </tr>
                    <tr>
                        <td><br><br><br><br><br><br>8. Jam Selesai</td>
                        <td><br><br><br><br><br><br>&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;:
                        </td>
                    </tr>
                </table>
                <br><br><br><br><br><br><br><br><br><br><br><br>
            </td>
        </tr>
    </table>
    <table width="100%" cellspacing="10" cellpadding="10">
        <tr>
            <td width="70%">
                <p>
                    Diketahui Oleh,<br>
                    Dikrektur Klinik Pratama Jati Elok <br><br><br><br>
                    Dr Yoga Hadi Nugroho, SPDV
                </p>
            </td>
            <td width="30%">
                <p>
                    Tangerang,<br>
                    Dibuat Oleh <br><br><br><br>
                    (……………………………………)
                </p>
            </td>
        </tr>
    </table>
@endsection
