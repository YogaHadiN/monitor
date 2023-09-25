@php
    $class = 'form-control';
    if( isset($rq) && $rq ){
        $class .= ' rq';
    }
    if( isset( $tanggal ) ){
        $class .= ' tanggal';
    }
    if( isset( $rupiah ) ){
        $class .= ' uangInput';
    }
    $attribute = [
            'class' => $class,
            'placeholder' => isset( $placeholder ) ? $placeholder : '',
            'id'    => isset( $id ) ? $id : $name,
        ];
    if( isset( $onkeypress ) ){
        $attribute['onkeypress'] = $onkeypress;
    }

    if( isset( $onchange ) ){
        $attribute['onchange'] = $onchange;
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
    @if (isset( $addOnPost ))
        <div class="input-group">
    @endif
        {!! Form::text($name , isset( $value ) ? $value : null, $attribute) !!}
    @if (isset( $addOnPost ))
        <span class="input-group-addon" id="addon{{ $name }}">{{ $addOnPost }}</span>
    </div>
    @endif
    @if($errors->has($name))<code>{!! $errors->first($name) !!}</code>@endif
</div>
