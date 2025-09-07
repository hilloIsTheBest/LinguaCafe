@extends('layouts.user')
@section('content')
    <layout 
        :_user-count="{{ $userCount }}"
        :theme-settings="{}"
        :_oidc-enabled='@json($oidcEnabled)'
        _oidc-button-text='@json($oidcButtonText)'
        _oidc-button-icon='@json($oidcButtonIcon)'
        :_oidc-auto-launch='@json($oidcAutoLaunch)'
        _selected-language="spanish"
    ></layout>
@endsection
