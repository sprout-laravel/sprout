<?php
declare(strict_types=1);

namespace Sprout\Core\Support;

final class PlaceholderHelper
{
    /**
     * @param string                                            $pattern
     * @param array<lowercase-string, string|callable():string> $placeholders
     *
     * @return string
     */
    public static function replaceForParameter(string $pattern, array $placeholders = []): string
    {
        return self::replace($pattern, $placeholders, true);
    }

    /**
     * @param string                                            $pattern
     * @param array<lowercase-string, string|callable():string> $placeholders
     * @param bool                                              $forParameter
     *
     * @return string
     */
    public static function replace(string $pattern, array $placeholders = [], bool $forParameter = false): string
    {
        $newString = $pattern;

        foreach ($placeholders as $placeholder => $replacement) {
            $newString = self::replacePlaceholder(
                $newString,
                $placeholder,
                ! is_string($replacement) ? $replacement() : $replacement,
                $forParameter
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
    private static function replacePlaceholder(string $string, string $placeholder, string $value, bool $forParameter): string
    {
        $search = [
            '{' . strtolower($placeholder) . '}',
            '{' . ucfirst($placeholder) . '}',
            '{' . strtoupper($placeholder) . '}',
        ];

        $replace = [
            $value,
            ucfirst($value),
            strtoupper($value),
        ];

        if ($forParameter) {
            $search[]  = '-';
            $replace[] = '_';
        }

        return str_replace(
            $search,
            $replace,
            $string
        );
    }
}
