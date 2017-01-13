<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Helper;

use Mautic\CoreBundle\Templating\Helper\FormatterHelper;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportHelper
{
    /**
     * @var FormatterHelper
     */
    protected $formatter;

    /**
     * @var string
     */
    protected $tmpPath;

    /**
     * ExportHelper constructor.
     *
     * @param FormatterHelper $formatterHelper
     * @param PathsHelper     $pathsHelper
     */
    public function __construct(FormatterHelper $formatterHelper, PathsHelper $pathsHelper)
    {
        $this->formatter = $formatterHelper;
        $this->tmpPath   = $pathsHelper->getSystemPath('tmp');
    }

    /**
     * @param $format
     * @param $name
     * @param $exportData
     *
     * @return int
     *
     * @throws \Exception
     */
    public function export($format, array $exportData, $name = null, $exportId = null)
    {
        if (null === $exportId) {
            $exportId = $this->generateExportId();
        }

        $file = $this->tmpPath.'/'.$exportId;

        if (!isset($exportData['data'])) {
            $exportData = ['data' => $exportData];
        }
        $exportCount = count($exportData['data']);

        switch ($format) {
            case 'csv':
                $handle = fopen($file, 'a+');
                $header = [];

                //build the data rows
                foreach ($exportData['data'] as $count => $data) {
                    $row = [];
                    foreach ($data as $k => $v) {
                        if ($count === 0) {
                            //set the header
                            $header[] = $k;
                        }

                        $row[] = $this->formatColumn($exportData, $v, $k);
                    }

                    if ($count === 0) {
                        //write the row
                        fputcsv($handle, $header);
                    }

                    fputcsv($handle, $row);

                    //free memory
                    unset($row, $exportData['data'][$count]);
                }

                fclose($handle);
                break;
            case 'xlsx':
                if (class_exists('PHPExcel')) {
                    if (file_exists($file)) {
                        $objPHPExcel = \PHPExcel_IOFactory::load($file);
                        $objPHPExcel->setActiveSheetIndex(0);
                        $rowCount = $objPHPExcel->getActiveSheet()->getHighestRow() + 1;
                    } else {
                        $objPHPExcel = new \PHPExcel();
                        $rowCount    = 0;
                    }

                    $objPHPExcel->getProperties()->setTitle($name);
                    $objPHPExcel->createSheet();
                    $header = [];

                    //build the data rows
                    foreach ($exportData['data'] as $dataKey => $data) {
                        $row = [];
                        foreach ($data as $k => $v) {
                            if ($rowCount === 0) {
                                //set the header
                                $header[] = $k;
                            }

                            $row[] = $this->formatColumn($exportData, $v, $k);
                        }

                        //write the row
                        if ($rowCount === 0) {
                            $objPHPExcel->getActiveSheet()->fromArray($header, null, 'A1');
                        } else {
                            ++$rowCount;
                            $objPHPExcel->getActiveSheet()->fromArray($row, null, "A{$rowCount}");
                        }

                        //free memory
                        unset($row, $exportData['data'][$dataKey]);
                    }

                    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
                    $objWriter->setPreCalculateFormulas(false);
                    $objWriter->save($file);
                    break;
                } else {
                    throw new \Exception('PHPExcel is required to export to Excel spreadsheets');
                }
            default:
                throw new \InvalidArgumentException($format.' not supported.');
        }

        return $exportCount;
    }

    /**
     * @param $exportId
     * @param $name
     * @param $format
     *
     * @return BinaryFileResponse
     */
    public function download($exportId, $name, $format)
    {
        if (!file_exists($this->tmpPath.'/'.$exportId)) {
            throw new \InvalidArgumentException($exportId.' not found.');
        }

        $response = new BinaryFileResponse($this->tmpPath.'/'.$exportId);

        $response->headers->set('Content-Type', 'application/force-download');
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.InputHelper::filename($name).'.'.$format.'"');
        $response->headers->set('Expires', 0);
        $response->headers->set('Cache-Control', 'must-revalidate');
        $response->headers->set('Pragma', 'public');

        return $response;
    }

    /**
     * @param $exportId
     */
    public function delete($exportId)
    {
        if (file_exists($this->tmpPath.'/'.$exportId)) {
            unlink($this->tmpPath.'/'.$exportId);
        }
    }

    /**
     * @return string
     */
    public function generateExportId()
    {
        $date = (new DateTimeHelper())->toLocalString();

        return str_replace([' ', ':'], ['_', ''], $date).'_'.EncryptionHelper::generateKey();
    }

    /**
     * @param $exportData
     * @param $v
     * @param $k
     *
     * @return string
     */
    protected function formatColumn($exportData, $value, $column)
    {
        if (isset($exportData['dataColumns']) && isset($exportData['columns'])) {
            return $this->formatter->_($value, $exportData['columns'][$exportData['dataColumns'][$column]]['type'], true);
        }

        return $value;
    }
}
