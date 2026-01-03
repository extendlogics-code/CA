<?php
declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) { require __DIR__ . '/../vendor/autoload.php'; }

use PHPUnit\Framework\TestCase;

final class BusinessFlowAuditTest extends TestCase
{
    /**
     * @dataProvider domainProvider
     */
    public function testWriteAuditForDomain(string $domain, int $entityId, string $action): void
    {
        $path = audit_log_path();
        $before = file_exists($path) ? filesize($path) : 0;
        write_audit($domain, $entityId, $action, ['id'=>2,'name'=>'Bob','role'=>'lead'], ['meta'=>'x']);
        clearstatcache(true, $path);
        $after = filesize($path);
        $this->assertGreaterThan($before, $after);

        $lines = file($path);
        $tail = trim(end($lines));
        $this->assertStringContainsString("Audit[{$domain}]", $tail);
        $this->assertStringContainsString("#{$entityId} {$action}", $tail);
    }

    public function domainProvider(): array
    {
        return [
            ['client', 10, 'update'],
            ['status', 0, 'create'],
            ['service_type', 3, 'delete'],
            ['template', 7, 'update'],
            ['checklist', 0, 'global_update'],
            ['task_checklist', 99, 'add_item'],
            ['hierarchy', 5, 'update'],
            ['access', 0, 'matrix_update'],
            ['lead_teams', 4, 'add_member'],
        ];
    }
}
