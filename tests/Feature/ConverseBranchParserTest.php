<?php

namespace Tests\Feature;

use App\Parsers\ConverseBranchParser;
use Tests\TestCase;

class ConverseBranchParserTest extends TestCase
{
    /**
     * Trimmed from https://sapi.conversebank.am/api/v2/branches. The real
     * response holds 201 locations, of which only 37 are branches - the rest
     * are ATMs and payment terminals standing in supermarkets and clinics,
     * separated from the branches by `type` alone.
     */
    private function fixture(): string
    {
        return <<<'JSON'
        [
            {"title":"Khudyakov 161/2","branch":"\"Avan\" branch","type":"1","status":"1",
             "body":"<p>Mon. - Fri.: 9:15-17:30</p>","lat":40.216063,"lng":44.579462},
            {"title":"Republic Square","branch":"Head office","type":"1","status":"1",
             "body":"<p>Mon. - Fri.: 9:15-17:30</p>","lat":40.1781093,"lng":44.512965},
            {"title":"Airport waiting lounge","branch":"\"Shirak\" branch","type":"1","status":"1",
             "body":"<p>Mon. - Sun.: around the clock</p>","lat":40.751744,"lng":43.85861},
            {"title":"191/1 Bashinjaghyan","branch":"Outside of \"Emmy\" florist's","type":"2","status":"1",
             "body":"<p>Mon. - Sun.: around the clock</p>","lat":40.2,"lng":44.4},
            {"title":"50/1 Ashtarak Highway","branch":"\"Gratsia\" medical centre","type":"3","status":"1",
             "body":"<p>Mon. - Fri.: 9:15-17:30</p>","lat":40.23,"lng":44.43},
            {"title":"Closed branch","branch":"\"Old\" branch","type":"1","status":"0",
             "body":"<p>Mon. - Fri.: 9:15-17:30</p>","lat":40.1,"lng":44.5},
            {"title":"No coordinates","branch":"\"Nowhere\" branch","type":"1","status":"1",
             "body":null,"lat":0,"lng":0}
        ]
        JSON;
    }

    /** @return array<int, array<string, mixed>> */
    private function parse(?string $json = null): array
    {
        return (new ConverseBranchParser)->parse($json ?? $this->fixture());
    }

    /**
     * The one that matters: 164 of the 201 rows are cash machines. Taking
     * them as branches would roughly quintuple the bank's apparent footprint
     * and send people to a florist's to change money.
     */
    public function test_it_keeps_only_branches_and_not_the_atms_beside_them(): void
    {
        $addresses = array_column($this->parse(), 'address');

        $this->assertContains('Khudyakov 161/2', $addresses);
        $this->assertNotContains('191/1 Bashinjaghyan', $addresses, 'An ATM was stored as a branch.');
        $this->assertNotContains('50/1 Ashtarak Highway', $addresses);
    }

    public function test_it_leaves_out_a_branch_the_bank_has_marked_inactive(): void
    {
        $this->assertNotContains('Closed branch', array_column($this->parse(), 'address'));
    }

    public function test_it_reads_the_name_address_hours_and_position(): void
    {
        $branch = $this->parse()[0];

        $this->assertSame('Avan branch', $branch['name'], 'The quotes around the name were kept.');
        $this->assertSame('Khudyakov 161/2', $branch['address']);
        $this->assertSame(40.216063, $branch['latitude']);
        $this->assertSame(['09:15', '17:30'], $branch['opening_hours']['mon']);
        $this->assertNull($branch['opening_hours']['sun']);
    }

    public function test_it_reads_a_branch_that_never_closes(): void
    {
        $airport = array_values(array_filter($this->parse(), fn ($b) => $b['address'] === 'Airport waiting lounge'))[0];

        $this->assertSame(['00:00', '23:59'], $airport['opening_hours']['sun']);
    }

    /**
     * A zero coordinate is a missing value, not a location - stored as one
     * it drops the branch into the Gulf of Guinea on the nearby-branch map.
     */
    public function test_it_treats_a_zero_coordinate_as_no_coordinate(): void
    {
        $nowhere = array_values(array_filter($this->parse(), fn ($b) => $b['address'] === 'No coordinates'))[0];

        $this->assertNull($nowhere['latitude']);
        $this->assertNull($nowhere['longitude']);
        $this->assertNull($nowhere['opening_hours']);
    }

    public function test_it_returns_nothing_when_the_endpoint_answers_with_something_other_than_json(): void
    {
        $this->assertSame([], $this->parse('<html>502</html>'));
        $this->assertSame([], $this->parse(''));
    }
}
