<div class="row">
    @if (isset( $no_cancel ) && $no_cancel)
        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
    @else
        <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
    @endif
        @if(isset($model))
            <button class="btn btn-success btn-block" type="button" onclick='dummySubmit(this);return false;'>Update</button>
        @else
            <button class="btn btn-success btn-block" type="button" onclick='dummySubmit(this);return false;'>Submit</button>
        @endif
    </div>
    @if (!isset( $no_cancel ))
        <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
            <a class="btn btn-danger btn-block" href="{{ url('laporans') }}">Cancel</a>
        </div>
    @endif
</div>
<script type="text/javascript" charset="utf-8">
    function dummySubmit(control){
        if(validatePass2(control)){
            control.disabled = true;
            control.closest("form").submit();
        }
    }
</script>
