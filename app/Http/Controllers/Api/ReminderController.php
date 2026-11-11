<?php

namespace App\Http\Controllers\Api;

use App\Data\ReminderData;
use App\Enums\ReminderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReminderRequest;
use App\Http\Requests\Api\UpdateReminderRequest;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

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

    public function show(Reminder $reminder): JsonResponse
    {
        Gate::authorize('view', $reminder);

        return response()->json(ReminderData::fromModel($reminder));
    }

    public function update(UpdateReminderRequest $request, Reminder $reminder): JsonResponse
    {
        Gate::authorize('update', $reminder);

        $reminder->fill($request->validated());

        if ($reminder->isDirty('type')) {
            $reminder->{$reminder->type === ReminderType::Once ? 'interval_minutes' : 'remind_at'} = null;
        }

        $reminder->save();

        return response()->json(ReminderData::fromModel($reminder->refresh()));
    }

    public function destroy(Reminder $reminder): Response
    {
        Gate::authorize('delete', $reminder);

        $reminder->delete();

        return response()->noContent();
    }
}
