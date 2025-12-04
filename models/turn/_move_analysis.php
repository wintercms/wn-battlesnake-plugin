<?php
/**
 * Move Analysis Partial - Shows decision analysis for each direction
 */

// Get replay data if not already set
if (!isset($replay)) {
    $replay = $formModel->replay();
}

$debug = $replay['debug'] ?? [];
$moves = $debug['moves'] ?? [];
$categorization = $debug['categorization'] ?? [];
$decision = $debug['decision'] ?? [];

$movesDifferent = ($replay['move'] ?? '') !== ($replay['originalMove'] ?? '');

// Risk level descriptions
$riskLabels = [
    0 => ['label' => 'Safe', 'class' => 'success'],
    1 => ['label' => 'Win', 'class' => 'info'],
    2 => ['label' => 'Tie', 'class' => 'warning'],
    3 => ['label' => 'Lose', 'class' => 'danger'],
];
?>

<div id="move-analysis-container" class="move-analysis">
    <div class="row">
        <!-- Move Analysis Table -->
        <div class="col-md-8">
            <div class="table-container">
                <table class="table table-striped table-bordered move-table">
                    <thead>
                        <tr>
                            <th>Direction</th>
                            <th>Valid</th>
                            <th>Reason</th>
                            <th>Area</th>
                            <th>Open</th>
                            <th>Escape</th>
                            <th>Risk</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (['up', 'down', 'left', 'right'] as $dir): ?>
                            <?php $move = $moves[$dir] ?? []; ?>
                            <tr class="<?= ($replay['move'] ?? '') === $dir ? 'active' : '' ?>">
                                <td>
                                    <strong><?= strtoupper($dir) ?></strong>
                                    <?php if (($replay['move'] ?? '') === $dir): ?>
                                        <span class="label label-success" title="Current code chose this">R</span>
                                    <?php endif; ?>
                                    <?php if (($replay['originalMove'] ?? '') === $dir): ?>
                                        <span class="label label-primary" title="Original move played">O</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($move['valid'] ?? false): ?>
                                        <span class="text-success"><i class="icon-check"></i></span>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="icon-close"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($move['rejectedReason'] ?? null): ?>
                                        <span class="label label-danger"><?= e($move['rejectedReason']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= ($move['valid'] ?? false) ? e($move['area'] ?? 0) : '<span class="text-muted">-</span>' ?></td>
                                <td class="text-center"><?= ($move['valid'] ?? false) ? e($move['immediateArea'] ?? 0) : '<span class="text-muted">-</span>' ?></td>
                                <td class="text-center"><?= ($move['valid'] ?? false) ? e($move['escapeRoutes'] ?? 0) : '<span class="text-muted">-</span>' ?></td>
                                <td class="text-center">
                                    <?php if ($move['valid'] ?? false): ?>
                                        <?php $risk = $move['collisionRisk'] ?? 0; ?>
                                        <?php $riskInfo = $riskLabels[$risk] ?? ['label' => $risk, 'class' => 'default']; ?>
                                        <span class="label label-<?= $riskInfo['class'] ?>"><?= e($riskInfo['label']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <strong><?= ($move['valid'] ?? false) ? e($move['score'] ?? 0) : '<span class="text-muted">-</span>' ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="help-block legend">
                <span class="label label-success">R</span> = Replay result &nbsp;
                <span class="label label-primary">O</span> = Original move
            </p>
        </div>

        <!-- Move Categorization & Decision -->
        <div class="col-md-4">
            <!-- Move Categorization -->
            <div class="panel panel-default categorization-panel">
                <div class="panel-heading"><strong>Categorization</strong></div>
                <div class="panel-body">
                    <p><strong>Safe:</strong>
                        <?php if (!empty($categorization['safeMoves'])): ?>
                            <?php foreach ($categorization['safeMoves'] as $dir => $score): ?>
                                <span class="label label-success"><?= e($dir) ?>: <?= e($score) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">None</span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Risky:</strong>
                        <?php if (!empty($categorization['riskyMoves'])): ?>
                            <?php foreach ($categorization['riskyMoves'] as $dir => $score): ?>
                                <span class="label label-warning"><?= e($dir) ?>: <?= e($score) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">None</span>
                        <?php endif; ?>
                    </p>
                    <p class="mb-0"><strong>Valid:</strong>
                        <?php if (!empty($categorization['validMoves'])): ?>
                            <?php foreach ($categorization['validMoves'] as $dir => $score): ?>
                                <span class="label label-info"><?= e($dir) ?>: <?= e($score) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">None</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Decision Summary -->
            <div class="callout callout-<?= $movesDifferent ? 'danger' : 'success' ?> decision-callout">
                <h5>Decision</h5>
                <div class="decision-moves">
                    <div class="decision-move">
                        <span class="decision-label">Current:</span>
                        <span class="label label-success label-lg"><?= strtoupper(e($replay['move'] ?? '-')) ?></span>
                    </div>
                    <div class="decision-move">
                        <span class="decision-label">Original:</span>
                        <span class="label label-primary label-lg"><?= strtoupper(e($replay['originalMove'] ?? '-')) ?></span>
                    </div>
                </div>
                <p class="decision-reason"><strong>Reason:</strong> <?= e($decision['reason'] ?? 'Unknown') ?></p>
                <?php if ($movesDifferent): ?>
                    <p class="text-danger decision-status">
                        <i class="icon-warning"></i> <strong>Code change detected!</strong>
                    </p>
                <?php else: ?>
                    <p class="text-success decision-status">
                        <i class="icon-check"></i> Same move as original.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.move-analysis {
    margin-bottom: 15px;
}
.move-analysis .table-container {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
}
.move-analysis .move-table {
    margin-bottom: 0;
    font-size: 13px;
}
.move-analysis .move-table th {
    font-weight: 600;
    background: #f8f8f8;
}
.move-analysis .move-table tr.active {
    background-color: #e8f5e9 !important;
}
.move-analysis .legend {
    margin-top: 8px;
    font-size: 12px;
}
.move-analysis .categorization-panel {
    margin-bottom: 15px;
}
.move-analysis .categorization-panel .panel-body {
    padding: 10px;
}
.move-analysis .categorization-panel p {
    margin-bottom: 5px;
}
.move-analysis .categorization-panel .mb-0 {
    margin-bottom: 0;
}
.move-analysis .decision-callout {
    margin-bottom: 0;
}
.move-analysis .decision-callout h5 {
    margin-top: 0;
    margin-bottom: 10px;
    font-weight: 600;
}
.move-analysis .decision-moves {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
}
.move-analysis .decision-move {
    display: flex;
    align-items: center;
    gap: 5px;
}
.move-analysis .decision-label {
    font-weight: 500;
    font-size: 12px;
}
.move-analysis .label-lg {
    font-size: 14px;
    padding: 4px 8px;
}
.move-analysis .decision-reason {
    font-size: 13px;
    margin-bottom: 8px;
}
.move-analysis .decision-status {
    margin-bottom: 0;
    font-size: 13px;
}
</style>
