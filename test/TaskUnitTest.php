<?php
declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) { require __DIR__ . '/../vendor/autoload.php'; }

use PHPUnit\Framework\TestCase;

final class TaskUtilsTest extends TestCase
{
    public function testTaskDaysRemainingTodayIsZero(): void
    {
        $task = ['due_date' => date('Y-m-d')];
        $this->assertSame(0, task_days_remaining($task));
    }

    public function testTaskDaysRemainingTomorrowIsPositive(): void
    {
        $tomorrow = (new DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');
        $task = ['due_date' => $tomorrow];
        $this->assertSame(1, task_days_remaining($task));
    }

    public function testTaskDaysRemainingYesterdayIsNegative(): void
    {
        $yesterday = (new DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d');
        $task = ['due_date' => $yesterday];
        $this->assertSame(-1, task_days_remaining($task));
    }
}