<?php

namespace App\Http\Controllers;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\RateAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RateAlertController extends Controller
{
    public function index(string $locale, Request $request): View
    {
        $user = $request->user();

        // A connect link is only useful before the user has linked their
        // chat - generate one lazily (same pattern as
        // Organization\TourismController::index) so the page always has a
        // live link to show, without a separate "generate" step for the
        // common case. The locale is refreshed on every visit (not just set
        // once) so the bot's eventual confirmation message matches whatever
        // language they're browsing in *when they actually connect*, not
        // whatever it was the first time this page happened to run.
        if (!$user->telegram_chat_id) {
            $user->update([
                'telegram_connect_token' => $user->telegram_connect_token ?? Str::random(32),
                'locale' => $locale,
            ]);
        }

        $alerts = $user->rateAlerts()
            ->with(['currency', 'organization'])
            ->latest()
            ->get();

        return view('alerts.index', [
            'alerts' => $alerts,
            'currencies' => Currency::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'organizations' => Organization::active()->orderBy('name')->get(),
            'rateTypes' => RateType::cases(),
            'botUsername' => config('services.telegram.bot_username'),
        ]);
    }

    public function store(string $locale, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            // Scoped to active orgs, not just Rule::in the index() dropdown:
            // the dropdown only ever offers active orgs, but without this an
            // alert could still be pinned to a deactivated one via a direct
            // POST - and CheckRateAlerts only matches active orgs, so it
            // would then silently never fire.
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')->where('is_active', true)],
            'rate_type' => ['required', Rule::in(array_column(RateType::cases(), 'value'))],
            'rate_field' => ['required', Rule::in(['buy_rate', 'sell_rate'])],
            'direction' => ['required', Rule::in(['above', 'below'])],
            'threshold' => ['required', 'numeric', 'min:0'],
            'channel' => ['required', Rule::in(['email', 'telegram'])],
        ]);

        // The chat ID always comes from the user's own connected Telegram
        // account (see index()'s connect-link generation), never typed into
        // the form - a raw numeric chat ID isn't something a visitor could
        // reasonably know, and a stale/mistyped one would silently break
        // delivery. Guarded here too, not just hidden client-side in the
        // view, since "connect first" is enforced server-side either way.
        if ($validated['channel'] === 'telegram' && !$request->user()->telegram_chat_id) {
            return back()->withInput()->withErrors([
                'channel' => __('alerts.form.telegram_not_connected_error'),
            ]);
        }

        $validated['telegram_chat_id'] = $validated['channel'] === 'telegram' ? $request->user()->telegram_chat_id : null;

        $request->user()->rateAlerts()->create($validated);

        return redirect()->route('alerts.index')->with('status', 'alert-created');
    }

    /**
     * Resolved manually (not via implicit route-model binding), same reason
     * as OrganizationController: implicit binding doesn't resolve correctly
     * for a route parameter coming right after the dynamic {locale} prefix.
     * Also enforces that the alert belongs to the current user.
     */
    public function toggle(string $locale, Request $request, string $rateAlert): RedirectResponse
    {
        $alert = RateAlert::where('id', $rateAlert)->where('user_id', $request->user()->id)->firstOrFail();
        $alert->update(['is_active' => !$alert->is_active]);

        return redirect()->route('alerts.index');
    }

    public function destroy(string $locale, Request $request, string $rateAlert): RedirectResponse
    {
        $alert = RateAlert::where('id', $rateAlert)->where('user_id', $request->user()->id)->firstOrFail();
        $alert->delete();

        return redirect()->route('alerts.index')->with('status', 'alert-deleted');
    }
}
