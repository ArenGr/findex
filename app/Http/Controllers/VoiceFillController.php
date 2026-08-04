<?php

namespace App\Http\Controllers;

use App\Services\VoiceTripFillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class VoiceFillController extends Controller
{
    /**
     * Backs the "fill by voice" button on the trip request form. Takes a
     * short in-browser audio recording, returns the trip fields extracted
     * from it as JSON, and the page's own JS fills the actual form inputs -
     * this endpoint never touches QuoteRequest itself, so a bad or empty
     * extraction just leaves the form as the visitor left it.
     *
     * Validated by hand rather than via $request->validate() - this route
     * isn't under api/*, and bootstrap/app.php's shouldRenderJsonWhen only
     * turns a ValidationException into JSON for those, so the framework's
     * default handling would redirect a failure back to the page instead of
     * returning JSON, which the frontend's fetch() call can't act on.
     */
    public function store(Request $request, VoiceTripFillService $voiceFill): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // Short recordings only - MediaRecorder output on the frontend
            // is capped at 60 seconds, so anything near this limit already
            // indicates a non-recording upload.
            'audio' => ['required', 'file', 'max:10240', 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/wav,audio/x-wav,video/webm'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        try {
            $fields = $voiceFill->fillFromAudio($validator->validated()['audio']);
        } catch (RuntimeException) {
            return response()->json([
                'message' => __('tourism.request.voice_fill_error'),
            ], 422);
        }

        return response()->json($fields);
    }
}
