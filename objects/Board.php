<?php

namespace Winter\Battlesnake\Objects;

/**
 * @see https://docs.battlesnake.com/api/objects/board
 */
class Board
{
    use \Winter\Battlesnake\Traits\HasCoordinates;

    public const CHAR_SPACE = "_"; // https://en.wikipedia.org/wiki/Braille_pattern_dots-0
    public const CHAR_FOOD = "f";
    public const CHAR_HAZARD = "x";
    public const CHAR_SNAKE = "s";
    public const CHAR_SNAKE_TAIL = "t";
    public const CHAR_SNAKE_HEAD = "h";
    public const DANGER_CHARS = [
        self::CHAR_HAZARD,
        self::CHAR_SNAKE_HEAD,
        self::CHAR_SNAKE,
        self::CHAR_SNAKE_TAIL,
    ];

    public int $height;
    public int $width;
    public array $food;
    public array $hazards;
    public array $snakes = [];

    public function __construct(array $data = [])
    {
        $this->height = $data['height'] ?? 0;
        $this->width = $data['width'] ?? 0;
        $this->food = array_map([$this, 'normalizeCoordinates'], $data['food'] ?? []);
        $this->hazards = array_map([$this, 'normalizeCoordinates'], $data['hazards'] ?? []);
        $snakes = $data['snakes'] ?? [];
        foreach ($snakes as $snake) {
            $this->snakes[] = new Battlesnake($snake, $this);
        }
    }

    public function draw(string $output)
    {
        // Draw each cell as 10px + 1px border
        $width = $this->width * 12 + 1;
        $height = $this->height * 12 + 1;

        $img = imagecreate($width, $height);

        // Draw background
        $background = imagecolorallocate($img, 242, 242, 242);
        imagefilledrectangle($img, 0, 0, $width, $height, $background);

        // Draw the board
        $cellColour = imagecolorallocate($img, 255, 255, 255);
        foreach (range(0, $this->height) as $y) {
            foreach (range(0, $this->width) as $x) {
                imagefilledrectangle(
                    $img,
                    $x * 12 + 1,
                    $y * 12 + 1,
                    $x * 12 + 11,
                    $y * 12 + 11,
                    $cellColour
                );
            }
        }

        // Draw food
        $foodColour = imagecolorallocate($img, 255, 0, 0);
        foreach ($this->food as $food) {
            imagefilledellipse(
                $img,
                $food['x'] * 12 + 6,
                $food['y'] * 12 + 6,
                6,
                6,
                $foodColour
            );
        }

        // Draw hazard
        $hazardColour = imagecolorallocate($img, 0, 255, 255);
        foreach ($this->hazards as $hazards) {
            imagefilledellipse(
                $img,
                $hazards['x'] * 12 + 6,
                $hazards['y'] * 12 + 6,
                6,
                6,
                $hazardColour
            );
        }

        // Draw snakes
        foreach ($this->snakes as $snake) {
            $snakeColour = imagecolorallocate($img, ...sscanf($snake->customizations['color'], "#%02x%02x%02x"));
            $last = null;
            foreach ($snake->body as $body) {
                // Fill the cell the snake segment is in
                imagefilledrectangle(
                    $img,
                    $body['x'] * 12 + 3,
                    $body['y'] * 12 + 3,
                    $body['x'] * 12 + 9,
                    $body['y'] * 12 + 9,
                    $snakeColour
                );

                // Draw between the current and last cell
                if ($last) {
                    imagefilledrectangle(
                        $img,
                        $body['x'] * 12 + 3,
                        $body['y'] * 12 + 3,
                        $last['x'] * 12 + 9,
                        $last['y'] * 12 + 9,
                        $snakeColour
                    );
                }

                $last = $body;
            }

            // Draw face
            imagefilledrectangle(
                $img,
                $snake->head['x'] * 12 + 5,
                $snake->head['y'] * 12 + 5,
                $snake->head['x'] * 12 + 7,
                $snake->head['y'] * 12 + 7,
                imagecolorallocate($img, 0, 0, 0)
            );
        }

        $directory = dirname($output);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        imagepng($img, $output);
        imagedestroy($img);
    }

    public function toArray(): array
    {
        $cells = array_fill(0, $this->height, array_fill(0, $this->width, static::CHAR_SPACE));

        if (empty($cells)) {
            return [];
        }

        foreach ([
            'food' => static::CHAR_FOOD,
            'hazards' => static::CHAR_HAZARD,
            'snakes' => static::CHAR_SNAKE,
        ] as $key => $value) {
            foreach ($this->{$key} as $element) {
                if ($key === 'snakes') {
                    $head = array_shift($element->body);
                    $last = $element->body[count($element->body) - 1];
                    foreach ($element->body as $i => $body) {
                        $x = $body['x'];
                        $y = $body['y'];
                        $cells[$y][$x] = ($last['x'] === $x && $last['y'] === $y)
                            ? static::CHAR_SNAKE_TAIL
                            : $value;
                    }
                    $cells[$head['y']][$head['x']] = static::CHAR_SNAKE_HEAD;
                } else {
                    $cells[$element['y']][$element['x']] = $value;
                }
            }
        }

        return $cells;
    }

    public function toString(): string
    {
        $cells = $this->toArray();
        $str = '';
        foreach ($cells as $y => $row) {
            $line = '';
            foreach ($row as $x => $value) {
                $line .= $value . ' ';
            }
            $str .= trim($line) . PHP_EOL;
        }

        return $str;
    }
}
