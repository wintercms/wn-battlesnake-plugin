<?php

namespace Winter\Battlesnake\Objects;

/**
 * @see https://docs.battlesnake.com/api/objects/board
 */
class Board
{
    use \Winter\Battlesnake\Traits\HasCoordinates;

    // AsciiBoard-compatible symbols
    public const CHAR_SPACE = ".";
    public const CHAR_FOOD = "F";
    public const CHAR_HAZARD = "H";
    // Your snake
    public const CHAR_YOU_HEAD = "Y";
    public const CHAR_YOU_BODY = "y";
    // Enemy snakes use A/a, B/b, C/c, etc.
    public const ENEMY_LETTERS = ['A', 'B', 'C', 'D', 'E', 'G', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Z'];

    // Legacy constants for danger detection (checks both formats)
    public const DANGER_CHARS = [
        'H', 'x',           // hazard
        'Y', 'y', 'h', 's', 't', // snake parts
        'A', 'a', 'B', 'b', 'C', 'c', 'D', 'd', 'E', 'e', 'G', 'g',
        'I', 'i', 'J', 'j', 'K', 'k', 'L', 'l', 'M', 'm', 'N', 'n',
        'O', 'o', 'P', 'p', 'Q', 'q', 'R', 'r', 'S', 'T', 'U', 'u',
        'V', 'v', 'W', 'w', 'X', 'Z', 'z',
    ];

    public int $height;
    public int $width;
    public array $food;
    public array $hazards;
    public array $snakes = [];
    public ?string $youId = null;

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
            @mkdir($directory, 0755, true);
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

        // Add food
        foreach ($this->food as $food) {
            $cells[$food['y']][$food['x']] = static::CHAR_FOOD;
        }

        // Add hazards
        foreach ($this->hazards as $hazard) {
            $cells[$hazard['y']][$hazard['x']] = static::CHAR_HAZARD;
        }

        // Add snakes with distinct symbols
        $enemyIndex = 0;
        foreach ($this->snakes as $snake) {
            if (empty($snake->body)) {
                continue;
            }

            $isYou = ($this->youId !== null && $snake->id === $this->youId);

            if ($isYou) {
                $headChar = static::CHAR_YOU_HEAD;
                $bodyChar = static::CHAR_YOU_BODY;
            } else {
                $letter = static::ENEMY_LETTERS[$enemyIndex % count(static::ENEMY_LETTERS)];
                $headChar = $letter;
                $bodyChar = strtolower($letter);
                $enemyIndex++;
            }

            // Clone body array so we don't modify the original
            $body = $snake->body;
            $head = array_shift($body);

            // Draw body segments
            foreach ($body as $segment) {
                $x = $segment['x'];
                $y = $segment['y'];
                $cells[$y][$x] = $bodyChar;
            }

            // Draw head on top
            $cells[$head['y']][$head['x']] = $headChar;
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
