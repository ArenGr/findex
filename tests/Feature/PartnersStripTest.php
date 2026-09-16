<?php

namespace Tests\Feature;

use App\Support\TravelPartners;
use Tests\TestCase;

class PartnersStripTest extends TestCase
{
    public function test_names_are_shortened_to_what_tells_them_apart(): void
    {
        $this->assertSame('INGO', TravelPartners::short('INGO Armenia'));
        $this->assertSame('Liga', TravelPartners::short('Liga Insurance'));
        $this->assertSame('Armenia', TravelPartners::short('Armenia Insurance'));
        $this->assertSame('Sky', TravelPartners::short('Sky Travel Agency LLC'));
        $this->assertSame('VTB', TravelPartners::short('VTB Bank (Armenia)'));
        $this->assertSame('Ameriabank', TravelPartners::short('Ameriabank'));
    }
}
