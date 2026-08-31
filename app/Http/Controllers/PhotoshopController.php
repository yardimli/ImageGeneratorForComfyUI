<?php

namespace App\Http\Controllers;

use App\Models\PhotoshopLayer;
use App\Models\PhotoshopProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PhotoshopController extends Controller
{
    public function index(): View
    {
        return view('photoshop.index');
    }

    public function projects(Request $request): JsonResponse
    {
        return response()->json(['projects' => PhotoshopProject::where('user_id', $request->user()->id)->with('layers')->latest('updated_at')->get()]);
    }

    public function show(Request $request, PhotoshopProject $project): JsonResponse
    {
        $this->ensureOwner($request, $project);
        return response()->json(['project' => $project->load('layers')]);
    }

    public function storeProject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'width' => ['required', 'integer', 'min:1', 'max:8192'],
            'height' => ['required', 'integer', 'min:1', 'max:8192'],
            'blank_layer' => ['sometimes', 'boolean'],
        ]);
        $project = PhotoshopProject::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        if ($request->boolean('blank_layer', true)) {
            $path = "photoshop/{$request->user()->id}/{$project->id}/" . Str::uuid() . '.png';
            Storage::disk('public')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL6WQAAAABJRU5ErkJggg=='));
            $project->layers()->create(['name' => 'Layer 1', 'file_path' => $path, 'width' => $project->width, 'height' => $project->height, 'visible' => true, 'opacity' => 100]);
        }

        return response()->json(['project' => $project->load('layers')], 201);
    }

    public function updateProject(Request $request, PhotoshopProject $project): JsonResponse
    {
        $this->ensureOwner($request, $project);
        $project->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'width' => ['sometimes', 'integer', 'min:1', 'max:8192'],
            'height' => ['sometimes', 'integer', 'min:1', 'max:8192'],
        ]));
        return response()->json(['project' => $project->fresh('layers')]);
    }

    public function storeLayer(Request $request, PhotoshopProject $project): JsonResponse
    {
        $this->ensureOwner($request, $project);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'image' => ['required', 'string'],
            'x' => ['nullable', 'numeric'], 'y' => ['nullable', 'numeric'],
            'width' => ['required', 'numeric', 'min:1', 'max:8192'],
            'height' => ['required', 'numeric', 'min:1', 'max:8192'],
        ]);
        $png = $this->decodePng($validated['image']);
        $path = "photoshop/{$request->user()->id}/{$project->id}/" . Str::uuid() . '.png';
        Storage::disk('public')->put($path, $png);
        $layer = $project->layers()->create([
            'name' => $validated['name'], 'file_path' => $path,
            'x' => $validated['x'] ?? 0, 'y' => $validated['y'] ?? 0,
            'width' => $validated['width'], 'height' => $validated['height'],
            'rotation' => 0, 'opacity' => 100, 'visible' => true,
            'z_index' => ((int) $project->layers()->max('z_index')) + 1,
        ]);
        $project->touch();
        return response()->json(['layer' => $layer->refresh()], 201);
    }

    public function updateLayer(Request $request, PhotoshopLayer $layer): JsonResponse
    {
        $this->ensureOwner($request, $layer->project);
        $layer->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'x' => ['sometimes', 'numeric'], 'y' => ['sometimes', 'numeric'],
            'width' => ['sometimes', 'numeric', 'min:1', 'max:8192'],
            'height' => ['sometimes', 'numeric', 'min:1', 'max:8192'],
            'rotation' => ['sometimes', 'numeric', 'between:-3600,3600'],
            'opacity' => ['sometimes', 'integer', 'between:0,100'],
            'visible' => ['sometimes', 'boolean'], 'z_index' => ['sometimes', 'integer'],
        ]));
        $layer->project->touch();
        return response()->json(['layer' => $layer->fresh()]);
    }

    private function ensureOwner(Request $request, PhotoshopProject $project): void
    {
        abort_unless($project->user_id === $request->user()->id, 404);
    }

    private function decodePng(string $dataUrl): string
    {
        if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) abort(422, 'Layers must be PNG files.');
        $decoded = base64_decode($matches[1], true);
        if ($decoded === false || strlen($decoded) > 25 * 1024 * 1024 || !str_starts_with($decoded, "\x89PNG\r\n\x1a\n")) abort(422, 'The layer PNG is invalid or too large.');
        return $decoded;
    }
}
