<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Linnworks\AuditTrail\AuditTrailService;
use Illuminate\Console\Command;

class TestAuditTrail extends Command
{
    protected $signature = 'test:audit-trail
                            {--stock-item-id= : Test inventory audit trail for this stock item ID}
                            {--order-id= : Test order audit trail for this order ID}
                            {--critical : Show only critical events}
                            {--hours=24 : Number of hours to look back}';

    protected $description = 'Test audit trail functionality with value objects';

    public function handle(AuditTrailService $auditService): int
    {
        $this->info('🔍 Testing Audit Trail Service');
        $this->newLine();

        $stockItemId = $this->option('stock-item-id');
        $orderId = $this->option('order-id');
        $criticalOnly = $this->option('critical');
        $hours = (int) $this->option('hours');

        try {
            // Test 1: Recent critical events
            if ($criticalOnly) {
                $this->testRecentCriticalEvents($auditService, $hours);
                return self::SUCCESS;
            }

            // Test 2: Inventory audit trail
            if ($stockItemId) {
                $this->testInventoryAuditTrail($auditService, $stockItemId);
                return self::SUCCESS;
            }

            // Test 3: Order audit trail
            if ($orderId) {
                $this->testOrderAuditTrail($auditService, $orderId);
                return self::SUCCESS;
            }

            // Test 4: System statistics (default)
            $this->testSystemStatistics($auditService);

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function testRecentCriticalEvents(AuditTrailService $auditService, int $hours): void
    {
        $this->info("🚨 Fetching critical events from last {$hours} hours...");
        $this->newLine();

        $events = $auditService->getRecentCriticalEvents(
            userId: 1,
            hours: $hours,
            maxEvents: 50,
        );

        if ($events->isEmpty()) {
            $this->warn('No critical events found');
            return;
        }

        $this->info("Found {$events->count()} critical events:");
        $this->newLine();

        $this->table(
            ['Time', 'Type', 'Severity', 'Description', 'User'],
            $events->map(fn ($event) => [
                $event->relativeTime(),
                $event->type->value,
                $event->severity(),
                $this->truncate($event->description, 50),
                $event->userName,
            ])->toArray()
        );

        $this->newLine();
        $this->displaySummary($events->summary());
    }

    private function testInventoryAuditTrail(AuditTrailService $auditService, string $stockItemId): void
    {
        $this->info("📦 Fetching inventory audit trail for: {$stockItemId}");
        $this->newLine();

        $events = $auditService->getInventoryAuditTrail(
            userId: 1,
            stockItemId: $stockItemId,
            dateFrom: now()->subDays(30),
            pageSize: 50,
        );

        if ($events->isEmpty()) {
            $this->warn('No events found for this stock item');
            return;
        }

        $this->info("Found {$events->count()} events:");
        $this->newLine();

        // Show recent events
        $this->table(
            ['Time', 'Type', 'Severity', 'Description', 'User'],
            $events->take(20)->map(fn ($event) => [
                $event->relativeTime(),
                $event->type->value,
                $event->severity(),
                $this->truncate($event->description, 50),
                $event->userName,
            ])->toArray()
        );

        $this->newLine();
        $this->displaySummary($events->summary());

        // Type statistics
        $this->newLine();
        $this->info('📊 Event Type Statistics:');
        $this->table(
            ['Type', 'Count', 'Percentage'],
            collect($events->typeStatistics())->map(fn ($stat) => [
                $stat['type'],
                $stat['count'],
                $stat['percentage'] . '%',
            ])->toArray()
        );
    }

    private function testOrderAuditTrail(AuditTrailService $auditService, string $orderId): void
    {
        $this->info("📋 Fetching order audit trail for: {$orderId}");
        $this->newLine();

        $events = $auditService->getOrderAuditTrail(
            userId: 1,
            orderId: $orderId,
        );

        if ($events->isEmpty()) {
            $this->warn('No events found for this order');
            return;
        }

        $this->info("Found {$events->count()} events:");
        $this->newLine();

        $this->table(
            ['Time', 'Type', 'Severity', 'Description', 'User'],
            $events->map(fn ($event) => [
                $event->relativeTime(),
                $event->type->value,
                $event->severity(),
                $this->truncate($event->description, 50),
                $event->userName,
            ])->toArray()
        );

        $this->newLine();
        $this->displaySummary($events->summary());
    }

    private function testSystemStatistics(AuditTrailService $auditService): void
    {
        $this->info('📊 Fetching system-wide audit statistics...');
        $this->newLine();

        $stats = $auditService->getSystemStatistics(
            userId: 1,
            dateFrom: now()->subDays(7),
        );

        $summary = $stats['summary'];

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Events', $summary['total_events']],
                ['Critical Events', $summary['critical_events']],
                ['Informational Events', $summary['informational_events']],
                ['Unique Users', $summary['unique_users']],
                ['Order Events', $summary['order_events']],
                ['Inventory Events', $summary['inventory_events']],
            ]
        );

        // Type statistics
        $this->newLine();
        $this->info('📈 Event Type Distribution:');
        $this->table(
            ['Type', 'Count', 'Percentage'],
            collect($stats['type_statistics'])->take(10)->map(fn ($stat) => [
                $stat['type'],
                $stat['count'],
                $stat['percentage'] . '%',
            ])->toArray()
        );

        // User statistics
        $this->newLine();
        $this->info('👥 Most Active Users:');
        $this->table(
            ['User', 'Events', 'Critical', 'Last Activity'],
            collect($stats['user_statistics'])->take(10)->map(fn ($stat) => [
                $stat['user'],
                $stat['event_count'],
                $stat['critical_count'],
                $stat['last_activity'] ? \Carbon\Carbon::parse($stat['last_activity'])->diffForHumans() : 'N/A',
            ])->toArray()
        );
    }

    private function displaySummary(array $summary): void
    {
        $this->info('📋 Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Events', $summary['total_events']],
                ['Critical', $summary['critical_events']],
                ['Informational', $summary['informational_events']],
                ['Unique Users', $summary['unique_users']],
                ['Order Events', $summary['order_events']],
                ['Inventory Events', $summary['inventory_events']],
            ]
        );
    }

    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
}
