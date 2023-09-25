@php
    $class = 'form-control textareacustom';
    if( isset($hide) && $hide ){
        $class .= ' hide';
    }
    if( isset($rq) && $rq ){
        $class .= ' rq';
    }
    if( isset( $tanggal ) ){
        $class .= ' tanggal';
    }
@endphp
<div class="form-group @if($errors->has($name)) has-error @endif">
    {!! Form::label($name, isset( $label ) ? $label : ucwords( str_replace('_', ' ', $name) ), ['class' => 'control-label']) !!}
    {!! Form::textarea($name , isset( $value ) ? $value : null, [
        'class' => $class,
        'placeholder' => isset( $placeholder ) ? $placeholder : '',
        'id'    => isset( $id ) ? $id : $name,
    ]) !!}
    @if($errors->has($name))<code>{!! $errors->first($name) !!}</code>@endif
</div>
