<?php

namespace Winter\Battlesnake\Controllers;

use Backend\Classes\Controller;
use Illuminate\Support\Facades\Lang;
use Winter\Battlesnake\Models\GameParticipant;
use Winter\Battlesnake\Models\Turn;
use Winter\Storm\Exception\ApplicationException;

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

    public $formLayout = 'fancy';

    public function create()
    {
        abort(404);
    }

    public function update()
    {
        abort(404);
    }

    /**
     * Extend filter scopes to provide snake options from the current game
     */
    public function relationExtendViewFilterWidget($widget, $field, $model)
    {
        if ($field === 'turns') {
            $widget->bindEvent('filter.extendScopesBefore', function () use ($widget, $model) {
                $widget->scopes['snake_id']['options'] = GameParticipant::where('game_id', $model->game_id)
                    ->pluck('snake_name', 'snake_id')
                    ->toArray();
            });
        }
    }

    /**
     * Extend the Turn preview form to populate strategy nestedform
     */
    public function relationExtendManageWidget($widget, $field, $model)
    {
        if ($field === 'turns' && $widget->model instanceof Turn) {
            // Get replay data to populate strategy values
            $replay = $widget->model->replay();
            $strategy = $replay['debug']['strategy'] ?? [];

            // Pass strategy values directly - nestedform expects flat array
            $widget->model->strategy = $strategy;
        }
    }

    public function formFindModelObject($recordId)
    {
        if (!strlen($recordId)) {
            throw new ApplicationException(Lang::get('backend::lang.form.missing_id'));
        }

        $model = $this->formCreateModelObject();

        /*
         * Prepare query and find model record
         */
        $query = $model->newQuery();
        $this->formExtendQuery($query);
        $result = $query->where('game_id', $recordId)->first();

        if (!$result) {
            throw new ApplicationException(Lang::get('backend::lang.form.not_found', [
                'class' => get_class($model), 'id' => $recordId,
            ]));
        }

        $result = $this->formExtendModel($result) ?: $result;

        return $result;
    }

    public function onReplayTurn()
    {
        // Get the turn ID from the form
        $turnId = post('turn_id') ?: post('Turn.id');
        $turn = Turn::find($turnId);

        if (!$turn) {
            throw new ApplicationException('Turn not found');
        }

        // Get strategy overrides from nestedform (flat array format)
        $strategyOverrides = post('Turn.strategy', []);

        // Replay with strategy overrides
        $replay = $turn->replay($strategyOverrides);

        // Render the updated partials
        $partialVars = [
            'formModel' => $turn,
            'replay' => $replay,
        ];

        return [
            '#board-state-container' => $this->makePartial(
                plugins_path('winter/battlesnake/models/turn/_board_state.php'),
                $partialVars
            ),
            '#move-analysis-container' => $this->makePartial(
                plugins_path('winter/battlesnake/models/turn/_move_analysis.php'),
                $partialVars
            ),
        ];
    }
}
