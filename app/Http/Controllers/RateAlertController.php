<?php

namespace App\Http\Controllers;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\RateAlert;
use App\Support\SafeRedirectUrl;
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

        if (! $user->telegram_chat_id) {
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
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')->where('is_active', true)],
            'rate_type' => ['required', Rule::in(array_column(RateType::cases(), 'value'))],
            'rate_field' => ['required', Rule::in(['buy_rate', 'sell_rate'])],
            'direction' => ['required', Rule::in(['above', 'below'])],
            'threshold' => ['required', 'numeric', 'min:0'],
            'channel' => ['required', Rule::in(['email', 'telegram', 'viber'])],
        ]);

        if ($validated['channel'] === 'telegram' && ! $request->user()->telegram_chat_id) {
            return back()->withInput()->withErrors([
                'channel' => __('alerts.form.telegram_not_connected_error'),
            ]);
        }

        if ($validated['channel'] === 'viber' && ! $request->user()->viber_chat_id) {
            return back()->withInput()->withErrors([
                'channel' => __('alerts.form.viber_not_connected_error'),
            ]);
        }

        $validated['telegram_chat_id'] = $validated['channel'] === 'telegram' ? $request->user()->telegram_chat_id : null;
        $validated['viber_chat_id'] = $validated['channel'] === 'viber' ? $request->user()->viber_chat_id : null;

        $request->user()->rateAlerts()->create($validated);

        return $this->afterStore($request)->with('status', 'alert-created');
    }

    private function afterStore(Request $request): RedirectResponse
    {
        return redirect()->to(SafeRedirectUrl::resolve(
            $request,
            $request->input('return_to'),
            route('alerts.index'),
        ));
    }

    public function toggle(string $locale, Request $request, string $rateAlert): RedirectResponse
    {
        $alert = RateAlert::where('id', $rateAlert)->where('user_id', $request->user()->id)->firstOrFail();
        $alert->update(['is_active' => ! $alert->is_active]);

        return redirect()->route('alerts.index');
    }

    public function destroy(string $locale, Request $request, string $rateAlert): RedirectResponse
    {
        $alert = RateAlert::where('id', $rateAlert)->where('user_id', $request->user()->id)->firstOrFail();
        $alert->delete();

        return redirect()->route('alerts.index')->with('status', 'alert-deleted');
    }

    public function disconnectTelegram(string $locale, Request $request): RedirectResponse
    {
        $request->user()->update(['telegram_chat_id' => null]);

        return redirect()->route('alerts.index')->with('status', 'telegram-disconnected');
    }

    public function connectViber(string $locale, Request $request): RedirectResponse
    {
        $request->user()->update(['viber_chat_id' => 'demo-viber-'.Str::random(12)]);

        return redirect()->route('alerts.index')->with('status', 'viber-connected');
    }

    public function disconnectViber(string $locale, Request $request): RedirectResponse
    {
        $request->user()->update(['viber_chat_id' => null]);

        return redirect()->route('alerts.index')->with('status', 'viber-disconnected');
    }
}
