@php
    $routeName = request()->route()?->getName();
    $segments = collect(explode('.', (string) $routeName))->filter()
        ->reject(fn ($segment) => in_array($segment, ['index', 'data', 'store', 'show', 'edit', 'update', 'destroy'], true));
    $pageTitle = trim($__env->yieldContent('title')) ?: $segments->last();
@endphp
<div class="app-page-header no-print">
    <nav class="app-breadcrumb" aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="bx bx-home-alt"></i> {{ __('Dashboard') }}</a></li>
            @foreach($segments as $segment)
                <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}" @if($loop->last) aria-current="page" @endif>{{ __((string) str($segment)->replace(['-', '_'], ' ')->title()) }}</li>
            @endforeach
            @if($segments->isEmpty())<li class="breadcrumb-item active" aria-current="page">{{ __($pageTitle) }}</li>@endif
        </ol>
    </nav>
    <div class="app-page-actions" id="app-page-actions">
        @yield('breadcrumb-actions')
    </div>
</div>
