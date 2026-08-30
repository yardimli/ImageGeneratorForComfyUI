<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Services\RenderJobService;
use Illuminate\Support\Facades\Log;
use Throwable;

class RenderQueueController extends Controller
{
    public function status(RenderJobService $renderJobs)
    {
        $result = $renderJobs->status();
        $this->logAjaxResponse('GET', request()->fullUrl(), 200, $result);

        return response()->json($result);
    }

    public function process(RenderJobService $renderJobs)
    {
        try {
            $result = $renderJobs->processNext();
            $status = $result['state'] === 'busy' ? 202 : 200;
            $this->logAjaxResponse('POST', request()->fullUrl(), $status, $result);

            return response()->json($result, $status);
        } catch (Throwable $exception) {
            report($exception);
            $result = [
                'state' => 'error',
                'message' => 'The render worker encountered an unexpected error.',
            ];
            $this->logAjaxResponse('POST', request()->fullUrl(), 500, $result, $exception->getMessage());

            return response()->json($result, 500);
        }
    }

    public function cancel(Prompt $prompt, RenderJobService $renderJobs)
    {
        $result = $renderJobs->cancel($prompt);
        $status = $result['state'] === 'cancelled' ? 200 : 409;
        $this->logAjaxResponse('POST', request()->fullUrl(), $status, $result);

        return response()->json($result, $status);
    }

    /** @param array<string, mixed> $response */
    private function logAjaxResponse(
        string $method,
        string $url,
        int $status,
        array $response,
        ?string $exception = null
    ): void {
        Log::channel('fal_ajax')->debug('Render queue AJAX response.', [
            'method' => $method,
            'url' => $url,
            'http_status' => $status,
            'response' => $response,
            'exception' => $exception,
        ]);
    }
}
