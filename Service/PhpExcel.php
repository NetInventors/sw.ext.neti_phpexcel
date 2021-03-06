<?php
/**
 * @copyright  Copyright (c) 2016, Net Inventors GmbH
 *
 * @category   Shopware
 *
 * @author     rubyc
 */

namespace NetiPhpExcel\Service;

class PhpExcel
{
    const FORMAT_EXCEL = 1;
    const FORMAT_CSV   = 2;

    /**
     * @var \PHPExcel|bool
     */
    protected $phpExcel;

    /**
     * @return bool|\PHPExcel
     */
    public function getPhpExcel()
    {
        if (null === $this->phpExcel) {
            if (!class_exists('PHPExcel')) {
                require_once __DIR__ . '/../vendor/phpoffice/phpexcel/Classes/PHPExcel.php';
            }

            $this->phpExcel = new \PHPExcel();
        } elseif (!$this->phpExcel instanceof \PHPExcel) {
            $this->phpExcel = false;
        }

        return $this->phpExcel;
    }

    /**
     * This function tries to determine the delimiter counting the occurrences in the first row.
     * The quality of the result depends on the contained values in the first row, it might nox be correct in any case.
     *
     * @param string $csvFile
     *
     * @return string
     */
    public function detectDelimiter($csvFile)
    {
        $delimiters = [
            ';'  => 0,
            ','  => 0,
            "\t" => 0,
            '|'  => 0,
        ];

        $handle    = fopen($csvFile, 'r');
        $firstLine = fgets($handle);
        fclose($handle);
        foreach ($delimiters as $delimiter => &$count) {
            $count = count(str_getcsv($firstLine, $delimiter));
        }

        return array_search(max($delimiters), $delimiters);
    }

    /**
     * @param array  $data
     *                                     If $data contains an associative array, the keys will be set as headlines in the first row.
     * @param string $filename
     * @param int    $format
     *                                     self::FORMAT_CSV or self::FORMAT_EXCEL
     * @param string $delimiter
     * @param bool   $strictNullComparison
     *
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public function exportFunction(
        $data,
        $filename,
        $format = self::FORMAT_CSV,
        $delimiter = ',',
        $strictNullComparison = false
    ) {
        $phpExcel = $this->getPhpExcel();
        $phpExcel->setActiveSheetIndex(0);

        if ($this->isAssoc(reset($data))) {
            $phpExcel->getActiveSheet()->fromArray(array_keys(reset($data)), null, 'A1', $strictNullComparison);
            $phpExcel->getActiveSheet()->fromArray($data, null, 'A2', $strictNullComparison);
        } else {
            $phpExcel->getActiveSheet()->fromArray($data, null, 'A1', $strictNullComparison);
        }

        $filename = $filename . '.' . $this->getExtension($format);
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $objWriter = \PHPExcel_IOFactory::createWriter($phpExcel, $this->getWriterType($format));
        if (self::FORMAT_CSV === $format) {
            $objWriter->setDelimiter($delimiter);
        }

        if ($objWriter instanceof \PHPExcel_Writer_IWriter) {
            $objWriter->save('php://output');
        }
        exit;
    }

    /**
     * @param string      $filename
     * @param string|null $inputFileType - is no type is suplied, PhpExcel will try to guess it
     *
     * @throws \Exception
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     *
     * @return array
     */
    public function getArrayFromFile($filename, $inputFileType = null)
    {
        if (!is_readable($filename)) {
            throw new \Exception('File does not exist or is not readable: ' . $filename);
        }

        $this->getPhpExcel();

        if (null === $inputFileType) {
            $inputFileType = \PHPExcel_IOFactory::identify($filename);
        }

        $objReader = \PHPExcel_IOFactory::createReader($inputFileType);

        // detect and set delimiter
        if ('CSV' === $inputFileType) {
            $delimiter = $this->detectDelimiter($filename);
            /** @var \PHPExcel_Reader_CSV $objReader */
            $objReader->setDelimiter($delimiter);
        }
        $objPHPExcel = $objReader->load($filename);

        $worksheet = $objPHPExcel->getActiveSheet();
        $delimiter = null;

        $rows = [];
        foreach ($worksheet->getRowIterator() as $rowData) {
            $row          = [];
            $cellIterator = $rowData->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false); // Loop all cells, even if it is not set
            foreach ($cellIterator as $cell) {
                /** @var \PHPExcel_Cell $cell */
                if (!is_null($cell)) {
                    $row[] = $cell->getValue();
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Transforms the imported numeric arrays into a associative arrays.
     * The keys will be taken from the first row of the $rows array.
     *
     * @param array $rows
     *
     * @return array
     */
    public function createAssociativeArray($rows)
    {
        $results  = [];
        $firstRow = true;
        foreach ($rows as $row) {
            if ($firstRow) {
                $firstRow = false;
                continue;
            }

            $result = [];
            foreach ($row as $key => $value) {
                $index = $rows[0][$key];
                if (null === $index || '' === $index) {
                    continue;
                }
                $result[$index] = $value;
            }
            $results[] = $result;
        }

        return $results;
    }

    /**
     * @param array $arr
     *
     * @return bool
     */
    private function isAssoc(array $arr)
    {
        if ([] === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * @param int $format
     *
     * @return string
     */
    private function getExtension($format)
    {
        switch ($format) {
            case self::FORMAT_EXCEL:
                return 'xls';
            case self::FORMAT_CSV:
                return 'csv';
            default:
                return 'unknown';
        }
    }

    /**
     * @param int $format
     *
     * @return string
     */
    private function getWriterType($format)
    {
        switch ($format) {
            case self::FORMAT_EXCEL:
                return 'Excel5';
            case self::FORMAT_CSV:
                return 'CSV';
            default:
                return 'unknown';
        }
    }
}
