<?php

namespace Neuedaten\Freezed\Services;

class LogService {
    protected static self|null $instance = null;

    const TYPES = [
        'info' => 'info',
        'warning' => 'warning',
        'error' => 'error',
    ];

    protected array $logs = [];

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function add(string $message, string $type = self::TYPES['info'], int $level = 0): void
    {
        $entry = [
            'message' => $message,
            'type' => $type,
            'level' => $level,
        ];

        $this->logs[]  = $entry;
        $this->print($entry);
    }

    public function print(array $entry): void
    {
       // echo $entry['message'] . PHP_EOL;
    }

    public function getAll(): array
    {
        return $this->logs;
    }
}
