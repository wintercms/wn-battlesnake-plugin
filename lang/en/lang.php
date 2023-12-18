<?php

return [
    'plugin' => [
        'name' => 'Battlesnake',
        'description' => 'No description provided yet...',
    ],
    'permissions' => [
        'some_permission' => 'Some permission',
    ],
    'models' => [
        'general' => [
            'id' => 'ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ],
        'gamelog' => [
            'label' => 'Game Log',
            'label_plural' => 'Game Logs',
        ],
        'turn' => [
            'label' => 'Turn',
            'label_plural' => 'Turns',
            'turn' => 'Turn',
            'move' => 'Selected Move',
            'board_image' => "Board State (Image)",
            'board_string' => "Board State (String)",
        ],
        'snaketemplate' => [
            'label' => 'Snake Template',
            'label_plural' => 'Snake Templates',
        ],
    ],
];
