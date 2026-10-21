@extends('layouts.layoutBlank')

@section('title', 'Forgot Password')

@section('content')
<div class="auth-card">
    <div class="card-body">
        <!-- Logo Centered -->
        <div class="app-brand d-flex justify-content-center mb-4">
            <a href="{{ url('/') }}">
                <img src="{{ asset('assets/img/wesarafi.svg') }}" class="app-brand-logo mx-auto d-block" alt="WeSarafi">
            </a>
        </div>

        <h4 class="mb-2 text-center">Forgot Password</h4>
        <p class="mb-4 text-center text-muted">
            No problem. Enter your email and we’ll send you a link to reset your password.
        </p>

        @if (session('status'))
            <div class="alert alert-success mb-3">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="bx bx-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="Email Address" required autofocus value="{{ old('email') }}">
                </div>
                @error('email')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary w-100">
                Send Password Reset Link
            </button>
        </form>
    </div>
</div>
@endsection
