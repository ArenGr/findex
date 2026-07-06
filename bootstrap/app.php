<?php

use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('scrape:rates')->daily()->withoutOverlapping();
        $schedule->command('scrape:mortgages')->daily()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'setlocale' => SetLocale::class,
            'banned' => EnsureUserIsNotBanned::class,
        ]);

        // Organization dashboard routes use the 'organization' guard; without
        // these, an unauthenticated hit on auth:organization would redirect to
        // the customer login instead of the org login (and vice versa for
        // guest:organization), since Laravel's defaults assume a single guard.
        //
        // Laravel's middleware priority sorting can run Authenticate (which
        // triggers this closure) before the 'setlocale' middleware has had a
        // chance to call URL::defaults(['locale' => ...]), so route() can't
        // rely on that default here - the {locale} route parameter is read
        // directly off the request instead, which is available regardless of
        // middleware order since it's bound during route matching.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('org.dashboard.*')
            ? route('org.login', ['locale' => $request->route('locale')])
            : route('login', ['locale' => $request->route('locale')]));

        $middleware->redirectUsersTo(fn (Request $request) => $request->routeIs('org.*')
            ? route('org.dashboard.index', ['locale' => $request->route('locale')])
            : route('home', ['locale' => $request->route('locale')]));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
