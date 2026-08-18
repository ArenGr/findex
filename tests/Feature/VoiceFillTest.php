<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VoiceFillTest extends TestCase
{
    // The endpoint tests never touch the database; the one that renders the
    // form does.
    use RefreshDatabase;

    /**
     * The concierge is hidden behind a flag, not deleted, so everything below
     * still describes how it behaves - with the flag on, which is the state
     * these tests are about.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.voice_fill' => true]);
    }

    private function fakeRecording(): File
    {
        return UploadedFile::fake()->create('trip-request.webm', 10, 'audio/webm');
    }

    public function test_a_recording_is_transcribed_and_extracted_into_trip_fields(): void
    {
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'I want to go to Thailand for two adults, checking in September 1st 2026 and out September 10th.',
            ]),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'destination_country' => 'TH',
                        'check_in' => '2026-09-01',
                        'check_out' => '2026-09-10',
                        'adults' => 2,
                        'children' => null,
                        'departure_location' => null,
                        'flight_preference' => null,
                        'hotel_preference' => null,
                        'meal_preference' => null,
                        'insurance' => null,
                        'hotel_name' => null,
                        'budget_min_amd' => null,
                        'budget_max_amd' => null,
                        'notes' => null,
                    ])]],
                ],
            ]),
        ]);

        $response = $this->post('/en/tourism/voice-fill', ['audio' => $this->fakeRecording()]);

        $response->assertOk();
        $response->assertJson([
            'found' => true,
            'destination_country' => 'TH',
            'check_in' => '2026-09-01',
            'check_out' => '2026-09-10',
            'adults' => 2,
        ]);
    }

    public function test_a_country_code_the_extraction_model_invents_is_dropped_rather_than_trusted(): void
    {
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'somewhere nice']),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['destination_country' => 'ZZ'])]],
                ],
            ]),
        ]);

        $response = $this->post('/en/tourism/voice-fill', ['audio' => $this->fakeRecording()]);

        $response->assertOk();
        $response->assertJson(['destination_country' => null, 'found' => false]);
    }

    public function test_a_transcript_with_no_real_trip_details_reports_found_false(): void
    {
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'um, is this thing on? testing, testing.']),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'destination_country' => null,
                        'check_in' => null,
                        'check_out' => null,
                        'adults' => null,
                        'children' => null,
                        'departure_location' => null,
                        'flight_preference' => null,
                        'hotel_preference' => null,
                        'meal_preference' => null,
                        'insurance' => null,
                        'hotel_name' => null,
                        'budget_min_amd' => null,
                        'budget_max_amd' => null,
                        'notes' => null,
                    ])]],
                ],
            ]),
        ]);

        $response = $this->post('/en/tourism/voice-fill', ['audio' => $this->fakeRecording()]);

        $response->assertOk();
        $response->assertJson(['found' => false]);
    }

    public function test_a_failed_transcription_returns_a_friendly_error_instead_of_a_server_error(): void
    {
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(['error' => 'boom'], 500),
        ]);

        $response = $this->post('/en/tourism/voice-fill', ['audio' => $this->fakeRecording()]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
    }

    public function test_an_empty_transcript_returns_a_friendly_error_without_calling_the_extraction_model(): void
    {
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => '   ']),
        ]);

        $response = $this->post('/en/tourism/voice-fill', ['audio' => $this->fakeRecording()]);

        $response->assertStatus(422);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions');
    }

    public function test_a_non_audio_upload_is_rejected_by_validation(): void
    {
        Http::fake();

        $response = $this->post('/en/tourism/voice-fill', [
            'audio' => UploadedFile::fake()->create('not-audio.pdf', 10, 'application/pdf'),
        ]);

        $response->assertStatus(422);
        Http::assertNothingSent();
    }

    /**
     * Hidden means off, not merely invisible. This endpoint spends money per
     * call, so leaving it reachable while the card is gone would be a button
     * nobody can see and anybody can press.
     */
    public function test_the_endpoint_is_gone_while_the_concierge_is_hidden(): void
    {
        config(['services.openai.voice_fill' => false]);

        Http::fake();

        $this->post('/en/tourism/voice-fill', ['audio' => $this->fakeRecording()])
            ->assertNotFound();

        // And it never reached the paid API to find that out.
        Http::assertNothingSent();
    }

    /** The card goes with it, and takes nothing else off the form. */
    public function test_the_card_follows_the_flag(): void
    {
        config(['services.openai.voice_fill' => false]);
        $this->get('/en/tourism')
            ->assertOk()
            ->assertDontSee('AI Travel Concierge')
            // The form it sits on is untouched.
            ->assertSee('name="adults"', false);

        config(['services.openai.voice_fill' => true]);
        $this->get('/en/tourism')->assertOk()->assertSee('AI Travel Concierge');
    }
}
