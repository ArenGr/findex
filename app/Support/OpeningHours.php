<?php

namespace App\Support;

/**
 * Turns the free text banks print next to a branch ("Mon. - Fri.: 9:15-17:30")
 * into the day-keyed array Branch::$opening_hours casts to:
 *
 *   ['mon' => ['09:15', '17:30'], ..., 'sat' => null, 'sun' => null]
 *
 * A day mapped to null means closed, which the UI renders differently from a
 * missing entry ("we don't know"). So a day this cannot read is left out
 * rather than assumed shut - claiming a branch is closed when it isn't sends
 * someone to a locked door.
 */
class OpeningHours
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * Longer spellings first: matching "mon" inside "monday" would leave a
     * stray "day" behind and break the range that follows.
     */
    private const DAY_NAMES = [
        'monday' => 'mon', 'tuesday' => 'tue', 'wednesday' => 'wed',
        'thursday' => 'thu', 'friday' => 'fri', 'saturday' => 'sat', 'sunday' => 'sun',
        'mon' => 'mon', 'tue' => 'tue', 'tues' => 'tue', 'wed' => 'wed',
        'thu' => 'thu', 'thur' => 'thu', 'thurs' => 'thu', 'fri' => 'fri',
        'sat' => 'sat', 'sun' => 'sun',
    ];

    private const ALL_DAY = ['00:00', '23:59'];

    private const ALL_DAY_PATTERN = '/round the clock|24\s*\/\s*7|24 hours|non-?stop/u';

    /**
     * Punctuation that separates an hour from its minutes on Armenian
     * sites. Araratbank writes its times with the Armenian full stop
     * (U+0589) rather than a colon - visually a colon, and it cost that
     * bank half its opening hours before this map existed.
     */
    private const TIME_SEPARATORS = [
        "\u{589}" => ':',   // Armenian full stop
        "\u{55d}" => ':',   // Armenian comma, used the same way
        "\u{ff1a}" => ':',  // fullwidth colon
        "\u{2236}" => ':',  // ratio
    ];

    /**
     * Cyrillic lookalikes. Armenian sites are authored on keyboards that
     * switch layouts constantly, and the text really does arrive with a
     * Cyrillic а in "аround the clock" or a Cyrillic о in "Мon" - visually
     * identical, and invisible to an ASCII pattern.
     */
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

        // Days the text never mentions are genuinely shut - a branch listing
        // "Mon. - Fri." is telling you about its weekend too.
        return array_replace(array_fill_keys(self::DAYS, null), $hours);
    }

    /**
     * Build the same structure from a source that already publishes hours
     * per weekday, instead of as a sentence to be read - AMIO ships
     * {"weekdayEn":"Monday","opensAt":"09:15:00.000","closesAt":"16:45:00.000"}.
     *
     * Keys may be full day names or the short forms. A day mapped to null is
     * closed; a day left out entirely is also closed, since a source listing
     * its days one by one has said all it intends to about the rest.
     *
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

            // Half a span is not a span. Left out rather than recorded as
            // closed - see the note on this class.
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

    /**
     * Accepts "9:15", "09:15", and the "09:15:00.000" that databases hand
     * back for a time column.
     */
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

        // Every rule sits in its own <br> or block element, and strip_tags
        // closes the gap without leaving a separator. The rule scanner
        // tolerates that now, but a break is still a real boundary and
        // keeping it stops two rules being read as one phrase.
        $text = preg_replace('#<(br|/p|/div|/li|/tr)\b[^>]*>#i', "\n", $text) ?? $text;

        $text = str_replace(["\u{a0}", '–', '—', '−'], [' ', '-', '-', '-'], $text);
        $text = strtolower(strip_tags($text));

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * Every rule in the text, found by anchoring on the days rather than by
     * splitting the text into lines.
     *
     * Splitting was the earlier approach and it silently lost hours:
     * Araratbank prints "Monday-Friday 09:00-17:00 Saturday 10:00-14:00" as
     * one unbroken line, so only the first rule was read and Saturday came
     * out closed - a branch that opens shown as shut. Anchoring on day names
     * reads both, and lines with no day in them at all (ACBA's "Cash
     * register service: 09:30-16:30", which closes an hour before the
     * branch) are ignored for free.
     *
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
        // "mon - fri" and "monday to friday" are both ranges.
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
