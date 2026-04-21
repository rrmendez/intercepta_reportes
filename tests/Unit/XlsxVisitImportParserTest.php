<?php

use App\Services\VisitImport\Parsers\XlsxVisitImportParser;

it('parses xlsx shared strings that use spreadsheet namespaces', function () {
    $xlsxPath = sys_get_temp_dir().'\\visit-import-'.uniqid('', true).'.xlsx';

    $worksheetXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">
      <c r="A1" t="s"><v>0</v></c>
      <c r="B1" t="s"><v>1</v></c>
    </row>
    <row r="2">
      <c r="A2" t="s"><v>2</v></c>
      <c r="B2" t="s"><v>3</v></c>
    </row>
  </sheetData>
</worksheet>
XML;

    $sharedStringsXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="4" uniqueCount="4">
  <si><t>client_id</t></si>
  <si><t>date_init</t></si>
  <si><r><t>1</t></r></si>
  <si><t>2026-04-16 10:00:00</t></si>
</sst>
XML;

    $zip = new ZipArchive;
    $zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
    $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
    $zip->close();

    try {
        $payload = (new XlsxVisitImportParser)->parse($xlsxPath);

        expect($payload->headers)->toBe(['client_id', 'date_init'])
            ->and($payload->rows->all())->toBe([
                ['1', '2026-04-16 10:00:00'],
            ]);
    } finally {
        @unlink($xlsxPath);
    }
});
