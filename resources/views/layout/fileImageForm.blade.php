@php
    if( !isset($label) ){
        $label = ucwords( str_replace('_', ' ', $name) );
    }
    $attribute['class'] = 'form-control';
    if( isset( $multiple ) ){
        $attribute['multiple'] = 'multiple';
    }
@endphp
<div class="form-group{{ $errors->has($name) ? ' has-error' : '' }}">
    {!! Form::label($name, $label, ['class' => 'control-label']) !!}
    {!! Form::file($name, $attribute) !!}
    @if( !isset($multiple) )
        @if (isset($model) && $model->$name)
            <div class="img-wrap">
                <button onclick="clickRemoveImage(this);return false;" class="btn btn-danger btn-circle close" type="button">
                    <i class="fa fa-times fa-2"></i>
                </button>
                <a href="{{ \Storage::disk('s3')->url($model->$name) }}" target="_blank">
                   <img src="{{ \Storage::disk('s3')->url($model->$name) }}" alt="" class="img-rounded upload"> 
                </a>
            </div>
        @else
            <p> <img src="{{ \Storage::disk('s3')->url('img/photo_not_available.png') }}" alt="" class="img-rounded upload"> </p>
        @endif
    @endif
    {!! $errors->first('bpjs_image', '<p class="help-block">:message</p>') !!}
</div>

