@extends('layouts.user')
@section('content')
    <layout 
        :_user-count="{{ $userCount }}"
        :theme-settings="{}"
        :_oidc-enabled='@json(config("oidc.enabled"))'
        _selected-language="spanish"
    ></layout>
@endsection
