@if (!empty( $message ))
    <br>
    <div class="alert {{ $alert_type ?? 'alert-danger' }}">
        {!! nl2br(e($message)) !!}
    </div>
@endif
