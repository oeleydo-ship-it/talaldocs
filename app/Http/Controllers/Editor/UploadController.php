<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Concerns\ResolvesWorkspaceProject;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    use ResolvesWorkspaceProject;

    public function store(Request $request, int $project): JsonResponse
    {
        $model = $this->project($request, $project);
        $this->authorize('update', $model);

        $data = $request->validate([
            'file' => ['required', 'image', 'max:5120'],
        ]);

        $path = $data['file']->store('uploads/'.$model->id, 'public');

        return response()->json([
            'url' => '/storage/'.$path,
        ]);
    }
}
