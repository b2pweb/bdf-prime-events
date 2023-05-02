<?php

namespace Psr\Log\Test;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

if (class_exists('Psr\Log\Test\TestLogger')) {
    return;
}

class TestLogger implements LoggerInterface
{
    use LoggerTrait;

    private $records = [];

    public function getRecords()
    {
        return $this->records;
    }

    public function hasRecordThatContains($record, $level = null)
    {
        foreach ($this->records as $rec) {
            if (strpos($rec['message'], $record) !== false && ($level === null || $rec['level'] === $level)) {
                return true;
            }
        }

        return false;
    }

    public function clear()
    {
        $this->records = [];
    }

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
