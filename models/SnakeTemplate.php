<?php

namespace Winter\Battlesnake\Models;

use Model;
use Winter\Battlesnake\Classes\Snake as SnakeObject;

/**
 * SnakeTemplate Model
 */
class SnakeTemplate extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'winter_battlesnake_snake_templates';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [];

    /**
     * @var array Attributes to be cast to native types
     */
    protected $casts = [];

    /**
     * @var array Attributes to be cast to JSON
     */
    protected $jsonable = [];

    /**
     * @var array Attributes to be appended to the API representation of the model (ex. toArray())
     */
    protected $appends = [];

    /**
     * @var array Attributes to be removed from the API representation of the model (ex. toArray())
     */
    protected $hidden = [];

    /**
     * @var array Attributes to be cast to Argon (Carbon) instances
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [];
    public $hasOneThrough = [];
    public $hasManyThrough = [];
    public $belongsTo = [];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];

    public function toArray(): array
    {
        return [
            'apiversion' => 1,
            'author' => $this->metadata['customization']['author'] ?? 'Winter CMS',
            'color' => $this->metadata['customization']['color'] ?? '#3498db',
            'head' => $this->metadata['customization']['head'] ?? 'default',
            'tail' => $this->metadata['customization']['tail'] ?? 'default',
            'version' => $this->metadata['customization']['version'] ?? $this->updated_at->toDateTimeString(),
        ];
    }

    public function findByCredentials(array $credentials): ?self
    {
        $result = static::where('slug', $credentials['slug'] ?? null)->first();
        if ($result && $credentials['password'] === $result->metadata['password']) {
            return $result;
        }
        return null;
    }

    public function getSnakeObject(): SnakeObject
    {
        $parser = new SnakeParser($this);
        return $parser->source();
    }

    /**
     * @see https://play.battlesnake.com/customizations
     */
    public function getHeadOptions(): array
    {
        return [
            'all-seeing',
            'beluga',
            'bendr',
            'bonhomme',
            'caffeine',
            'dead',
            'default',
            'do-sammy',
            'earmuffs',
            'evil',
            'fang',
            'gamer',
            'mlh-gene',
            'nr-rocket',
            'pixel',
            'rbc-bowler',
            'replit-mark',
            'rudolph',
            'safe',
            'sand-worm',
            'scarf',
            'shades',
            'silly',
            'ski',
            'smart-caterpillar',
            'smile',
            'snowman',
            'snow-worm',
            'tiger-king',
            'tongue',
            'trans-rights-scarf',
            'workout',
        ];
    }

    /**
     * @see https://play.battlesnake.com/customizations
     */
    public function getTailOptions(): array
    {
        return [
            'block-bum',
            'bolt',
            'bonhomme',
            'coffee',
            'curled',
            'default',
            'do-sammy',
            'fat-rattle',
            'flake',
            'freckled',
            'hook',
            'ice-skate',
            'mlh-gene',
            'mouse',
            'mystic-moon',
            'nr-rocket',
            'pixel',
            'present',
            'rbc-necktie',
            'replit-notmark',
            'round-bum',
            'sharp',
            'skinny',
            'small-rattle',
            'tiger-tail',
            'weight',
        ];
    }
}
