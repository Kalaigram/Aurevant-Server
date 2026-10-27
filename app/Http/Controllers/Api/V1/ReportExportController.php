<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Report\UseCases\BuildReportExport;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Export\ReportXlsxWriter;
use App\Models\Company;
use App\Support\Access;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(
        Request $request,
        Company $company,
        string $report,
        BuildReportExport $build,
        BranchRepositoryInterface $branches,
        ReportXlsxWriter $xlsx,
    ): StreamedResponse {
        $validated = $request->validate([
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        if ($report === 'consolidation') {
            Access::require($request->user(), $company, 'laporan.konsolidasi');
        }

        $branch = null;
        if (in_array($report, ['profit-loss', 'daily', 'cash-flow'], true)
            && isset($validated['branch_id'])) {
            $branch = $branches->findForCompany($company, (int) $validated['branch_id']) ?? abort(404);
        }

        $document = $build(
            $company,
            $report,
            $validated['from'],
            $validated['to'],
            $branch?->id,
        );
        $filename = $document->filename
            .($branch !== null ? "-cabang-{$branch->id}" : '')
            .".{$validated['format']}";

        if ($validated['format'] === 'pdf') {
            $pdf = Pdf::loadView('reports.export', [
                'document' => $document,
                'companyName' => $company->name,
            ])->setPaper('a4', $document->isWide() ? 'landscape' : 'portrait');

            return response()->streamDownload(
                fn () => print ($pdf->output()),
                $filename,
                ['Content-Type' => 'application/pdf'],
            );
        }

        return response()->streamDownload(
            fn () => $xlsx->write($document, $company->name),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
