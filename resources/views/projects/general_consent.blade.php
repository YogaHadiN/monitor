@extends('layout.app')

@section('title')
    {{ __('General Consent') }} | {{ config('app.name') }}
@endsection

@section('content')
    <table cellspacing="10" cellpadding="10">
        <tr class="text-center"
            style="border-right: 1px solid #000; border-left: 1px solid #000; border-top: 1px solid #000; border-bottom: 1px solid #000;">
            <td style="border-right: 1px solid #000;">
                <img src="{{ public_path('images/icon.png') }}" alt="Clinic Icon" width="80px" height="80px">
            </td>
            <td style="color: #8A9E00; border-right: 1px solid #000;">
                <h5>
                    <strong>KLINIK JATI ELOK</strong>
                </h5>
                <p style="font-size: 12px; line-height: 1;">
                    Bumi Jati Elok A1 No. 4-7<br>
                    Jalan Raya Legok - Parung Panjang KM. 3, Malang Nengah<br>
                    Pagedangan, Tangerang, Banten, Telp. 021 5977529<br>
                    PRAKTEK DOKTER 24 JAM, DOKTER GIGI, BIDAN, BERSALIN,<br>
                    USG,LABORATORIUM
                </p>
            </td>
            <td>
                <h5><strong>GENERAL CONSENT <br> (PERSETUJUAN UMUM)</strong></h5>
            </td>
        </tr>
        <tr>
            <!-- Content for the rest of the table goes here -->
            <td colspan="3" style="border-right: 1px solid #000; border-left: 1px solid #000;">
                <!-- Use colspan to span all three columns -->
                <p>
                    Yang bertandatangan dibawah ini:<br>
                    Nama :<br>
                    Alamat :<br>
                    Telepon :<br>
                </p>

                <p>
                    Saya selaku diri sendiri dari pasien <span style="background-color: yellow">………….. /
                        …..-…….-……./……………...,</span> menyatakan persetujuan atas
                    pernyataan-pemyataan dibawah ini:
                </p>

                <ol>
                    <li>
                        Saya telah mendapatkan informasi mengenai hak dan kewajiban pasien, tata tertib serta tata cara
                        menyampaikan keluhan pada saat proses pendaftaran.
                    </li>
                    <li>
                        Persetujuan Pelayanan Kesehatan
                        <ol type="a">
                            <li>
                                Saya memberikan persetujuan untuk mendapat pelayanan kesehatan dan memberikan kuasa kepada
                                dokter, perawat, bidan dan tenaga kesehatan lainnya untuk memberikan/melakukan asuhan medis,
                                pemeriksaan fisik, tindakan medis rutin (pasang infus, kateter, klisma, ganti verban,
                                nebulizer dll), prosedur diagnostik rutin non-invasif (laboratorium, radiologi, EKG, USG
                                dll), serta pemberian obat-obatan baik oral maupun intravena dan intramuskular.
                            </li>
                            <li>
                                Saya memahami bahwa saya memiliki hak untuk memutuskan atau menghentikan perawatan atau
                                tindakan kedokteran, dan saya bertanggung jawab atas semua akibat yang dapat merugikan saya
                                atas keputusan tersebut.
                            </li>
                        </ol>
                    </li>
                    <li>
                        Saya memahami bahwa kerahasiaan data medis saya akan selalu dijaga, dan saya menyetujui data medis
                        saya diberikan kepada Penjamin yang membiayai perawatan dan pengobatan saya.
                    </li>
                    <li>
                        Saya menyetujui bahwa saya akan mendapatkan privasi selama saya mendapatkan pengobatan dan
                        perawatan.
                    </li>
                    <li>
                        Saya menyetujui bahwa tidak ada penggantian atas kehilangan atau kerusakan atas barang dan kendaraan
                        milik saya.
                    </li>
                    <li>
                        Terkait kewajiban pembayaran:
                        <ol type="a">
                            <li>
                                Saya menyetujui bahwa status penjaminan pasien ditentukan di awal perawatan atau pengobatan.
                            </li>
                            <li>
                                Saya telah mendapatkan informasi mengenai biaya perawatan dan pengobatan.
                            </li>
                            <li>
                                Saya menyetujui bahwa apabila saya sebagai pasien umum atau pasien jaminan lainnya, maka
                                tidak bisa berpindah menjadi pasien jaminan BPJS Kesehatan dalam satu episode
                                perawatan/pengobatan.
                            </li>
                            <li>
                                Saya menyetujui membayar biaya perawatan/pengobatan apabila:
                                <ol type="a">
                                    <li>
                                        Penjamin menolak penjaminan karena alasan apapun.
                                    </li>
                                    <li>
                                        Terjadi selisih biaya akibat perawatan melebihi plafon.
                                    </li>
                                </ol>
                            </li>
                        </ol>
                    </li>
                </ol>

                <p>
                    Dengan ini saya menyatakan bahwa telah membaca, memahami dan menyetujui semua pernyataan diatas, tanpa
                    paksaan dari pihak manapun.<br> <br>
                </p>

            </td>
        </tr>
    </table>
    <table width="100%" cellspacing="10" cellpadding="10">
        <tr>
            <td width="70%" style="border-bottom: 1px solid #000; border-left: 1px solid #000;">
                <p>
                    Tangerang, ……/……./………..<br>
                    Yang membuat penyataan, <br><br><br><br>
                    (……………………………………)
                </p>
            </td>
            <td width="30%" style="border-bottom: 1px solid #000; border-right: 1px solid #000;">
                <p>
                    <br>
                    Yang membuat informasi, <br><br><br><br>
                    (……………………………………)
                </p>
            </td>
        </tr>
    </table>
@endsection
