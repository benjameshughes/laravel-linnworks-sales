# Historical Order Import UI - Setup Guide

## Overview

A Livewire-based UI has been created for importing historical orders from Linnworks with real-time progress tracking using Laravel's event broadcasting system.

## Files Created/Modified

### New Files

1. **Events:**
   - `app/Events/ImportStarted.php` - Broadcast when import begins
   - `app/Events/ImportProgressUpdated.php` - Broadcast progress updates
   - `app/Events/ImportCompleted.php` - Broadcast when import finishes

2. **Livewire Component:**
   - `app/Livewire/Settings/ImportProgress.php` - Main import UI component
   - `resources/views/livewire/settings/import-progress.blade.php` - UI view

### Modified Files

1. **Command:** `app/Console/Commands/ImportHistoricalOrders.php`
   - Added event dispatching throughout the import process
   - Broadcasts progress every 25 orders and at each page completion

2. **Routes:** `routes/web.php`
   - Added `settings/import` route

3. **Navigation:** `resources/views/components/settings/layout.blade.php`
   - Added "Import Orders" link to settings navigation

## Broadcasting Setup (Required for Real-Time Updates)

The import progress UI uses Laravel Broadcasting to push real-time updates from the server to the browser. You'll need to set up a broadcasting driver.

### Option 1: Laravel Reverb (Recommended for Development)

Laravel Reverb is a first-party WebSocket server for Laravel:

```bash
# Install Reverb
composer require laravel/reverb

# Install Reverb
php artisan reverb:install

# Update .env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Start Reverb server
php artisan reverb:start
```

### Option 2: Pusher (Recommended for Production)

Pusher is a hosted WebSocket service:

```bash
# Install Pusher PHP SDK
composer require pusher/pusher-php-server

# Update .env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=your-cluster
```

### Frontend Setup

After setting up broadcasting, install Laravel Echo and Pusher JS:

```bash
npm install --save-dev laravel-echo pusher-js
```

Update `resources/js/app.js` to configure Echo:

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    wsHost: import.meta.env.VITE_PUSHER_HOST ?? `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
    wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
    wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

Add to `.env`:

```
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

## Usage

1. Navigate to Settings → Import Orders (`/settings/import`)
2. Configure date range and batch size
3. Click "Start Import"
4. Watch real-time progress updates as orders are imported

## Features

- **Real-time Progress Bar** - Visual progress indicator
- **Live Statistics** - Total orders, processed, imported, errors
- **Page Tracking** - Current page number and estimated total pages
- **Status Messages** - Detailed status updates throughout import
- **Error Handling** - Graceful error display and recovery
- **Completion Summary** - Final import statistics

## Technical Details

### Event Flow

1. User clicks "Start Import" → `ImportProgress::startImport()`
2. Command runs → `ImportHistoricalOrders::handle()`
3. Command dispatches `ImportStarted` event
4. For each page:
   - Process orders
   - Dispatch `ImportProgressUpdated` event
5. Command completes → Dispatch `ImportCompleted` event

### Livewire Listeners

The `ImportProgress` component listens for these events using Livewire's `#[On]` attribute:

- `echo:import-progress,import.started`
- `echo:import-progress,progress.updated`
- `echo:import-progress,import.completed`

### Performance Considerations

- Events are dispatched every 25 orders (configurable)
- Events are dispatched at page completion (every 200 orders by default)
- All events are asynchronous and non-blocking
- UI updates happen in real-time without page refresh

## Troubleshooting

### Real-time Updates Not Working

1. Check broadcasting is configured: `php artisan config:show broadcasting`
2. Verify Reverb/Pusher is running
3. Check browser console for WebSocket connection errors
4. Ensure `BROADCAST_CONNECTION` is set in `.env`

### Import Runs But No UI Updates

1. Verify events are being dispatched (check `storage/logs/laravel.log`)
2. Check that `--dry-run` flag is not set (events don't dispatch in dry-run mode)
3. Ensure Laravel Echo is properly configured in frontend

### Performance Issues

1. Reduce `batch-size` parameter (default 200)
2. Increase event dispatch frequency (currently every 25 orders)
3. Check Linnworks API rate limits (150 requests/minute)

## Alternative: Polling-Based Progress (No Broadcasting Required)

If you don't want to set up broadcasting, you can implement a simpler polling-based solution:

1. Store import progress in cache/database
2. Use Livewire's `wire:poll` to check progress every few seconds
3. Update UI based on cached progress

This approach is simpler but provides less immediate feedback.
