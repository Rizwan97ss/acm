<?php

namespace App\Services\Audit;

use App\Http\Middleware\LogActivity;
use Carbon\CarbonImmutable;

/**
 * Reads the daily activity log files written by the LogActivity middleware, newest
 * entries first. Only the tail of very large files is read.
 */
class ActivityLogReader
{
    private const int MAX_BYTES = 5 * 1024 * 1024;

    private const string LINE_PATTERN = '/^\[(?<time>[^\]]+)\] \w+\.(?<level>[A-Z]+): (?<method>\S+) (?<route>\S+) (?<context>\{.*\}) (?<extra>\{[^{}]*\}|\[\])$/';

    /**
     * Dates (Y-m-d) that have an activity log file, newest first.
     *
     * @return list<string>
     */
    public function dates(): array
    {
        $dates = [];

        foreach (glob($this->directory().'/'.LogActivity::CHANNEL.'-*.log') ?: [] as $file) {
            if (preg_match('/-(\d{4}-\d{2}-\d{2})\.log$/', $file, $match) === 1) {
                $dates[] = $match[1];
            }
        }

        rsort($dates);

        return $dates;
    }

    /**
     * @param  array{school_id: int|null, user_id?: int|null, level?: string|null, search?: string|null}  $filters
     * @return list<array{time: string, level: string, method: string, route: string, action: string|null, user_id: int|null, parameters: array<string, mixed>, fields: list<string>, status: int|null, duration_ms: int|null, ip: string|null}>
     */
    public function entries(CarbonImmutable $date, array $filters, int $limit = 200): array
    {
        $path = $this->directory().'/'.LogActivity::CHANNEL.'-'.$date->format('Y-m-d').'.log';

        if (! is_file($path)) {
            return [];
        }

        $entries = [];

        foreach (array_reverse($this->tailLines($path)) as $line) {
            $entry = $this->parse($line);

            if ($entry === null || ! $this->matches($entry, $filters)) {
                continue;
            }

            unset($entry['school_id']);
            $entries[] = $entry;

            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function tailLines(string $path): array
    {
        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $offset = max(0, $size - self::MAX_BYTES);
        fseek($handle, $offset);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        $lines = preg_split('/\R/', $content) ?: [];

        if ($offset > 0) {
            array_shift($lines);
        }

        return array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
    }

    /**
     * @return array{time: string, level: string, method: string, route: string, action: string|null, user_id: int|null, parameters: array<string, mixed>, fields: list<string>, status: int|null, duration_ms: int|null, ip: string|null, school_id: int|null}|null
     */
    private function parse(string $line): ?array
    {
        if (preg_match(self::LINE_PATTERN, $line, $match) !== 1) {
            return null;
        }

        $context = json_decode($match['context'], true);
        $extra = json_decode($match['extra'], true);

        if (! is_array($context)) {
            return null;
        }

        return [
            'time' => $match['time'],
            'level' => strtolower($match['level']),
            'method' => $match['method'],
            'route' => $match['route'],
            'action' => isset($context['action']) ? (string) $context['action'] : null,
            'user_id' => isset($context['user_id']) ? (int) $context['user_id'] : null,
            'parameters' => is_array($context['parameters'] ?? null) ? $context['parameters'] : [],
            'fields' => is_array($context['fields'] ?? null) ? array_values(array_map(strval(...), $context['fields'])) : [],
            'status' => isset($context['status']) ? (int) $context['status'] : null,
            'duration_ms' => isset($context['duration_ms']) ? (int) $context['duration_ms'] : null,
            'ip' => isset($context['ip']) ? (string) $context['ip'] : null,
            'school_id' => is_array($extra) && isset($extra['school_id']) ? (int) $extra['school_id'] : null,
        ];
    }

    /**
     * @param  array{school_id: int|null, user_id: int|null, level: string, route: string, action: string|null}  $entry
     * @param  array{school_id: int|null, user_id?: int|null, level?: string|null, search?: string|null}  $filters
     */
    private function matches(array $entry, array $filters): bool
    {
        if ($filters['school_id'] !== null && $entry['school_id'] !== $filters['school_id']) {
            return false;
        }

        if (($filters['user_id'] ?? null) !== null && $entry['user_id'] !== $filters['user_id']) {
            return false;
        }

        if (($filters['level'] ?? null) !== null && $entry['level'] !== $filters['level']) {
            return false;
        }

        $search = $filters['search'] ?? null;

        return $search === null || $search === ''
            || str_contains(strtolower($entry['route']), strtolower($search))
            || str_contains(strtolower((string) $entry['action']), strtolower($search));
    }

    private function directory(): string
    {
        return dirname((string) config('logging.channels.'.LogActivity::CHANNEL.'.path'));
    }
}
