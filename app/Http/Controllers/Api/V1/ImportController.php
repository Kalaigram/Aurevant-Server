<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Import\UseCases\ExportItems;
use App\Application\Import\UseCases\ImportFile;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use League\Csv\Reader;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function template(Company $company, string $type): StreamedResponse
    {
        $headers = match ($type) {
            'items' => ['code', 'name', 'type', 'unit', 'category', 'branch_code', 'price'],
            'stock' => ['item_code', 'branch_code', 'qty', 'unit_cost'],
            'suppliers' => ['name', 'phone', 'address'],
            'employees' => ['name', 'email', 'phone', 'branch_code', 'role', 'password', 'ptkp_status'],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($headers): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fclose($out);
        }, "template-{$type}.csv", ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request, Company $company, string $type, ImportFile $useCase): JsonResponse
    {
        if (! in_array($type, ['items', 'stock', 'suppliers', 'employees'], true)) {
            abort(404);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:2048'],
            'dry_run' => ['sometimes', 'boolean'],
            'delimiter' => ['sometimes', 'string', Rule::in([',', ';'])],
        ]);

        try {
            $reader = Reader::createFromPath($validated['file']->getRealPath());
            $reader->setDelimiter($validated['delimiter'] ?? ',');
            $reader->setHeaderOffset(0);
            $rows = array_map(
                fn ($record) => array_map(fn ($value) => $value === null ? null : (string) $value, $record),
                iterator_to_array($reader->getRecords())
            );
        } catch (\Throwable) {
            return ApiResponse::error('File tidak bisa dibaca sebagai CSV.', null, 422);
        }

        if ($rows === []) {
            return ApiResponse::error('File kosong atau tanpa baris data.', null, 422);
        }

        $result = $useCase($company, $type, array_values($rows), (bool) ($validated['dry_run'] ?? false));

        if ($result->failed()) {
            return ApiResponse::error('Impor gagal validasi.', ['rows' => $result->errors], 422);
        }

        return ApiResponse::success(
            ['imported' => $result->imported, 'dry_run' => (bool) ($validated['dry_run'] ?? false)],
            $validated['dry_run'] ?? false ? 'Pratinjau valid.' : 'Impor selesai.'
        );
    }

    public function exportItems(Company $company, ExportItems $useCase): StreamedResponse
    {
        $rows = $useCase($company);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['code', 'name', 'type', 'unit', 'category', 'status']);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'items.csv', ['Content-Type' => 'text/csv']);
    }
}
