<?php

namespace enricodias\EmailValidator\Tests\Utils;

use Psr\Log\AbstractLogger;

/**
 * A simple in-memory PSR-3 logger used to assert log output in tests.
 */
final class ArrayLogger extends AbstractLogger
{
    /**
     * Recorded log entries.
     *
     * @var array
     */
    private $records = [];

    /**
     * Records a log entry.
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * Returns all recorded log entries.
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * Returns all recorded log entries for a given level.
     */
    public function getRecordsByLevel(string $level): array
    {
        return \array_values(\array_filter($this->records, static function (array $record) use ($level) {

            return $record['level'] === $level;

        }));
    }

    /**
     * Checks whether the given string appears in any recorded message or context value.
     */
    public function contains(string $needle): bool
    {
        foreach ($this->records as $record) {

            if (\stripos($record['message'], $needle) !== false) return true;

            if (\stripos(\json_encode($record['context']), $needle) !== false) return true;

        }

        return false;
    }
}
