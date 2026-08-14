<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RateType;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Shared input handling for the public API.
 *
 * Both parameters here name things by their public identifier - "USD", "cash" -
 * rather than by primary key. Ids are an implementation detail and would tie a
 * customer's integration to our row numbering.
 */
abstract class ApiController extends Controller
{
    protected function currencyFromRequest(Request $request, bool $required = true): ?Currency
    {
        $code = $request->query('currency');

        if ($code === null) {
            if ($required) {
                throw ValidationException::withMessages([
                    'currency' => 'A currency code is required, for example USD.',
                ]);
            }

            return null;
        }

        $currency = Currency::where('code', strtoupper((string) $code))->where('is_active', true)->first();

        if ($currency === null) {
            throw ValidationException::withMessages([
                'currency' => "Unknown or inactive currency [{$code}].",
            ]);
        }

        return $currency;
    }

    protected function rateTypeFromRequest(Request $request): RateType
    {
        $type = $request->query('type');

        if ($type === null) {
            return RateType::CASH;
        }

        $resolved = collect(RateType::cases())->first(fn (RateType $case) => $case->value === $type);

        if ($resolved === null) {
            throw ValidationException::withMessages([
                'type' => "Unknown rate type [{$type}].",
            ]);
        }

        return $resolved;
    }
}
