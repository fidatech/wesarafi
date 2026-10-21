@extends('layouts.layoutBlank')

@section('title', 'Login')

@section('content')
<div class="container-xxl">
  <div class="authentication-wrapper authentication-basic container-p-y">
    <div class="authentication-inner">

      <!-- Login -->
      <div class="card px-sm-6 px-0">
        <div class="card-body">

          <!-- Logo -->
          <div class="app-brand justify-content-center mb-4" style="font-size: 2.5rem;">
            <a href="{{ url('/') }}" class="app-brand-link gap-2 d-flex align-items-center justify-content-center">
              <span class="app-brand-logo demo">
                <img src="{{ asset('assets/img/wesarafi.svg') }}" style="height: 50px;" />
              </span>
            </a>
          </div>

          <h4 class="mb-1">Welcome to WeSarafi! 👋</h4>
          <p class="mb-4">Please sign-in to your account and start the adventure</p>

          <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bx bx-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email or username" required autofocus />
                </div>
            </div>

            <div class="mb-4 form-password-toggle">
                <div class="input-group">
                    <span class="input-group-text"><i class="bx bx-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                </div>
            </div>

            <div class="mb-4 d-flex justify-content-between">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" />
                <label class="form-check-label" for="remember"> Remember me </label>
              </div>
              <a href="{{ route('password.request') }}">Forgot Password?</a>
            </div>

            <div class="mb-4">
              <button type="submit" class="btn btn-primary d-grid w-100">Login</button>
            </div>
          </form>

          {{-- <p class="text-center">
            <span>New on our platform?</span>
            <a href="{{ route('register') }}">Create an account</a>
          </p>

          <div class="divider my-4"><div class="divider-text">or</div></div>

          <div class="d-flex justify-content-center">
            <a href="#" class="btn btn-icon btn-facebook me-2"><i class="bx bxl-facebook"></i></a>
            <a href="#" class="btn btn-icon btn-twitter me-2"><i class="bx bxl-twitter"></i></a>
            <a href="#" class="btn btn-icon btn-github me-2"><i class="bx bxl-github"></i></a>
            <a href="#" class="btn btn-icon btn-google"><i class="bx bxl-google"></i></a>
          </div> --}}

        </div>
      </div>
      <!-- /Login -->
    </div>
  </div>
</div>
@endsection
