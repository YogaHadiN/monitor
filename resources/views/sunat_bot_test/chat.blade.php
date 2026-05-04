@extends('layout.app')

@section('title', 'Sunat Bot Tester')

@section('content')
<div class="container py-4" style="max-width: 720px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0">Sunat Bot Tester</h4>
        <form method="POST" action="{{ url('/sunat-bot/test/reset') }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger">Reset Session</button>
        </form>
    </div>

    <div class="text-muted small mb-2">
        Simulasi: <code>{{ $no_telp }}</code>
        @if ($session)
            · Step aktif: <strong>{{ $session->current_step }}</strong>
            @if (!empty($session->collected_data))
                · Data: <code>{{ json_encode($session->collected_data, JSON_UNESCAPED_UNICODE) }}</code>
            @endif
        @else
            · <em>belum ada session</em>
        @endif
    </div>

    <div class="card mb-3" style="background:#f4f6f8;">
        <div class="card-body" style="height:480px; overflow-y:auto;" id="chat-box">
            @if (empty($history))
                <div class="text-center text-muted small py-5">
                    Mulai dengan kirim pesan yang mengandung kata <strong>sunat</strong> atau <strong>khitan</strong>.
                </div>
            @else
                @foreach ($history as $msg)
                    @if ($msg['role'] === 'user')
                        <div class="d-flex justify-content-end mb-2">
                            <div class="px-3 py-2 rounded" style="background:#dcf8c6; max-width:75%; white-space:pre-wrap;">
                                {{ $msg['text'] }}
                            </div>
                        </div>
                    @elseif ($msg['role'] === 'bot')
                        <div class="d-flex justify-content-start mb-2">
                            <div class="px-3 py-2 rounded" style="background:#fff; max-width:75%; white-space:pre-wrap; box-shadow:0 1px 2px rgba(0,0,0,.08);">
                                @if (!empty($msg['image']))
                                    <div class="text-muted small mb-1">[image: {{ $msg['image'] }}]</div>
                                @endif
                                {{ $msg['text'] }}
                            </div>
                        </div>
                    @else
                        <div class="text-center my-2">
                            <span class="text-muted small fst-italic">{{ $msg['text'] }}</span>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
    </div>

    <form method="POST" action="{{ url('/sunat-bot/test') }}">
        @csrf
        <div class="input-group">
            <input type="text" name="message" class="form-control" placeholder="Tulis pesan..." autofocus required>
            <button type="submit" class="btn btn-primary">Kirim</button>
        </div>
        <div class="form-text">
            Tip: ketik <code>akhiri</code> untuk mengakhiri sesi, <code>cs</code> untuk eskalasi ke admin.
        </div>
    </form>
</div>

<script>
    (function () {
        var box = document.getElementById('chat-box');
        if (box) box.scrollTop = box.scrollHeight;
    })();
</script>
@endsection
