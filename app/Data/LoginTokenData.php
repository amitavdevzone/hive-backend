<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
class LoginTokenData extends Data
{
    public function __construct(
        public string $accessToken,
        public string $tokenType = 'Bearer',
    ) {}
}
