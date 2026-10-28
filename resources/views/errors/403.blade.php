@extends('errors::minimal')

@section('title', 'Acesso negado')
@section('code', '403')
@section('message', $exception->getMessage() ?: 'Você não tem permissão para acessar esta tela.')
