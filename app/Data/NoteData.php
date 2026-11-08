<?php

namespace App\Data;

use App\Enums\NoteFormat;
use App\Models\Note;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
class NoteData extends Data
{
    /**
     * @param  array<int, TagData>  $tags
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $text,
        public NoteFormat $format,
        #[DataCollectionOf(TagData::class)]
        public array $tags,
        public ?CarbonInterface $createdAt,
        public ?CarbonInterface $updatedAt,
    ) {}

    /**
     * Expects the `tags` relation to be loaded.
     */
    public static function fromModel(Note $note): self
    {
        return new self(
            $note->id,
            $note->title,
            $note->text,
            $note->format,
            $note->tags->map(TagData::fromModel(...))->all(),
            $note->created_at,
            $note->updated_at,
        );
    }
}
