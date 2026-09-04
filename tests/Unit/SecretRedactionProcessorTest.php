<?php

namespace Tests\Unit;

use App\Logging\SecretRedactionProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class SecretRedactionProcessorTest extends TestCase
{
    public function test_redacts_sensitive_fields_and_embedded_secrets(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'DB_PASSWORD=hunter2 url=https://example.test/?token=secret123',
            context: [
                'client_secret' => 'hidden-value',
                'headers' => ['x-api-key' => 'hidden-key', 'cookie' => 'session=abc'],
            ],
        );

        $redacted = (new SecretRedactionProcessor())($record);
        $serialized = json_encode([$redacted->message, $redacted->context]);

        $this->assertStringNotContainsString('hunter2', $serialized);
        $this->assertStringNotContainsString('secret123', $serialized);
        $this->assertStringNotContainsString('hidden-value', $serialized);
        $this->assertStringNotContainsString('hidden-key', $serialized);
        $this->assertStringNotContainsString('session=abc', $serialized);
    }
}
