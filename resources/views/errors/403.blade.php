@extends('errors::minimal')

@section('title', 'No tienes permiso')
@section('code', '403')
@section('message', 'No tienes permiso')
@section('description', 'Tu usuario puede ver el proyecto, pero esta acción no le corresponde a tu rol o a tu sector.')
