<?php

namespace App\Http\Controllers\Api;

use App\Data\ReminderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReminderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reminders = $request->user()->reminders()
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->latest('id')
            ->paginate(15)
            ->through(ReminderData::fromModel(...));

        return response()->json($reminders);
    }

    public function store(StoreReminderRequest $request): JsonResponse
    {
        $reminder = $request->user()->reminders()->create($request->validated());

        return response()->json(ReminderData::fromModel($reminder->refresh()), 201);
    }
}
