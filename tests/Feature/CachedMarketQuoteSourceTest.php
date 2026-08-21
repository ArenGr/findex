<?php

namespace Tests\Feature;

use App\Models\AutoInsuranceRequest;
use App\Services\Insurance\CachedMarketQuoteSource;
use App\Services\Insurance\InsuranceQuoteInputException;
use App\Services\Insurance\MarketQuoteDetails;
use App\Services\Insurance\MarketQuoteSourceInterface;
use App\Services\Insurance\QuoteIdentity;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The cache in front of the single quote source. Sil is the only upstream, so
 * the value here is not speed but volume - a refresh or a retry must not cost
 * another pair of calls. And the whole point of hashing the key is that the
 * plate and ID never reach the cache, so those get asserted too.
 */
class CachedMarketQuoteSourceTest extends TestCase
{
    private function request(int $term = 12): AutoInsuranceRequest
    {
        return new AutoInsuranceRequest(['locale' => 'en', 'contract_term_months' => $term]);
    }

    private function identity(string $plate = '01AA123', string $id = 'AN1234567'): QuoteIdentity
    {
        return new QuoteIdentity($plate, $id);
    }

    private function details(): MarketQuoteDetails
    {
        return new MarketQuoteDetails('+37400000000', 'a@example.com', '1234567890123456');
    }

    /**
     * A counting stub so we can prove how many times the inner source ran.
     * $returns is either the premium array to hand back, or a Throwable to
     * throw; public $calls records how often it was invoked.
     */
    private function countingInner(array|\Throwable $returns): MarketQuoteSourceInterface
    {
        return new class($returns) implements MarketQuoteSourceInterface
        {
            public int $calls = 0;

            public function __construct(private array|\Throwable $returns) {}

            public function premiums(AutoInsuranceRequest $r, QuoteIdentity $i, MarketQuoteDetails $d): array
            {
                $this->calls++;

                if ($this->returns instanceof \Throwable) {
                    throw $this->returns;
                }

                return $this->returns;
            }
        };
    }

    private function source(MarketQuoteSourceInterface $inner): CachedMarketQuoteSource
    {
        return new CachedMarketQuoteSource($inner, Cache::store('array'));
    }

    public function test_a_second_identical_request_is_served_from_cache(): void
    {
        config(['insurance.quote_cache_ttl' => 1800]);
        $inner = $this->countingInner(['liga-insurance' => '39000.00']);
        $source = $this->source($inner);

        $first = $source->premiums($this->request(), $this->identity(), $this->details());
        $second = $source->premiums($this->request(), $this->identity(), $this->details());

        $this->assertSame($first, $second);
        $this->assertSame(1, $inner->calls, 'the upstream should be called once, not twice');
    }

    public function test_a_different_vehicle_is_a_separate_entry(): void
    {
        config(['insurance.quote_cache_ttl' => 1800]);
        $inner = $this->countingInner(['liga-insurance' => '39000.00']);
        $source = $this->source($inner);

        $source->premiums($this->request(), $this->identity('01AA123', 'AN1111111'), $this->details());
        $source->premiums($this->request(), $this->identity('02BB456', 'AN2222222'), $this->details());

        $this->assertSame(2, $inner->calls);
    }

    public function test_the_bank_details_do_not_affect_the_key(): void
    {
        // Same vehicle, different bank account - still one upstream call,
        // because the account does not change the premium.
        config(['insurance.quote_cache_ttl' => 1800]);
        $inner = $this->countingInner(['liga-insurance' => '39000.00']);
        $source = $this->source($inner);

        $source->premiums($this->request(), $this->identity(), new MarketQuoteDetails('+37400000000', 'a@example.com', '1111111111111111'));
        $source->premiums($this->request(), $this->identity(), new MarketQuoteDetails('+37499999999', 'b@example.com', '2222222222222222'));

        $this->assertSame(1, $inner->calls);
    }

    public function test_the_raw_plate_and_id_never_land_in_the_cache(): void
    {
        config(['insurance.quote_cache_ttl' => 1800]);
        $inner = $this->countingInner(['liga-insurance' => '39000.00']);
        $source = $this->source($inner);

        $source->premiums($this->request(), $this->identity('01AA123', 'SECRETID99'), $this->details());

        // The key is the sha256 of the inputs, not the inputs - so a lookup
        // by the raw plate or ID finds nothing, and the hashed key does.
        $this->assertNull(Cache::store('array')->get('01AA123'));
        $this->assertNull(Cache::store('array')->get('SECRETID99'));

        $key = 'insurance:quote:v1:'.hash('sha256', '01AA123|SECRETID99|12');
        $this->assertSame(['liga-insurance' => '39000.00'], Cache::store('array')->get($key));
    }

    public function test_an_empty_result_is_not_cached(): void
    {
        config(['insurance.quote_cache_ttl' => 1800]);
        $inner = $this->countingInner([]);
        $source = $this->source($inner);

        $source->premiums($this->request(), $this->identity(), $this->details());
        $source->premiums($this->request(), $this->identity(), $this->details());

        // A transient empty must be retried, not remembered.
        $this->assertSame(2, $inner->calls);
    }

    public function test_an_input_exception_is_not_cached(): void
    {
        config(['insurance.quote_cache_ttl' => 1800]);
        $inner = $this->countingInner(new InsuranceQuoteInputException('bad id'));
        $source = $this->source($inner);

        try {
            $source->premiums($this->request(), $this->identity(), $this->details());
        } catch (InsuranceQuoteInputException) {
            // expected
        }

        try {
            $source->premiums($this->request(), $this->identity(), $this->details());
        } catch (InsuranceQuoteInputException) {
            // expected
        }

        $this->assertSame(2, $inner->calls, 'a rejection must be re-checked, not cached');
    }

    public function test_a_zero_ttl_disables_the_cache(): void
    {
        config(['insurance.quote_cache_ttl' => 0]);
        $inner = $this->countingInner(['liga-insurance' => '39000.00']);
        $source = $this->source($inner);

        $source->premiums($this->request(), $this->identity(), $this->details());
        $source->premiums($this->request(), $this->identity(), $this->details());

        $this->assertSame(2, $inner->calls);
    }
}
