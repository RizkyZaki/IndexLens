<?php

namespace IndexLens\IndexLens\Tests\Unit;

use IndexLens\IndexLens\Analyzers\QueryFingerprint;
use PHPUnit\Framework\TestCase;

class QueryFingerprintTest extends TestCase
{
    public function test_normalize_handles_single_quoted_literals(): void
    {
        $fingerprint = new QueryFingerprint();

        $sql = "select * from users where email = 'foo@example.com' and id = 123";
        $normalized = $fingerprint->normalize($sql);

        $this->assertStringContainsString("email = ?", $normalized);
        $this->assertStringContainsString("id = ?", $normalized);
    }
}
