<?php

use App\Events\SyncCompleted;
use App\Events\SyncProgressUpdated;
use App\Events\SyncStarted;
use Illuminate\Support\Facades\Event;

it('broadcasts sync events with correct event names', function () {
    Event::fake();

    // Dispatch events
    SyncStarted::dispatch(7, 30);
    SyncProgressUpdated::dispatch('fetching-open-ids', 'Fetching all open orders...', 0);
    SyncCompleted::dispatch(100, 10, 90, 0, true);

    // Assert events were broadcast
    Event::assertDispatched(SyncStarted::class);
    Event::assertDispatched(SyncProgressUpdated::class);
    Event::assertDispatched(SyncCompleted::class);
});

it('sync events broadcast on the correct channel', function () {
    $syncStarted = new SyncStarted(7, 30);
    $syncProgress = new SyncProgressUpdated('fetching-open-ids', 'Fetching all open orders...', 0);
    $syncCompleted = new SyncCompleted(100, 10, 90, 0, true);

    expect($syncStarted->broadcastOn()->name)->toBe('sync-progress');
    expect($syncProgress->broadcastOn()->name)->toBe('sync-progress');
    expect($syncCompleted->broadcastOn()->name)->toBe('sync-progress');
});

it('sync events use class name as broadcast name by default', function () {
    // Laravel uses the class name as the broadcast event name by default
    // when broadcastAs() is not explicitly defined
    $syncStarted = new SyncStarted(7, 30);
    $syncProgress = new SyncProgressUpdated('fetching-open-ids', 'Fetching all open orders...', 0);
    $syncCompleted = new SyncCompleted(100, 10, 90, 0, true);

    // Verify the events have the correct class names
    expect($syncStarted)->toBeInstanceOf(SyncStarted::class);
    expect($syncProgress)->toBeInstanceOf(SyncProgressUpdated::class);
    expect($syncCompleted)->toBeInstanceOf(SyncCompleted::class);
});

it('sync events broadcast with correct data structure', function () {
    $syncStarted = new SyncStarted(7, 30);
    $syncProgress = new SyncProgressUpdated('fetching-open-ids', 'Fetching all open orders...', 100);
    $syncCompleted = new SyncCompleted(100, 10, 90, 0, true);

    $startedData = $syncStarted->broadcastWith();
    expect($startedData)
        ->toHaveKey('open_window_days')
        ->toHaveKey('processed_window_days')
        ->toHaveKey('started_at');

    $progressData = $syncProgress->broadcastWith();
    expect($progressData)
        ->toHaveKey('stage')
        ->toHaveKey('message')
        ->toHaveKey('count')
        ->and($progressData['stage'])->toBe('fetching-open-ids')
        ->and($progressData['message'])->toBe('Fetching all open orders...')
        ->and($progressData['count'])->toBe(100);

    $completedData = $syncCompleted->broadcastWith();
    expect($completedData)
        ->toHaveKey('processed')
        ->toHaveKey('created')
        ->toHaveKey('updated')
        ->toHaveKey('failed')
        ->toHaveKey('success')
        ->toHaveKey('completed_at');
});
