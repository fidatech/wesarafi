<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportExportService
{
    public function xlsx(string $title, array $headers, iterable $rows, string $filename)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle(mb_substr($title, 0, 31));
        $sheet->fromArray([$headers], null, 'A1');
        $rowNumber = 2;
        foreach ($rows as $row) {
            foreach (array_values($row) as $index => $value) {
                $sheet->setCellValueExplicit([$index + 1, $rowNumber], $value ?? '',
                    is_int($value) || is_float($value) ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $rowNumber++;
        }
        $rowNumber = max(1, $rowNumber - 1);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$sheet->getHighestColumn().$rowNumber);
        $lastColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F7A5B');
        $sheet->getStyle("A1:{$lastColumn}{$rowNumber}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        foreach (range('A', $lastColumn) as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $temporary = tempnam(sys_get_temp_dir(), 'wes-report-');
        (new Xlsx($spreadsheet))->save($temporary);
        return response()->download($temporary, $filename.'.xlsx')->deleteFileAfterSend(true);
    }

    public function pdf(string $title, array $headers, iterable $rows, string $filename, array $meta = [])
    {
        return Pdf::loadView('financial_reports.export', compact('title', 'headers', 'rows', 'meta'))
            ->setPaper('a4', count($headers) > 7 ? 'landscape' : 'portrait')->download($filename.'.pdf');
    }
}
