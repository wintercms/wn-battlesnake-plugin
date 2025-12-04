<?php
$participants = $record->participants;
if ($participants->isEmpty()) {
    echo '<span class="text-muted">-</span>';
    return;
}

$items = [];
foreach ($participants as $participant) {
    $name = $participant->display_name;
    $result = $participant->result ?? 'pending';
    $isOurs = $participant->is_our_snake;
    $color = match($result) {
        'win' => 'text-success',
        'loss' => 'text-danger',
        'draw' => 'text-warning',
        default => 'text-muted',
    };
    // Bold our snakes, normal for opponents
    $format = $isOurs ? '<strong class="%s">%s: %s</strong>' : '<span class="%s">%s: %s</span>';
    $items[] = sprintf($format, $color, e($name), $result);
}

echo implode('<br>', $items);
