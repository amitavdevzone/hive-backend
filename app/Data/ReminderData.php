<?php

namespace App\Data;

use App\Enums\ReminderType;
use App\Models\Reminder;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
class ReminderData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $notes,
        public ReminderType $type,
        public ?CarbonInterface $remindAt,
        public ?int $intervalMinutes,
        public bool $isActive,
        public ?CarbonInterface $createdAt,
        public ?CarbonInterface $updatedAt,
    ) {}

    public static function fromModel(Reminder $reminder): self
    {
        return new self(
            $reminder->id,
            $reminder->title,
            $reminder->notes,
            $reminder->type,
            $reminder->remind_at,
            $reminder->interval_minutes,
            $reminder->is_active,
            $reminder->created_at,
            $reminder->updated_at,
        );
    }
}
