<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Services\RenderJobService;
use Throwable;

class RenderQueueController extends Controller
{
    public function status(RenderJobService $renderJobs)
    {
        return response()->json($renderJobs->status());
    }

    public function process(RenderJobService $renderJobs)
    {
        try {
            $result = $renderJobs->processNext();

            return response()->json($result, $result['state'] === 'busy' ? 202 : 200);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'state' => 'error',
                'message' => 'The render worker encountered an unexpected error.',
            ], 500);
        }
    }

    public function cancel(Prompt $prompt, RenderJobService $renderJobs)
    {
        $result = $renderJobs->cancel($prompt);

        return response()->json($result, $result['state'] === 'cancelled' ? 200 : 409);
    }
}
