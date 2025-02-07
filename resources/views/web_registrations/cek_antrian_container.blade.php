@foreach ($data as $d)
    <p>{{ $d['nama_ruangan'] }}</p>
    <h2>{{ $d['nomor_antrian_terakhir'] }}</h2>
    Nomor Antrian Anda :
    <h4>{{ $d['nomor_antrian_anda'] }}</h4>
@endforeach
