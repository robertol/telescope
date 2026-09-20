<?php

namespace Laravel\Telescope\Observability;

/**
 * Conservative SQL fingerprint.
 *
 * Numeric and quoted literals are replaced with placeholders so structurally
 * equivalent queries share a hash. IN-lists of different length still differ
 * because expanding the list changes the SQL shape.
 */
class QueryFingerprint
{
    public function hash(string $sql, ?string $existingHash = null): string
    {
        if (is_string($existingHash) && $existingHash !== '') {
            return $existingHash;
        }

        return md5($this->normalize($sql));
    }

    public function normalize(string $sql): string
    {
        $normalized = preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'/", '?', $sql) ?? $sql;
        $normalized = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '?', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return strtolower(trim($normalized));
    }
}
