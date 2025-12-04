<?php
use Winter\Battlesnake\Classes\BoardDataTransformer;

$gameId = $formModel->game_id;
$frames = BoardDataTransformer::gameToFrames($formModel);

// Build the replay URL with query params
$replayUrl = url('battlesnake/replay/' . $gameId);
$params = http_build_query([
    'game' => $gameId,
    'engine' => 'mock://local',
    'autoplay' => 'false',
    'showScoreboard' => 'true',
    'showControls' => 'true',
    'showScrubber' => 'true',
]);
$iframeSrc = $replayUrl . '?' . $params;
?>

<div class="form-group board-viewer-section">
    <?php if (empty($frames)): ?>
        <div class="callout callout-warning">
            <p>No turn data available for replay.</p>
        </div>
    <?php else: ?>
        <div id="board-container" style="position: relative; width: 100%; height: 750px; border: 1px solid #ccc; border-radius: 4px; overflow: hidden; background: #1a1a2e;">
            <iframe
                id="battlesnake-board-<?= e($gameId) ?>"
                style="width: 100%; height: 100%; border: none;"
                src="<?= e($iframeSrc) ?>"
            ></iframe>
        </div>

        <p class="help-block">
            <small>
                Board viewer powered by
                <a href="https://github.com/BattlesnakeOfficial/board" target="_blank" rel="noopener">BattlesnakeOfficial/board</a>
                (AGPL-3.0)
            </small>
        </p>
    <?php endif; ?>
</div>
