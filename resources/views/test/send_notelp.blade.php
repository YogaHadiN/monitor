<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Test Kirim FCM</title>
</head>
<body>
    <h2>Test Kirim Notifikasi ke FCM</h2>

    @if(session('success'))
        <p style="color: green;">{{ session('success') }}</p>
    @endif

    @if($errors->any())
        <div style="color: red;">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ url('/test/send-notelp') }}">
        @csrf
        <label>No Telp:</label><br>
        <input type="text" name="no_telp" value="{{ old('no_telp', '628123456789') }}"><br><br>

        <label>FCM Device Token:</label><br>
        <textarea name="token" rows="3" cols="70">{{ old('token') }}</textarea><br><br>

        <button type="submit">Kirim Notifikasi</button>
    </form>
</body>
</html>
