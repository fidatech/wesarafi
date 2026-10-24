@extends('layouts.layoutMaster')
@section('title', 'Branch Capital')
@section('content')
<div class="row g-3">
    <div class="col-lg-2">@include('layouts.menu.accounting-sidebar')</div>
    <div class="col-lg-10 fb-content"><div class="fb-main">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(isset($errors) && $errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        @yield('branch-content')
    </div></div>
</div>
@endsection
