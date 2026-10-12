<?php

declare(strict_types=1);

namespace App\Application\Import\DTOs;

final readonly class ImportResult
{
    /**
     * @param  list<array{row: int, errors: list<string>}>  $errors
     */
    public function __construct(
        public int $imported,
        public array $errors,
    ) {}

    public function failed(): bool
    {
        return $this->errors !== [];
    }
}
