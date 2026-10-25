{{--
    Realtime shim (Pusher → Reverb), dr. Yoga 2026-10-25.
    Di-include tepat setelah <script pusher.min.js>. Kalau REVERB_WS_HOST
    kosong → no-op (tetap Pusher cloud). Kalau diisi → setiap `new Pusher(KEY, …)`
    dgn KEY = PUSHER_APP_KEY diarahkan ke server Reverb sendiri (wss://HOST/app/KEY).
    Rollback cukup kosongkan REVERB_WS_HOST + kembalikan PUSHER_* di .env.
--}}
@if (env('REVERB_WS_HOST') && env('REVERB_APP_KEY'))
<script>
(function () {
    if (!window.Pusher || window.Pusher.__reverbShim) return;
    var KEY = @json(env('PUSHER_APP_KEY')), RKEY = @json(env('REVERB_APP_KEY')), HOST = @json(env('REVERB_WS_HOST'));
    var Orig = window.Pusher;
    window.Pusher = new Proxy(Orig, {
        construct: function (target, args) {
            var key = args[0], opts = Object.assign({}, args[1] || {});
            if (key === KEY || key === RKEY) {
                key = RKEY;
                opts.wsHost = HOST; opts.wsPort = 443; opts.wssPort = 443;
                opts.forceTLS = true; opts.enabledTransports = ['ws', 'wss'];
                opts.disableStats = true; opts.cluster = opts.cluster || 'mt1';
                var a = opts.auth || {};
                opts.auth = Object.assign({}, a, { params: Object.assign({}, a.params || {}, { rt: 'reverb' }) });
            }
            return Reflect.construct(target, [key, opts]);
        },
        get: function (t, p) { return p === '__reverbShim' ? true : t[p]; }
    });
})();
</script>
@endif
