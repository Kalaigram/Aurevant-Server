<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Stock\DTOs\StockOpnameDTO;
use App\Domain\Stock\Repositories\StockOpnameRepositoryInterface;
use App\Models\StockOpname;
use Illuminate\Validation\ValidationException;

final class TransitionOpname
{
    public function __construct(
        private StockOpnameRepositoryInterface $opnames,
    ) {}

    public function submit(StockOpname $opname): StockOpnameDTO
    {
        return $this->move($opname, 'draft', 'diajukan');
    }

    public function reject(StockOpname $opname): StockOpnameDTO
    {
        return $this->move($opname, 'diajukan', 'ditolak');
    }

    private function move(StockOpname $opname, string $from, string $to): StockOpnameDTO
    {
        if ($opname->status !== $from) {
            throw ValidationException::withMessages([
                'status' => 'Opname harus berstatus '.$from.' (saat ini: '.$opname->status.').',
            ]);
        }

        $opname->status = $to;

        return StockOpnameDTO::fromModel($this->opnames->save($opname));
    }
}
