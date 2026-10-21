@extends('layouts.layoutMaster')
@section('title', 'Remittances')
@section('content')
<div class="row g-3"><div class="col-lg-2">@include('layouts.menu.remittance-sidebar')</div><div class="col-lg-10 fb-content">
@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(isset($errors) && $errors->any())
<div class="alert alert-danger"><ul class="mb-0">
@foreach($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul></div>
@endif
<div class="fb-main">
@yield('remittance-content')
</div></div></div>
@endsection
