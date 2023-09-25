@php
    if( !isset($label) ){
        $label = ucwords( str_replace('_', ' ', $name) );
    }
    if( isset($rq) ){
        $attribute['class'] = 'form-control rq';
    } else {
        $attribute['class'] = 'form-control';
    }

    if( isset( $multiple ) ){
        $attribute['multiple'] = 'multiple';
    }
@endphp
<div class="form-group{{ $errors->has($name) ? ' has-error' : '' }}">
    {!! Form::label($name, $label, ['class' => 'control-label']) !!}
    {!! Form::file($name, $attribute) !!}
    @if(
         isset($model) &&
         !is_null($model->$name)
        )
        <a href="{{ \Storage::disk('s3')->url($model->$name) }}" alt="" class="btn btn-primary btn-sm" target="_blank" >
            <span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span>
        </a>
    @endif
    {!! $errors->first($name, '<p class="help-block">:message</p>') !!}
</div>
