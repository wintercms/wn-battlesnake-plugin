<?php

namespace Winter\Battlesnake\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\Backend;
use Winter\Battlesnake\Classes\Snake;
use Winter\Storm\Support\Facades\Flash;

/**
 * Snake Templates Backend Controller
 */
class SnakeTemplates extends Controller
{
    /**
     * @var array Behaviors that are implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formLayout = 'fancy';

    /**
     * AJAX handler to reset strategy values to defaults.
     */
    public function onResetStrategy(int $recordId): mixed
    {
        $model = $this->formFindModelObject($recordId);
        $metadata = $model->metadata ?? [];
        $metadata['strategy'] = Snake::getDefaultStrategy();
        $model->metadata = $metadata;
        $model->save();

        Flash::success('Strategy values reset to defaults');

        return redirect()->refresh();
    }
}
