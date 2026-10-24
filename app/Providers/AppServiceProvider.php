<?php

namespace App\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
{
    $rtlLanguages = ['fa', 'ps'];
    View::composer('*', function ($view) use ($rtlLanguages) {
        $locale = App::getLocale();
        $isRtl = in_array($locale, $rtlLanguages);
        $view->with('isRtl', $isRtl);
        // Optional: define blade directives here
        Blade::directive('dirMargin', fn() => "<?php echo \$isRtl ? 'ms-2' : 'me-2'; ?>");
        Blade::directive('dirTextAlign', fn() => "<?php echo \$isRtl ? 'text-end' : 'text-start'; ?>");
    });
    View::share('currencyList', config('currencies.list'));
}

}
