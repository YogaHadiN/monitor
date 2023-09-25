@php
    $class = 'form-control';
    if( isset($rq) && $rq ){
        $class .= ' rq';
    }
    if( isset( $tanggal ) ){
        $class .= ' tanggal';
    }
    $attribute = [
            'class' => $class,
            'placeholder' => isset( $placeholder ) ? $placeholder : '',
            'id'    => isset( $id ) ? $id : $name,
        ];
    if( isset( $onkeypress ) ){
        $attribute['onkeypress'] = $onkeypress;
    }
    if( isset( $onkeyup ) ){
        $attribute['onkeyup'] = $onkeyup;
    }
    if( isset( $readonly ) ){
        $attribute['readonly'] = 'readonly';
    }
@endphp
<div class="form-group @if($errors->has($name)) has-error @endif">
  {!! Form::label($name, isset( $label ) ? $label : ucwords( str_replace('_', ' ', $name) ), ['class' => 'control-label']) !!}
<div class="input-group jam" data-placement="left" data-align="top" data-autoclose="true">
  {!! Form::text($name , null, ['class' => $class]) !!}
    <span class="input-group-addon">
        <span class="glyphicon glyphicon-time"></span>
    </span>
</div>
  @if($errors->has($name))<code>{!! $errors->first($name) !!}</code>@endif
</div>
