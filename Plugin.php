<?php namespace Winter\Battlesnake;

use Backend;
use Backend\Models\UserRole;
use System\Classes\PluginBase;

/**
 * Battlesnake Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'winter.battlesnake::lang.plugin.name',
            'description' => 'winter.battlesnake::lang.plugin.description',
            'author'      => 'Winter',
            'icon'        => 'icon-leaf',
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     */
    public function register(): void
    {

    }

    /**
     * Boot method, called right before the request route.
     */
    public function boot(): void
    {

    }

    /**
     * Registers any frontend components implemented in this plugin.
     */
    public function registerComponents(): array
    {
        return []; // Remove this line to activate

        return [
            'Winter\Battlesnake\Components\MyComponent' => 'myComponent',
        ];
    }

    /**
     * Registers any backend permissions used by this plugin.
     */
    public function registerPermissions(): array
    {
        return []; // Remove this line to activate

        return [
            'winter.battlesnake.some_permission' => [
                'tab' => 'winter.battlesnake::lang.plugin.name',
                'label' => 'winter.battlesnake::lang.permissions.some_permission',
                'roles' => [UserRole::CODE_DEVELOPER, UserRole::CODE_PUBLISHER],
            ],
        ];
    }

    /**
     * Registers backend navigation items for this plugin.
     */
    public function registerNavigation(): array
    {
        return [
            'battlesnake' => [
                'label'       => 'winter.battlesnake::lang.plugin.name',
                'url'         => Backend::url('winter/battlesnake/gamelogs'),
                'icon'        => 'icon-gamepad',
                'permissions' => ['winter.battlesnake.*'],
                'order'       => 500,
                'sideMenu'    => [
                    'gamelogs' => [
                        'label'       => 'winter.battlesnake::lang.models.gamelog.label_plural',
                        'icon'        => 'icon-gamepad',
                        'url'         => Backend::url('winter/battlesnake/gamelogs'),
                        'permissions' => ['winter.battlesnake.*'],
                    ],
                    'snaketemplates' => [
                        'label'       => 'winter.battlesnake::lang.models.snaketemplate.label_plural',
                        'icon'        => 'icon-code',
                        'url'         => Backend::url('winter/battlesnake/snaketemplates'),
                        'permissions' => ['winter.battlesnake.*'],
                    ],
                ],
            ],
        ];
    }
}
