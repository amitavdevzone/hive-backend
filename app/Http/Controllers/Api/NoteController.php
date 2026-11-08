<?php

namespace App\Http\Controllers\Api;

use App\Data\NoteData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreNoteRequest;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notes = $request->user()->notes()
            ->with('tags')
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->through(NoteData::fromModel(...));

        return response()->json($notes);
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        $user = $request->user();

        $note = $user->notes()->create($request->safe()->only(['title', 'text', 'format']));

        $note->tags()->sync(
            $request->safe()->collect('tags')
                ->map(fn (string $name): int => Tag::query()->firstOrCreate(['user_id' => $user->id, 'name' => $name])->id)
        );

        return response()->json(NoteData::fromModel($note->load('tags')), 201);
    }
}
