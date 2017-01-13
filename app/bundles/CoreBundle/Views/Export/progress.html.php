<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
$view->extend('MauticCoreBundle:Default:content.html.php');

$view['slots']->set('mauticContent', 'coreExport');
$view['slots']->set('headerTitle', $view['translator']->trans('mautic.core.exporting'));

$percent = ($progress[1]) ? ceil(($progress[0] / $progress[1]) * 100) : 100;
$id      = ($complete) ? 'exportProgressComplete' : 'exportProgress';
$header  = ($complete) ? 'mautic.core.export.success' : 'mautic.core.export.inprogress';
?>

<div class="row ma-lg" id="<?php echo $id; ?>">
    <div class="col-sm-offset-3 col-sm-6 text-center">
        <div class="panel panel-<?php echo ($complete) ? 'success' : 'danger'; ?>">
            <div class="panel-heading">
                <h4 class="panel-title"><?php echo $view['translator']->trans($header); ?></h4>
            </div>
            <div class="panel-body">
                <div class="progress mt-md" style="height:50px;">
                    <div class="progress-bar-import progress-bar progress-bar-striped<?php if (!$complete): echo ' active'; endif; ?>"
                         role="progressbar" aria-valuenow="<?php echo $progress[0]; ?>"
                         aria-valuemin="0" aria-valuemax="<?php echo $progress[1]; ?>" style="width: <?php echo $percent; ?>%; height: 50px;">
                        <span class="sr-only"><?php echo $percent; ?>%</span>
                    </div>
                </div>
            </div>
            <div class="panel-footer">
                <p class="small"><span class="imported-count"><?php echo $progress[0]; ?></span> / <span class="total-count"><?php echo $progress[1]; ?></span></p>
                <?php if (!$complete): ?>
                    <div>
                        <a class="text-danger mt-md" href="<?php echo $view['router']->path($actionRoute, ['objectAction' => 'export', 'cancel' => 1]); ?>" data-toggle="ajax"><?php echo $view['translator']->trans('mautic.core.form.cancel'); ?></a>
                    </div>
                <?php elseif (!empty($completeUrl)): ?>
                    <div>
                        <a class="btn btn-success" href="<?php echo $view['router']->path($actionRoute); ?>" data-toggle="ajax"><?php echo $view['translator']->trans('mautic.core.form.done'); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
