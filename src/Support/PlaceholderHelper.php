<?php
declare(strict_types=1);

namespace Sprout\Support;

final class PlaceholderHelper
{
    /**
     * @param string                                            $pattern
     * @param array<lowercase-string, string|callable():string> $placeholders
     *
     * @return string
     */
    public static function replace(string $pattern, array $placeholders = []): string
    {
        $newString = $pattern;

        foreach ($placeholders as $placeholder => $replacement) {
            $newString = self::replacePlaceholder(
                $newString,
                $placeholder,
                ! is_string($replacement) ? $replacement() : $replacement
            );
        }

        return $newString;
    }

    /**
     * @param string $string
     * @param string $placeholder
     * @param string $value
     *
     * @return string
     */
    private static function replacePlaceholder(string $string, string $placeholder, string $value): string
    {
        return str_replace(
            [
                '-',
                '{' . strtolower($placeholder) . '}',
                '{' . ucfirst($placeholder) . '}',
                '{' . strtoupper($placeholder) . '}',
            ],
            [
                '_',
                $value,
                ucfirst($value),
                strtoupper($value),
            ],
            $string
        );
    }
}
