<?php namespace Winter\Battlesnake\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Turns Backend Controller
 */
class Turns extends Controller
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

        BackendMenu::setContext('Winter.Battlesnake', 'battlesnake', 'turns');
    }
}
