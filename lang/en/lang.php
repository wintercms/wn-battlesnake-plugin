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
            'label' => 'Game',
            'label_plural' => 'Games',
            'participants' => 'Participants',
        ],
        'gameparticipant' => [
            'label' => 'Game Participant',
            'label_plural' => 'Game Participants',
            'snake' => 'Snake',
            'result' => 'Result',
            'death_cause' => 'Death Cause',
            'turns_survived' => 'Turns Survived',
            'final_length' => 'Final Length',
            'final_health' => 'Final Health',
            'kills' => 'Kills',
            'food_eaten' => 'Food Eaten',
        ],
        'turn' => [
            'label' => 'Turn',
            'label_plural' => 'Turns',
            'snake' => 'Snake',
            'turn' => 'Turn',
            'move' => 'Selected Move',
            'board_image' => "Board State (Image)",
            'board_string' => "Board State (String)",
        ],
        'snaketemplate' => [
            'label' => 'Snake',
            'label_plural' => 'Snakes',
        ],
    ],
];
