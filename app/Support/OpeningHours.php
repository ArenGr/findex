<?php

namespace App\Support;

// Turns the free text banks print next to a branch ("Mon.
class OpeningHours
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    private const DAY_NAMES = [
        'monday' => 'mon', 'tuesday' => 'tue', 'wednesday' => 'wed',
        'thursday' => 'thu', 'friday' => 'fri', 'saturday' => 'sat', 'sunday' => 'sun',
        'mon' => 'mon', 'tue' => 'tue', 'tues' => 'tue', 'wed' => 'wed',
        'thu' => 'thu', 'thur' => 'thu', 'thurs' => 'thu', 'fri' => 'fri',
        'sat' => 'sat', 'sun' => 'sun',
    ];

    private const ALL_DAY = ['00:00', '23:59'];

    private const ALL_DAY_PATTERN = '/round the clock|24\s*\/\s*7|24 hours|non-?stop/u';

    // Punctuation that separates an hour from its minutes on Armenian sites.
    private const TIME_SEPARATORS = [
        "\u{589}" => ':',   // Armenian full stop
        "\u{55d}" => ':',   // Armenian comma, used the same way
        "\u{ff1a}" => ':',  // fullwidth colon
        "\u{2236}" => ':',  // ratio
    ];

    // Cyrillic lookalikes.
    private const HOMOGLYPHS = [
        'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c',
        'х' => 'x', 'у' => 'y', 'М' => 'M', 'Т' => 'T', 'В' => 'B',
        'А' => 'A', 'Е' => 'E', 'О' => 'O', 'Р' => 'P', 'С' => 'C',
    ];

    /**
     * @return array<string, array{0: string, 1: string}|null>|null
     */
    public static function parse(?string $text): ?array
    {
        if ($text === null) {
            return null;
        }

        $text = self::normalize($text);

        if ($text === '') {
            return null;
        }

        $hours = [];

        foreach (self::rules($text) as [$days, $span]) {
            foreach ($days as $day) {
                $hours[$day] = $span;
            }
        }

        // "24/7" on its own names no day, but describes every one of them.
        if ($hours === [] && preg_match(self::ALL_DAY_PATTERN, $text)) {
            return array_fill_keys(self::DAYS, self::ALL_DAY);
        }

        if ($hours === []) {
            return null;
        }

        // Days the text never mentions are genuinely shut - a branch listing "Mon.
        return array_replace(array_fill_keys(self::DAYS, null), $hours);
    }

    /**
     * @param  array<string, array{0: string, 1: string}|null>  $byDay
     * @return array<string, array{0: string, 1: string}|null>|null
     */
    public static function fromDays(array $byDay): ?array
    {
        $hours = [];

        foreach ($byDay as $name => $span) {
            $day = self::dayKey((string) $name);

            if ($day === null) {
                continue;
            }

            if ($span === null) {
                $hours[$day] = null;

                continue;
            }

            $open = self::clockTime($span[0] ?? null);
            $close = self::clockTime($span[1] ?? null);

            // Half a span is not a span.
            if ($open === null || $close === null) {
                continue;
            }

            $hours[$day] = [$open, $close];
        }

        if ($hours === []) {
            return null;
        }

        return array_replace(array_fill_keys(self::DAYS, null), $hours);
    }

    public static function dayKey(string $name): ?string
    {
        $name = rtrim(strtolower(trim(strtr($name, self::HOMOGLYPHS))), '.');

        return self::DAY_NAMES[$name] ?? null;
    }

    // Accepts "9:15", "09:15", and the "09:15:00.000" that databases hand back for a time column.
    private static function clockTime(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = strtr(trim((string) $value), self::TIME_SEPARATORS);

        if (! preg_match('/^(\d{1,2})[:.](\d{2})/', $value, $m)) {
            return null;
        }

        return self::time((int) $m[1], (int) $m[2]);
    }

    private static function normalize(string $text): string
    {
        $text = strtr($text, self::HOMOGLYPHS + self::TIME_SEPARATORS);

        $text = preg_replace('#<(br|/p|/div|/li|/tr)\b[^>]*>#i', "\n", $text) ?? $text;

        $text = str_replace(["\u{a0}", '–', '—', '−'], [' ', '-', '-', '-'], $text);
        $text = strtolower(strip_tags($text));

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * @return array<int, array{0: array<int, string>, 1: array{0: string, 1: string}|null}>
     */
    private static function rules(string $text): array
    {
        $names = implode('|', array_keys(self::DAY_NAMES));

        $pattern = '/\b(?P<days>(?:'.$names.')\.?(?:\s*(?:-|,|and|&|to)\s*(?:'.$names.')\.?)*)'
            // The gap between a day and its hours: a colon, a dash, spaces.
            .'[^0-9a-z]{0,4}'
            .'(?P<span>\d{1,2}[:.]\d{2}\s*-\s*\d{1,2}[:.]\d{2}'
            .'|round the clock|24\s*\/\s*7|24 hours|non-?stop'
            .'|closed|non-working|day off|not working)/u';

        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        $rules = [];

        foreach ($matches as $match) {
            $days = self::daysIn($match['days']);
            $span = self::spanIn($match['span']);

            if ($days !== [] && $span !== false) {
                $rules[] = [$days, $span];
            }
        }

        return $rules;
    }

    /**
     * @return array<int, string>
     */
    private static function daysIn(string $expression): array
    {
        $names = implode('|', array_keys(self::DAY_NAMES));

        // "mon - fri" is a range; "mon, wed" is a list.
        if (preg_match("/^\s*({$names})\.?\s*(?:-|to)\s*({$names})\.?\s*$/u", $expression, $m)) {
            return self::range(self::DAY_NAMES[$m[1]], self::DAY_NAMES[$m[2]]);
        }

        preg_match_all("/\b({$names})\b/u", $expression, $m);

        return array_values(array_unique(array_map(
            fn (string $name) => self::DAY_NAMES[$name],
            $m[1] ?? [],
        )));
    }

    /** @return array<int, string> */
    private static function range(string $from, string $to): array
    {
        $start = array_search($from, self::DAYS, true);
        $end = array_search($to, self::DAYS, true);

        if ($start === false || $end === false) {
            return [];
        }

        // A range that wraps the week ("sat - mon") is still one range.
        $length = $end >= $start
            ? $end - $start + 1
            : count(self::DAYS) - $start + $end + 1;

        $days = [];

        for ($i = 0; $i < $length; $i++) {
            $days[] = self::DAYS[($start + $i) % count(self::DAYS)];
        }

        return $days;
    }

    /**
     * @return array{0: string, 1: string}|null|false false when unreadable,
     *                                                null when explicitly closed
     */
    private static function spanIn(string $expression): array|null|false
    {
        if (preg_match(self::ALL_DAY_PATTERN, $expression)) {
            return self::ALL_DAY;
        }

        if (preg_match('/closed|non-working|day off|not working/u', $expression)) {
            return null;
        }

        if (! preg_match('/(\d{1,2})[:.](\d{2})\s*-\s*(\d{1,2})[:.](\d{2})/u', $expression, $m)) {
            return false;
        }

        $open = self::time((int) $m[1], (int) $m[2]);
        $close = self::time((int) $m[3], (int) $m[4]);

        return $open === null || $close === null ? false : [$open, $close];
    }

    private static function time(int $hour, int $minute): ?string
    {
        if ($hour > 24 || $minute > 59) {
            return null;
        }

        // Some sites write midnight as 24:00; the app stores clock times.
        if ($hour === 24) {
            $hour = $minute === 0 ? 23 : 0;
            $minute = $minute === 0 ? 59 : $minute;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }
}
