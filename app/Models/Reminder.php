<?php

namespace App\Models;

use App\Enums\ReminderType;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $notes
 * @property ReminderType $type
 * @property Carbon|null $remind_at
 * @property int|null $interval_minutes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'title', 'notes', 'type', 'remind_at', 'interval_minutes', 'is_active'])]
class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReminderType::class,
            'remind_at' => 'datetime',
            'interval_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Normalise to UTC, otherwise Laravel drops the client's offset and stores the wall-clock time.
     *
     * @return Attribute<Carbon|null, \DateTimeInterface|string|null>
     */
    protected function remindAt(): Attribute
    {
        return Attribute::set(fn (\DateTimeInterface|string|null $value) => $value === null ? null : Carbon::parse($value)->utc());
    }
}
