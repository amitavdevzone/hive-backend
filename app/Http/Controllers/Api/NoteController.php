<?php

namespace App\Http\Controllers\Api;

use App\Data\NoteData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreNoteRequest;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notes = $request->user()->notes()
            ->with('tags')
            ->when($request->string('search')->trim()->toString(), function (Builder $query, string $search): void {
                $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';

                $query->where(fn (Builder $query) => $query
                    ->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereRaw("LOWER(text) LIKE ? ESCAPE '!'", [$needle]));
            })
            ->when($request->string('tag')->trim()->toString(), fn (Builder $query, string $tag) => $query
                ->whereHas('tags', fn (Builder $query) => $query->where('name', mb_strtolower($tag))))
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
