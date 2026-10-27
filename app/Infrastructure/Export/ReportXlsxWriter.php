<?php

declare(strict_types=1);

namespace App\Infrastructure\Export;

use App\Application\Report\DTOs\ReportExportDocument;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class ReportXlsxWriter
{
    private const MONEY_FORMAT = '"Rp" #,##0';

    private const NUMBER_FORMAT = '#,##0';

    public function write(ReportExportDocument $document, string $companyName): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator((string) config('app.name', 'Aurevant'))
            ->setCompany($companyName)
            ->setTitle($document->title);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan');

        $columns = $document->columnCount();
        $lastColumn = Coordinate::stringFromColumnIndex($columns);

        $row = 1;
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->setCellValue("A{$row}", $companyName);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row++;
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->setCellValue("A{$row}", $document->title);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row++;
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->setCellValue("A{$row}", $document->periodLabel);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        if ($document->branchName !== null) {
            $row++;
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $sheet->setCellValue("A{$row}", "Cabang: {$document->branchName}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $row += 2;
        $sheet->setCellValue("A{$row}", 'Ringkasan');
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
            ->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8F3EE');

        foreach ($document->summary as $item) {
            $row++;
            $sheet->setCellValue("A{$row}", $item['label']);
            $sheet->setCellValue("B{$row}", $item['value']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $this->applyNumberFormat($sheet, $item['type'], "B{$row}");
        }

        foreach ($document->sections as $section) {
            $row += 2;
            $sectionColumns = max(2, count($section['headings']));
            $sectionLastColumn = Coordinate::stringFromColumnIndex($sectionColumns);

            $sheet->mergeCells("A{$row}:{$sectionLastColumn}{$row}");
            $sheet->setCellValue("A{$row}", $section['title']);
            $sheet->getStyle("A{$row}:{$sectionLastColumn}{$row}")
                ->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("A{$row}:{$sectionLastColumn}{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE8F3EE');

            $row++;
            foreach ($section['headings'] as $index => $heading) {
                $column = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValue("{$column}{$row}", $heading);
                $sheet->getStyle("{$column}{$row}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            $headerRange = "A{$row}:{$sectionLastColumn}{$row}";
            $sheet->getStyle($headerRange)->getFont()
                ->setBold(true)
                ->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF0F5132');
            $sheet->getStyle($headerRange)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            foreach ($section['rows'] as $dataRow) {
                $row++;
                $isTotal = is_string($dataRow[0] ?? null)
                    && stripos((string) $dataRow[0], 'total') === 0;

                foreach ($dataRow as $index => $value) {
                    $column = Coordinate::stringFromColumnIndex($index + 1);
                    $coordinates = "{$column}{$row}";
                    $type = $section['types'][$index] ?? 'text';

                    $sheet->setCellValue($coordinates, $value);
                    $sheet->getStyle($coordinates)->getAlignment()->setHorizontal(
                        $type === 'text' ? Alignment::HORIZONTAL_LEFT : Alignment::HORIZONTAL_RIGHT
                    );
                    $this->applyNumberFormat($sheet, $type, $coordinates);

                    if ($isTotal) {
                        $sheet->getStyle($coordinates)->getFont()->setBold(true);
                    }
                }

                $sheet->getStyle("A{$row}:{$sectionLastColumn}{$row}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            }
        }

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(30);

        for ($index = 3; $index <= $columns; $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth(16);
        }

        $sheet->getPageSetup()
            ->setOrientation($document->isWide() ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()
            ->setTop(0.5)
            ->setRight(0.4)
            ->setBottom(0.5)
            ->setLeft(0.4);

        $temporary = tempnam(sys_get_temp_dir(), 'aurevant-report-');

        if ($temporary === false) {
            throw new RuntimeException('Gagal membuat berkas sementara.');
        }

        try {
            (new Xlsx($spreadsheet))->save($temporary);
            readfile($temporary);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            $spreadsheet->disconnectWorksheets();
        }
    }

    private function applyNumberFormat(
        Worksheet $sheet,
        string $type,
        string $coordinates,
    ): void {
        if ($type === 'money') {
            $sheet->getStyle($coordinates)->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
        } elseif ($type === 'number') {
            $sheet->getStyle($coordinates)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
        }
    }
}
