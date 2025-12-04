<?php

namespace Winter\Battlesnake\Models;

use Exception;
use Winter\Storm\Database\Model;
use Winter\Battlesnake\Classes\CodeParser;
use Winter\Battlesnake\Classes\Snake as SnakeObject;
use Winter\Storm\Exception\ApplicationException;

/**
 * SnakeTemplate Model
 */
class SnakeTemplate extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    /**
     * @var array Relations
     */
    public $hasMany = [
        'gameParticipants' => [
            GameParticipant::class,
            'key' => 'snake_template_id',
        ],
        'turns' => [
            Turn::class,
            'key' => 'snake_template_id',
        ],
    ];

    public const OPTIONS_HEAD = [
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

    public const OPTIONS_TAIL = [
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

    public $rules = [
        'name' => 'required',
        'slug' => 'required',
    ];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'winter_battlesnake_snake_templates';

    /**
     * @var array Attributes to be cast to JSON
     */
    protected $jsonable = [
        'metadata',
    ];

    public function toArray(): array
    {
        return [
            'apiversion' => '1',
            'author' => $this->metadata['customizations']['author'] ?? 'Winter CMS',
            'color' => $this->metadata['customizations']['color'] ?? '#3498db',
            'head' => $this->metadata['customizations']['head'] ?? 'default',
            'tail' => $this->metadata['customizations']['tail'] ?? 'default',
            'version' => $this->metadata['customizations']['version'] ?? ($this->updated_at ?? now())->toDateTimeString(),
        ];
    }

    public function getUrlAttribute(): string
    {
        if (!$this->exists) {
            return '';
        }
        return url("api/bs/{$this->slug}/{$this->metadata['password']}");
    }

    public static function findByCredentials(string $slug, string $password): ?self
    {
        $result = static::where('slug', $slug)->first();
        if ($result && $password === $result->metadata['password']) {
            return $result;
        }
        return null;
    }

    public function snake(array $state = [], array $options = []): SnakeObject
    {
        $uniqueName = str_replace('.', '', uniqid('', true)).'_'.md5(mt_rand());
        $className = 'Snake'.$uniqueName.'Class';

        $body = $this->code;
        $body = preg_replace('/^\s*function/m', 'public function', $body);

        $namespaces = [];
        $pattern = '/(use\s+[a-z0-9_\\\\]+(\s+as\s+[a-z0-9_]+)?;(\r\n|\n)?)/mi';
        preg_match_all($pattern, $body, $namespaces);
        $body = preg_replace($pattern, '', $body);

        $parentClass = SnakeObject::class;
        if ($parentClass !== null) {
            $parentClass = ' extends '.$parentClass;
        }

        // $fileContents = '<?php '.PHP_EOL;

        $fileContents = '';

        foreach ($namespaces[0] as $namespace) {
            // Only allow compound or aliased use statements
            if (str_contains($namespace, '\\') || str_contains($namespace, ' as ')) {
                $fileContents .= trim($namespace).PHP_EOL;
            }
        }

        $fileContents .= 'class '.$className.$parentClass.PHP_EOL;
        $fileContents .= '{'.PHP_EOL;
        $fileContents .= 'public array $info = ' . var_export($this->toArray(), true) . ';';
        $fileContents .= trim($body).PHP_EOL;
        $fileContents .= '}'.PHP_EOL;

        eval($fileContents);

        // Pass template ID in options
        $options['templateId'] = $this->id;

        // Pass strategy overrides from metadata (filter out null/empty values)
        $strategy = array_filter($this->metadata['strategy'] ?? [], function ($value) {
            return $value !== null && $value !== '';
        });
        if (!empty($strategy)) {
            $options['strategy'] = $strategy;
        }

        return new $className($state, $options);
    }

    public function beforeValidate()
    {
        try {
            $this->snake();
        } catch (\Throwable $e) {
            throw new ApplicationException($e->getMessage());
        }
    }

    /**
     * @see https://play.battlesnake.com/customizations
     */
    public function getHeadOptions(): array
    {
        return array_combine(static::OPTIONS_HEAD, static::OPTIONS_HEAD);
    }

    /**
     * @see https://play.battlesnake.com/customizations
     */
    public function getTailOptions(): array
    {
        return array_combine(static::OPTIONS_TAIL, static::OPTIONS_TAIL);
    }
}
