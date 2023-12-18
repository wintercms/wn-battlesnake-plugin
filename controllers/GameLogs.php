<?php namespace Winter\Battlesnake\Controllers;

use ApplicationException;
use BackendMenu;
use Backend\Classes\Controller;

use Winter\Battlesnake\Models\Turn;

/**
 * Game Logs Backend Controller
 */
class GameLogs extends Controller
{
    /**
     * @var array Behaviors that are implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\RelationController::class,
    ];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Winter.Battlesnake', 'battlesnake', 'gamelogs');
    }

    public function formFindModelObject($recordId)
    {
        if (!strlen($recordId)) {
            throw new ApplicationException($this->asExtension('FormController')->getLang('not-found-message', 'backend::lang.form.missing_id'));
        }

        $model = $this->formCreateModelObject();

        /*
         * Prepare query and find model record
         */
        $query = $model->newQuery();
        $this->formExtendQuery($query);
        $result = $query->where('game_id', $recordId)->first();

        if (!$result) {
            throw new ApplicationException($this->asExtension('FormController')->getLang('not-found-message', 'backend::lang.form.not_found', [
                'class' => get_class($model), 'id' => $recordId,
            ]));
        }

        $result = $this->formExtendModel($result) ?: $result;

        return $result;
    }

    public function onReplayTurn()
    {
        $turn = Turn::find(post('turn'))->replay();
        dd($turn);
    }
}
