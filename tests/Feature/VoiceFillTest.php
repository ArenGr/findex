<?php

namespace Tests\Feature;

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VoiceFillTest extends TestCase
{
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
                        'all_inclusive' => null,
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
                        'all_inclusive' => null,
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
}
