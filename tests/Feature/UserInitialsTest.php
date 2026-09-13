<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// The initials that stand in for a face beside the signed-in user's name.
class UserInitialsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function names(): array
    {
        return [
            'first and last' => ['Aren Grigoryan', 'a@example.com', 'AG'],
            // Armenian is as common here as Latin, and substr() would cut a multi-byte character in half.
            'armenian' => ['Արեն Գրիգորյան', 'b@example.com', 'ԱԳ'],
            'single word' => ['Cher', 'c@example.com', 'C'],
            'middle names are skipped' => ['Mary Jane Watson', 'd@example.com', 'MW'],
            'untidy whitespace' => ["  Aren   Grigoryan \n", 'e@example.com', 'AG'],
            'lowercase is raised' => ['aren grigoryan', 'f@example.com', 'AG'],
            // An OAuth callback can create an account with no name at all.
            'no name falls back to the email' => ['', 'zoe@example.com', 'Z'],
        ];
    }

    #[DataProvider('names')]
    public function test_initials_are_derived_from_the_name(string $name, string $email, string $expected): void
    {
        $user = new User(['name' => $name, 'email' => $email]);

        $this->assertSame($expected, $user->initials());
    }

    public function test_the_header_shows_the_initials_next_to_the_name(): void
    {
        $user = User::factory()->create(['name' => 'Aren Grigoryan', 'avatar' => null]);

        $this->actingAs($user)->get('/en')->assertOk()->assertSee('AG');
    }

    /** A user who signed in with Google has a real picture, so use it. */
    public function test_an_avatar_url_is_used_instead_of_the_initials(): void
    {
        $user = User::factory()->create([
            'name' => 'Aren Grigoryan',
            'avatar' => 'https://example.com/photo.jpg',
        ]);

        $this->actingAs($user)->get('/en')->assertOk()->assertSee('https://example.com/photo.jpg', false);
    }
}
