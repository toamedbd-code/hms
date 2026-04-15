<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleHolidayCalendarService
{
    /**
     * @return array<string, string> key=date(Y-m-d), value=holiday title
     */
    public function getHolidayMapForYear(int $year, ?string $country = null): array
    {
        $countryCode = strtolower(trim((string) ($country ?: config('attendance.google_holidays.country', 'bd'))));
        $url = $this->resolveCalendarUrl($countryCode);
        if ($url === '') {
            return $this->readCachedHolidayMap($year, $countryCode);
        }

        $normalizedUrl = $this->normalizeCalendarUrl($url);

        try {
            $response = Http::connectTimeout(8)
                ->timeout(20)
                ->retry(2, 1000, throw: false)
                ->get($normalizedUrl);

            if ($response->successful()) {
                $map = $this->parseIcsHolidayMap($response->body(), $year);
                $this->writeCachedHolidayMap($year, $countryCode, $map);

                return $map;
            }
        } catch (\Throwable $e) {
            Log::warning('Google holiday fetch failed, using cached fallback.', [
                'year' => $year,
                'country' => $countryCode,
                'url' => $normalizedUrl,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->readCachedHolidayMap($year, $countryCode);
    }

    private function resolveCalendarUrl(?string $country = null): string
    {
        $customUrl = trim((string) config('attendance.google_holidays.calendar_ics_url', ''));
        if ($customUrl !== '') {
            return $customUrl;
        }

        $resolvedCountry = strtolower(trim((string) ($country ?: config('attendance.google_holidays.country', 'bd'))));
        $map = config('attendance.google_holidays.country_calendar_map', []);

        if (is_array($map) && isset($map[$resolvedCountry]) && is_string($map[$resolvedCountry])) {
            return trim($map[$resolvedCountry]);
        }

        return '';
    }

    private function normalizeCalendarUrl(string $url): string
    {
        // If .env contains an unencoded #, rebuild URL by encoding the fragment marker.
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        if (is_string($fragment) && $fragment !== '') {
            $base = strstr($url, '#', true);
            if ($base !== false) {
                return $base . '%23' . rawurlencode($fragment);
            }
        }

        return $url;
    }

    /**
     * @return array<string, string>
     */
    private function readCachedHolidayMap(int $year, string $country): array
    {
        $path = $this->getCachePath($year, $country);
        if (!Storage::disk('local')->exists($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) Storage::disk('local')->get($path), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return [];
            }

            $result = [];
            foreach ($decoded as $date => $title) {
                if (!is_string($date) || !is_string($title)) {
                    continue;
                }
                $result[$date] = $title;
            }

            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string, string> $map
     */
    private function writeCachedHolidayMap(int $year, string $country, array $map): void
    {
        try {
            Storage::disk('local')->put(
                $this->getCachePath($year, $country),
                json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        } catch (\Throwable $e) {
            // Cache write failures should never break payroll/attendance flows.
        }
    }

    private function getCachePath(int $year, string $country): string
    {
        return 'attendance/holiday-cache/' . $country . '-' . $year . '.json';
    }

    /**
     * @return array<string, string> key=date(Y-m-d), value=holiday title
     */
    private function parseIcsHolidayMap(string $icsBody, int $year): array
    {
        $holidayMap = [];

        if (trim($icsBody) === '') {
            return $holidayMap;
        }

        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $icsBody, $matches);
        $events = $matches[1] ?? [];

        foreach ($events as $eventBlock) {
            $summary = $this->extractField($eventBlock, 'SUMMARY');
            $dtStartRaw = $this->extractField($eventBlock, 'DTSTART');

            if ($dtStartRaw === null) {
                continue;
            }

            $date = $this->parseIcsDateToCarbon($dtStartRaw);
            if (!$date || (int) $date->year !== $year) {
                continue;
            }

            $holidayMap[$date->toDateString()] = $summary ?: 'Public Holiday';
        }

        return $holidayMap;
    }

    private function extractField(string $eventBlock, string $fieldName): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', $eventBlock) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (stripos($line, $fieldName . ';') === 0 || stripos($line, $fieldName . ':') === 0) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    return trim($parts[1]);
                }
            }
        }

        return null;
    }

    private function parseIcsDateToCarbon(string $raw): ?Carbon
    {
        // Handles formats like: 20260101 or 20260101T000000Z
        if (preg_match('/^(\d{8})/', $raw, $m) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Ymd', $m[1])->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
