<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Public channel for import progress updates
// No authorization needed - this is a public channel
Broadcast::channel('import-progress', function () {
    return true;
});

// Public channel for sync progress updates
// No authorization needed - this is a public channel
Broadcast::channel('sync-progress', function () {
    return true;
});
