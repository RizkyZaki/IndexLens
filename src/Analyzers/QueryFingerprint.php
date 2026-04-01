<?php

namespace IndexLens\IndexLens\Analyzers;

class QueryFingerprint
{
    public function normalize(string $sql): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim(strtolower($sql)));
        $normalized = preg_replace('/\b\d+\b/', '?', $normalized ?? '');
        $normalized = preg_replace('/\'(?:[^\'\\]|\\.)*\'/', '?', $normalized ?? '');

        return $normalized ?? strtolower(trim($sql));
    }

    public function fingerprint(string $sql): string
    {
        return sha1($this->normalize($sql));
    }
}
