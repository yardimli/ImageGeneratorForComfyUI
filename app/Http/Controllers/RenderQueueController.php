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
            $status = $result['state'] === 'busy' ? 202 : 200;

            return response()->json($result, $status);
        } catch (Throwable $exception) {
            report($exception);
            $result = [
                'state' => 'error',
                'message' => 'The render worker encountered an unexpected error.',
            ];

            return response()->json($result, 500);
        }
    }

    public function cancel(Prompt $prompt, RenderJobService $renderJobs)
    {
        $result = $renderJobs->cancel($prompt);
        $status = $result['state'] === 'cancelled' ? 200 : 409;

        return response()->json($result, $status);
    }
}
