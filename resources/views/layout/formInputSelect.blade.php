@php
    $class = 'form-control';
    if( isset($rq) && $rq ){
        $class .= ' rq';
    }
    if( 
        isset($live) ||
        isset($multiple)
    ){
        $class .= ' selectpick';
    }


    if( str_contains( $name, '[]' ) ){
        $id_name = str_replace('[]', '', $name);
    } else {
        $id_name = $name;
    }
    $column = trim( str_replace('_id',' ', $name) );
    if(substr( $name , -3) == '_id'){
        if( !isset($label) ){
            $label = ucwords( str_replace('_', ' ', $column) );
        }
        if( !isset($options) ){
            $model = explode(' ', ucwords( str_replace('_', ' ', $column)));
            $full_model = '\App\Models\\';
            foreach ($model as $m) {
                $full_model .=  ucfirst( $m );
            }
            $options = $full_model::pluck($column, 'id');
        }
    }
    $attribute = [
        'class'            => $class,
        'id'               => isset( $id ) ? $id : $id_name,
        'data-live-search' => isset($live) ? 'true' : 'false',
    ];

    if( isset($onchange) ){
        $attribute['onchange'] = $onchange;
    }
    if( isset($multiple) ){
        $attribute['multiple'] = $multiple;
        $attribute['title'] = isset( $placeholder ) ? $placeholder :  '-Pilih-';
    } else {
        $attribute['placeholder'] = isset( $placeholder ) ? $placeholder :  '-Pilih-';
    }
@endphp
<div class="form-group @if($errors->has($name)) has-error @endif">
    {!! Form::label($name, isset( $label ) ? $label : ucwords( str_replace('_', ' ', $name) ), ['class' => 'control-label']) !!}
    {!! Form::select($name , $options, isset($value)? $value : null, $attribute) !!}
    @if($errors->has($name))<code>{!! $errors->first($name) !!}</code>@endif
</div>
