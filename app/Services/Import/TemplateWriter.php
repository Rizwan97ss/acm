<?php

namespace App\Services\Import;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Builds the downloadable .xlsx template: a "Data" sheet with only the header row (so no
 * example data is imported by accident) and an "Instructions" sheet describing each column.
 */
class TemplateWriter
{
    public function write(Importer $importer, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $header = (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor('1E3A8A');
        $required = (new Style)->withFontBold(true)->withFontColor('1E3A8A')->withBackgroundColor('FDE68A');

        $columns = $importer->columns();

        $data = $writer->getCurrentSheet();
        $data->setName('Data');
        $data->setColumnWidthForRange(22, 1, max(1, count($columns)));
        $writer->addRow(Row::fromValuesWithStyles(
            array_map(fn (ImportColumn $column): string => $column->header(), $columns),
            array_map(fn (ImportColumn $column): Style => $column->required ? $required : $header, $columns),
        ));

        $instructions = $writer->addNewSheetAndMakeItCurrent();
        $instructions->setName('Instructions');
        $instructions->setColumnWidth(26, 1);
        $instructions->setColumnWidth(12, 2);
        $instructions->setColumnWidth(70, 3);
        $instructions->setColumnWidth(28, 4);

        $writer->addRow(Row::fromValuesWithStyle([__(':title — how to fill this file', ['title' => $importer->title()])], (new Style)->withFontBold(true)->withFontSize(14)));
        $writer->addRow(Row::fromValues([__('Fill one row per record on the "Data" sheet, under the header row. Columns marked * are required. Keep the header row as it is. Up to :max rows per file.', ['max' => SpreadsheetReader::MAX_ROWS])]));
        $writer->addRow(Row::fromValues([__('Phone numbers and IDs with leading zeros: format those columns as Text in Excel first, or the zeros are lost.')]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle([__('Column'), __('Required'), __('What to enter'), __('Example')], $header));

        foreach ($columns as $column) {
            $writer->addRow(Row::fromValues([$column->header(), $column->required ? __('Yes') : __('No'), $column->format(), $column->example]));
        }

        $writer->setCurrentSheet($data);
        $writer->close();
    }
}
