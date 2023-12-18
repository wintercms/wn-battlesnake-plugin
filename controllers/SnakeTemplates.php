<?php namespace Winter\Battlesnake\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

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

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Winter.Battlesnake', 'battlesnake', 'snaketemplates');
    }
}
