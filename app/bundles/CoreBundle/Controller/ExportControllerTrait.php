<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Controller;

use Mautic\CoreBundle\Helper\ExportHelper;
use Symfony\Component\HttpFoundation\JsonResponse;

trait ExportControllerTrait
{
    protected function batchExportProgessAction($format, $start, $total, $exportData, $name, $actionRoute, $indexRoute)
    {
        /** @var ExportHelper $exporter */
        $exporter = $this->get('mautic.helper.exporter');
        $export   = $this->get('session')->get('mautic.export.active', []);
        if (!is_array($export)) {
            $export = [];
        }
        $inProgress = array_key_exists($name.$format, $export);

        if (!$inProgress) {
            $exportId              = $exporter->generateExportId();
            $export[$name.$format] = [
                'id'    => $exportId,
                'total' => $total,
            ];
            $thisExport = &$export[$name.$format];
        } else {
            $thisExport = &$export[$name.$format];
            $exportId   = $thisExport['id'];
            $total      = $thisExport['total'];

            if (0 === $start && $inProgress) {
                // Restart export
                $exporter->delete($exportId);
                $this->get('session')->remove('mautic.export.'.$exportId);

                $thisExport['total'] = $total;
            }
        }

        if ($this->request->get('cancel')) {
            // Delete the temp export
            $exporter->delete($exportId);
            unset($export[$name.$format]);
            $this->get('session')->set('mautic.export.active', $export);

            return $this->delegateRedirect($this->get('router')->generate($indexRoute));
        } elseif ($this->request->query->has('export')) {
            if (!$data = $this->get('session')->get('mautic.export.'.$exportId, false)) {
                if (!empty($thisExport['completed'])) {
                    // Completed start download
                    unset($export[$name.$format]);
                    $this->get('session')->set('mautic.export.active', $export);

                    return $exporter->download($exportId, $name, $format);
                }

                $this->get('session')->get('mautic.export.'.$exportId, true);

                // Export a batch
                $data = ['progress' => [$start, $total], 'percent' => ($start) ? round($total / $start, 0) : 0];
                $this->get('session')->set('mautic.export.'.$exportId, $data);

                $exported      = $exporter->export($format, $exportData, $name, $exportId);
                $data['start'] = $start + $exported;
                if ($data['start'] >= $total) {
                    $thisExport['completed'] = $data['completed'] = true;
                }
                $this->get('session')->set('mautic.export.active', $export);
                $this->get('session')->remove('mautic.export.'.$exportId);
            }

            return new JsonResponse($data);
        }

        return $this->delegateView(
            [
                'contentTemplate' => 'MauticCoreBundle:Export:progress.html.php',
                'viewParameters'  => [
                    'format'      => $format,
                    'exportId'    => $exportId,
                    'complete'    => ($start >= $total),
                    'progress'    => [$start, $total],
                    'actionRoute' => $actionRoute,
                    'indexRoute'  => $indexRoute,
                ],
            ]
        );
    }
}
