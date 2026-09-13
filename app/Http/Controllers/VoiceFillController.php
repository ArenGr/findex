<?php

namespace App\Http\Controllers;

use App\Services\VoiceTripFillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class VoiceFillController extends Controller
{
    // Backs the "fill by voice" button on the trip request form.
    public function store(Request $request, VoiceTripFillService $voiceFill): JsonResponse
    {
        abort_unless(config('services.openai.voice_fill'), 404);

        $validator = Validator::make($request->all(), [
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
