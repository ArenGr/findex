<?php
namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmpDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug(): void
    {
        $this->mock(TelegramClient::class, fn ($m) => $m->shouldReceive('sendMessage')->andReturn(['ok' => true, 'result' => ['message_id' => 1]]));

        $o = Organization::create(['name'=>'A','slug'=>'a','type'=>'tourism','country_code'=>'AM','is_active'=>true,'telegram_chat_id'=>'1']);
        $o->tourismDestinations()->create(['country_code'=>'GE']);
        User::factory()->organization($o)->create();

        $this->withoutExceptionHandling();

        $r = $this->post(route('tourism.request.store', ['locale' => 'en']), [
            'departure_location' => 'Yerevan',
            'destination_countries' => ['GE'],
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'consent' => '1',
        ]);

        fwrite(STDERR, "STATUS: ".$r->getStatusCode()."\n");
        $this->assertTrue(true);
    }
}
