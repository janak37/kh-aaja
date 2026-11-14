<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;

/**
 * A logging "tap": replaces Nepali mobile numbers in every log line with [phone],
 * so customers' numbers never end up in log files or error reports.
 */
class MaskPersonalData
{
    /** 98xxxxxxxx, 97xxxxxxxx, 96xxxxxxxx, with or without +977. */
    public const PHONE_PATTERN = '/(?<!\d)(?:\+?977[\s-]?)?9[678]\d{8}(?!\d)/';

    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof Monolog) {
            $monolog->pushProcessor(fn (LogRecord $record): LogRecord => $this->mask($record));
        }
    }

    public function mask(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->clean($record->message),
            context: $this->cleanArray($record->context),
            extra: $this->cleanArray($record->extra),
        );
    }

    private function clean(string $text): string
    {
        return (string) preg_replace(self::PHONE_PATTERN, '[phone]', $text);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function cleanArray(array $data): array
    {
        array_walk_recursive($data, function (mixed &$value): void {
            if (is_string($value)) {
                $value = $this->clean($value);
            }
        });

        return $data;
    }
}
