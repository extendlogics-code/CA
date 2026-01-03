<?php
declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) { require __DIR__ . '/../vendor/autoload.php'; }

use PHPUnit\Framework\TestCase;

final class AuditWriterTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = __DIR__ . '/../storage/logs/audit.log';
        if (!file_exists($this->logPath)) {
            touch($this->logPath);
        }
    }

    public function testWriteAuditAppendsLogForClientCreate(): void
    {
        $this->logPath = audit_log_path();
        $before = file_exists($this->logPath) ? filesize($this->logPath) : 0;
        write_audit('client', 123, 'create', ['id'=>1,'name'=>'Alice','role'=>'ceo'], ['data'=>['name'=>'ACME']]);
        clearstatcache(true, $this->logPath);
        $after = filesize($this->logPath);
        $this->assertGreaterThan($before, $after);

        $lines = file($this->logPath);
        $tail = trim(end($lines));
        $this->assertStringContainsString('Audit[client]', $tail);
        $this->assertStringContainsString('#123 create', $tail);
        $this->assertStringContainsString('Alice', $tail);
    }
}
