<?php

declare(strict_types=1);

namespace App\Application\Report\DTOs;

final readonly class ReportExportDocument
{
    /**
     * @param  list<array{label: string, value: int|float|string, type: 'money'|'number'|'text'}>  $summary
     * @param  list<array{title: string, headings: list<string>, types: list<'money'|'number'|'text'>, rows: list<list<int|float|string>>}>  $sections
     */
    public function __construct(
        public string $title,
        public string $periodLabel,
        public string $filename,
        public ?string $branchName,
        public array $summary,
        public array $sections,
    ) {}

    public function isWide(): bool
    {
        $columns = 0;

        foreach ($this->sections as $section) {
            $columns = max($columns, count($section['headings']));
        }

        return $columns > 6;
    }

    public function columnCount(): int
    {
        $columns = 2;

        foreach ($this->sections as $section) {
            $columns = max($columns, count($section['headings']));
        }

        return $columns;
    }
}
