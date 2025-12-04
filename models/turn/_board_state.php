<?php
/**
 * Board State Partial - Shows board image and copyable ASCII representation
 */

// Get replay data if not already set (for initial load)
if (!isset($replay)) {
    $replay = $formModel->replay();
}

$debug = $replay['debug'] ?? [];
$state = $debug['state'] ?? [];
$boardString = $replay['board'] ?? '';
$boardImage = $formModel->board_image ?? '';
?>

<div id="board-state-container" class="row board-state-row">
    <!-- Hidden field for turn ID (used by replay button) -->
    <input type="hidden" name="turn_id" value="<?= e($formModel->id) ?>">

    <!-- Board Image -->
    <div class="col-md-4">
        <h5 class="section-title">Visual Board</h5>
        <?php if ($boardImage): ?>
            <img
                src="<?= url('/storage/app/media/' . e($boardImage)) ?>"
                alt="Board State"
                class="board-image"
            >
        <?php else: ?>
            <p class="text-muted">No image available</p>
        <?php endif; ?>
    </div>

    <!-- ASCII Board (Copyable) -->
    <div class="col-md-4">
        <h5 class="section-title">ASCII Board <small class="text-muted">(click to copy)</small></h5>
        <pre
            id="ascii-board-copy"
            class="copyable-ascii"
            title="Click to copy for test case"
            data-board="<?= e($boardString) ?>"
        ><?= e($boardString) ?></pre>
        <p class="help-block">
            <small><i class="icon-copy"></i> Click to copy for use as a test case</small>
        </p>
    </div>

    <!-- Snake Info -->
    <div class="col-md-4">
        <h5 class="section-title">Snake Info</h5>
        <table class="table table-condensed table-bordered snake-info-table">
            <tr>
                <td><strong>Health</strong></td>
                <td><?= e($state['health'] ?? '-') ?></td>
            </tr>
            <tr>
                <td><strong>Length</strong></td>
                <td><?= e($state['length'] ?? '-') ?></td>
            </tr>
            <tr>
                <td><strong>Mode</strong></td>
                <td>
                    <?php if (($debug['mode'] ?? '') === 'food_seeking'): ?>
                        <span class="label label-warning">Food Seeking</span>
                    <?php else: ?>
                        <span class="label label-info">Space Maximizing</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Head</strong></td>
                <td>
                    <?php if ($state['headPosition'] ?? null): ?>
                        (<?= e($state['headPosition']['x']) ?>, <?= e($state['headPosition']['y']) ?>)
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Neck</strong></td>
                <td>
                    <?php if ($state['neckPosition'] ?? null): ?>
                        (<?= e($state['neckPosition']['x']) ?>, <?= e($state['neckPosition']['y']) ?>)
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($debug['foodTarget'] ?? null): ?>
            <tr>
                <td><strong>Food Target</strong></td>
                <td>(<?= e($debug['foodTarget']['x']) ?>, <?= e($debug['foodTarget']['y']) ?>)</td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<style>
.board-state-row {
    margin-bottom: 15px;
}
.board-state-row .section-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
}
.board-image {
    max-width: 100%;
    height: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    image-rendering: pixelated;
}
.copyable-ascii {
    font-size: 11px;
    line-height: 1.2;
    background: #1a1a2e;
    color: #fff;
    padding: 10px;
    border-radius: 4px;
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
    margin-bottom: 5px;
}
.copyable-ascii:hover {
    background: #2a2a4e;
    box-shadow: 0 0 10px rgba(100, 150, 255, 0.5);
}
.copyable-ascii.copied {
    background: #1a4a1a;
}
.copyable-ascii.copied::after {
    content: 'Copied!';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 200, 0, 0.9);
    color: white;
    padding: 5px 15px;
    border-radius: 4px;
    font-size: 14px;
    font-family: sans-serif;
}
.snake-info-table {
    font-size: 13px;
}
.snake-info-table td:first-child {
    width: 100px;
}
</style>

<script>
// Use delegated event handler that works in modals
$(document).off('click.copyAsciiBoard').on('click.copyAsciiBoard', '#ascii-board-copy', function() {
    var $board = $(this);
    var boardText = $board.data('board');

    navigator.clipboard.writeText(boardText).then(function() {
        $board.addClass('copied');
        setTimeout(function() {
            $board.removeClass('copied');
        }, 1500);
    }).catch(function(err) {
        console.error('Failed to copy: ', err);
        // Fallback for older browsers
        var textarea = document.createElement('textarea');
        textarea.value = boardText;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        $board.addClass('copied');
        setTimeout(function() {
            $board.removeClass('copied');
        }, 1500);
    });
});
</script>
