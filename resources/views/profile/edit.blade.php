@extends('layouts.layoutMaster')
@section('title','My Profile')
@section('content')
<div class="row justify-content-center"><div class="col-xl-9">
@if(session('status')==='system-refreshed')<div class="alert alert-success">System cache, routes, views, and configuration were refreshed.</div>@endif
<div class="card mb-3"><div class="card-header"><h4>My Profile</h4></div><div class="card-body">@include('profile.partials.update-profile-information-form')</div></div>
<div class="card mb-3"><div class="card-header"><h4>Change Password</h4></div><div class="card-body">@include('profile.partials.update-password-form')</div></div>
<div class="card mb-3"><div class="card-header"><h4>Refresh System</h4></div><div class="card-body"><p class="text-muted">Clear application, route, configuration and compiled-view caches after deployment or configuration changes.</p><form method="post" action="{{route('profile.refresh-system')}}">@csrf<button class="btn btn-outline-primary"><i class="bx bx-refresh me-1"></i>Refresh system</button></form></div></div>
</div></div>
@endsection