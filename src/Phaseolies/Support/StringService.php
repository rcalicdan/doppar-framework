<?php

namespace Phaseolies\Support;

class StringService
{
    /**
     * Extract a substring from the given input string.
     */
    public function substr(string $input, int $start, ?int $length = null): string
    {
        return mb_substr($input, $start, $length, 'UTF-8');
    }

    /**
     * Compute the length of the given string.
     */
    public function len(string $input): int
    {
        return mb_strlen($input, 'UTF-8');
    }

    /**
     * Count the number of words in a string.
     */
    public function countWord(string $string): int
    {
        preg_match_all('/\p{L}[\p{L}\p{Mn}\p{Pd}\'’]*/u', $string, $matches);

        return count($matches[0]);
    }

    /**
     * Check if a given string is a palindrome.
     */
    public function isPalindrome(string $string): bool
    {
        $cleaned = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $string) ?? '');

        return $cleaned === strrev($cleaned);
    }

    /**
     * Generate a random alphanumeric string of a given length.
     */
    public function random(int $length = 10): string
    {
        return substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', $length)), 0, $length);
    }

    /**
     * Convert a snake_case or kebab-case string to camelCase.
     */
    public function camel(string $input): string
    {
        $input = str_replace(['-', '_'], ' ', $input);
        $input = mb_convert_case($input, MB_CASE_TITLE, 'UTF-8');
        $input = str_replace(' ', '', $input);

        return lcfirst($input);
    }

    /**
     * Mask a string with a specified number of visible characters at the start and end.
     */
    public function mask(string $string, int $visibleFromStart = 1, int $visibleFromEnd = 1, string $maskCharacter = '*'): string
    {
        $length = strlen($string);

        if ($length <= $visibleFromStart + $visibleFromEnd) {
            return $string;
        }

        $startPart  = substr($string, 0, $visibleFromStart);
        $endPart    = substr($string, -$visibleFromEnd);
        $middlePart = str_repeat($maskCharacter, $length - $visibleFromStart - $visibleFromEnd);

        return $startPart . $middlePart . $endPart;
    }

    /**
     * Truncate a string to a specific length and append a suffix if truncated.
     */
    public function truncate(string $string, int $maxLength, string $suffix = '...'): string
    {
        return (strlen($string) > $maxLength) ? substr($string, 0, $maxLength) . $suffix : $string;
    }

    /**
     * Convert a camelCase string to snake_case.
     */
    public function snake(string $input): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input) ?? '');
    }

    /**
     * Convert a string to title case (each word capitalized).
     */
    public function title(string $input): string
    {
        return mb_convert_case($input, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Generate a URL-friendly slug from a string.
     */
    public function slug(string $input, string $separator = '-'): string
    {
        $slug = mb_strtolower($input, 'UTF-8');
        $slug = preg_replace('/[\s,\.;\'"!\?]+/u', $separator, $slug) ?? '';
        $slug = preg_replace('/[^\p{L}\p{Nd}' . preg_quote($separator, '/') . ']+/u', '', $slug) ?? '';
        $slug = preg_replace('/' . preg_quote($separator, '/') . '{2,}/u', $separator, $slug) ?? '';

        return trim($slug, $separator);
    }

    /**
     * Check if a string contains another string (case-insensitive or sensitive).
     *
     * @param string|array<int, string> $needles
     */
    public function contains(string $haystack, string|array $needles, bool $ignoreCase = true): bool
    {
        if (is_array($needles)) {
            foreach ($needles as $needle) {
                if ($this->contains($haystack, $needle, $ignoreCase)) {
                    return true;
                }
            }
            return false;
        }

        if ($ignoreCase) {
            return mb_stripos($haystack, $needles, 0, 'UTF-8') !== false;
        }

        return mb_strpos($haystack, $needles, 0, 'UTF-8') !== false;
    }

    /**
     * Limit the number of words in a string, handling Unicode and preserving
     * natural word boundaries (splitting on whitespace).
     */
    public function limitWords(string $string, int $words, string $end = '...', string $encoding = 'UTF-8'): string
    {
        $trimmed = trim($string);

        if ($trimmed === '') {
            return '';
        }

        $wordArray = preg_split('/\s+/u', $trimmed);

        if ($wordArray === false || count($wordArray) <= $words) {
            return $string;
        }

        $limited = implode(' ', array_slice($wordArray, 0, $words));

        return $limited . $end;
    }

    /**
     * Determine if a string matches a given pattern.
     */
    public function is(string $pattern, string $value): bool
    {
        if ($pattern === $value) {
            return true;
        }

        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);

        return (bool) preg_match('#^' . $pattern . '\z#u', $value);
    }

    /**
     * Remove all whitespace from a string.
     */
    public function removeWhiteSpace(string $input): string
    {
        return preg_replace('/\s+/u', '', $input) ?? '';
    }

    /**
     * Generate a UUID v4 string.
     */
    public function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Check if a string starts with one or more given needles (case-sensitive).
     *
     * @param string|array<int, string> $needles
     */
    public function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle === '') {
                continue;
            }

            if (mb_strpos($haystack, $needle, 0, 'UTF-8') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a string ends with one or more given needles (case‐sensitive).
     *
     * @param string|array<int, string> $needles
     */
    public function endsWith(string $haystack, string|array $needles, string $encoding = 'UTF-8'): bool
    {
        $haystackLength = mb_strlen($haystack, $encoding);

        foreach ((array) $needles as $needle) {
            if ($needle === '') {
                continue;
            }

            $needleLength = mb_strlen($needle, $encoding);

            if ($needleLength > $haystackLength) {
                continue;
            }

            $endingSegment = mb_substr($haystack, $haystackLength - $needleLength, $needleLength, $encoding);

            if ($endingSegment === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert a string to StudlyCase (each “word” capitalized, no separators).
     */
    public function studly(string $input, string $encoding = 'UTF-8'): string
    {
        $spaced = str_replace(['-', '_'], ' ', $input);
        $titled = mb_convert_case($spaced, MB_CASE_TITLE, $encoding);

        return str_replace(' ', '', $titled);
    }

    /**
     * Reverse a UTF-8 string while preserving multi-byte characters.
     */
    public function reverse(string $input, string $encoding = 'UTF-8'): string
    {
        $chars = mb_str_split($input, 1, $encoding);

        return implode('', array_reverse($chars));
    }

    /**
     * Extract all numeric digits from a string.
     */
    public function extractNumbers(string $input): string
    {
        return preg_replace('/\D/', '', $input) ?? '';
    }

    /**
     * Find the longest common substring between two strings.
     */
    public function longestCommonSubstring(string $str1, string $str2): string
    {
        $matrix    = array_fill(0, strlen($str1) + 1, array_fill(0, strlen($str2) + 1, 0));
        $maxLength = 0;
        $endIndex  = 0;

        for ($i = 1; $i <= strlen($str1); $i++) {
            for ($j = 1; $j <= strlen($str2); $j++) {
                if ($str1[$i - 1] === $str2[$j - 1]) {
                    $matrix[$i][$j] = $matrix[$i - 1][$j - 1] + 1;
                    if ($matrix[$i][$j] > $maxLength) {
                        $maxLength = $matrix[$i][$j];
                        $endIndex  = $i;
                    }
                }
            }
        }

        return substr($str1, $endIndex - $maxLength, $maxLength);
    }

    /**
     * Convert a string to leetspeak (1337).
     */
    public function leetSpeak(string $input): string
    {
        $map = [
            'a' => '4',
            'e' => '3',
            'i' => '1',
            'o' => '0',
            's' => '5',
            't' => '7'
        ];

        return strtr(strtolower($input), $map);
    }

    /**
     * Extract all email addresses from a string.
     *
     * @return list<string>
     */
    public function extractEmails(string $input): array
    {
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $input, $matches);

        return $matches[0];
    }

    /**
     * Highlight all occurrences of a keyword in a string using HTML tags.
     */
    public function highlightKeyword(string $input, string $keyword, string $tag = 'strong'): string
    {
        if (empty($keyword)) {
            return $input;
        }

        return preg_replace(
            '/(' . preg_quote($keyword, '/') . ')/i',
            "<$tag>$1</$tag>",
            $input
        ) ?? $input;
    }

    /**
     * Convert string to uppercase.
     */
    public function toUpper(string $input): string
    {
        return strtoupper($input);
    }

    /**
     * Append a suffix to a string if it doesn't already end with it.
     */
    public function suffixAppend(string $input, string $suffix): string
    {
        if ($this->endsWith($input, $suffix)) {
            return $input;
        }

        if ($input === '') {
            return $suffix;
        }

        return strtoupper($input[0]) . substr($input, 1) . $suffix;
    }

    /**
     * Remove a suffix from a string.
     */
    public function removeSuffix(string $input, string $suffix): string
    {
        return substr($input, 0, -strlen($suffix));
    }

    /**
     * Get the portion of $subject after the first occurrence of $search.
     */
    public function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = mb_strpos($subject, $search, 0, 'UTF-8');

        return $pos === false ? $subject : mb_substr($subject, $pos + mb_strlen($search, 'UTF-8'), null, 'UTF-8');
    }

    /**
     * Get the portion of $subject before the first occurrence of $search.
     */
    public function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = mb_strpos($subject, $search, 0, 'UTF-8');

        return $pos === false ? $subject : mb_substr($subject, 0, $pos, 'UTF-8');
    }

    /**
     * Get the portion between two values.
     */
    public function between(string $subject, string $from, string $to): string
    {
        $start = $this->after($subject, $from);
        return $this->before($start, $to);
    }

    /**
     * Determine if a given string is valid JSON.
     */
    public function isJson(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return json_validate($value);
    }

    /**
     * Harmonize the given URL.
     */
    public function urlHarmonize(string $url): string
    {
        return str_replace("\\", "/", $url);
    }
}