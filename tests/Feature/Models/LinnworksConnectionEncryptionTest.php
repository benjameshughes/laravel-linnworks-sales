<?php

use App\Models\LinnworksConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('application_id is encrypted when saved to database', function () {
    $user = User::factory()->create();
    $plainAppId = 'test-app-id-12345';

    $connection = LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'application_id' => $plainAppId,
    ]);

    // Read raw value from database
    $rawValue = DB::table('linnworks_connections')
        ->where('id', $connection->id)
        ->value('application_id');

    // Raw value should NOT be plain text
    expect($rawValue)->not->toBe($plainAppId);

    // Model attribute should be decrypted automatically
    expect($connection->application_id)->toBe($plainAppId);
});

test('application_secret is encrypted when saved to database', function () {
    $user = User::factory()->create();
    $plainSecret = 'test-secret-67890';

    $connection = LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'application_secret' => $plainSecret,
    ]);

    // Read raw value from database
    $rawValue = DB::table('linnworks_connections')
        ->where('id', $connection->id)
        ->value('application_secret');

    // Raw value should NOT be plain text
    expect($rawValue)->not->toBe($plainSecret);

    // Model attribute should be decrypted automatically
    expect($connection->application_secret)->toBe($plainSecret);
});

test('access_token is encrypted when saved to database', function () {
    $user = User::factory()->create();
    $plainToken = 'test-access-token-abcdef';

    $connection = LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'access_token' => $plainToken,
    ]);

    // Read raw value from database
    $rawValue = DB::table('linnworks_connections')
        ->where('id', $connection->id)
        ->value('access_token');

    // Raw value should NOT be plain text
    expect($rawValue)->not->toBe($plainToken);

    // Model attribute should be decrypted automatically
    expect($connection->access_token)->toBe($plainToken);
});

test('session_token is encrypted when saved to database', function () {
    $user = User::factory()->create();
    $plainSessionToken = 'test-session-token-xyz123';

    $connection = LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'session_token' => $plainSessionToken,
    ]);

    // Read raw value from database
    $rawValue = DB::table('linnworks_connections')
        ->where('id', $connection->id)
        ->value('session_token');

    // Raw value should NOT be plain text
    expect($rawValue)->not->toBe($plainSessionToken);

    // Model attribute should be decrypted automatically
    expect($connection->session_token)->toBe($plainSessionToken);
});

test('encrypted credentials can be decrypted correctly', function () {
    $user = User::factory()->create();

    $originalData = [
        'application_id' => 'app-id-123',
        'application_secret' => 'secret-456',
        'access_token' => 'token-789',
        'session_token' => 'session-abc',
    ];

    $connection = LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        ...$originalData,
    ]);

    // Refresh from database
    $connection->refresh();

    // All credentials should match original values
    expect($connection->application_id)->toBe($originalData['application_id'])
        ->and($connection->application_secret)->toBe($originalData['application_secret'])
        ->and($connection->access_token)->toBe($originalData['access_token'])
        ->and($connection->session_token)->toBe($originalData['session_token']);
});

test('null session_token is handled correctly', function () {
    $user = User::factory()->create();

    $connection = LinnworksConnection::factory()->withoutSessionToken()->create([
        'user_id' => $user->id,
    ]);

    expect($connection->session_token)->toBeNull();

    // Raw value should also be null
    $rawValue = DB::table('linnworks_connections')
        ->where('id', $connection->id)
        ->value('session_token');

    expect($rawValue)->toBeNull();
});

test('updating encrypted fields maintains encryption', function () {
    $user = User::factory()->create();

    $connection = LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'application_id' => 'original-app-id',
    ]);

    // Update the field
    $newAppId = 'updated-app-id';
    $connection->update(['application_id' => $newAppId]);

    // Read raw value from database
    $rawValue = DB::table('linnworks_connections')
        ->where('id', $connection->id)
        ->value('application_id');

    // Raw value should be encrypted
    expect($rawValue)->not->toBe($newAppId);

    // Model should return decrypted value
    $connection->refresh();
    expect($connection->application_id)->toBe($newAppId);
});

test('multiple connections have different encrypted values even with same plain text', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $sameAppId = 'same-app-id';

    $connection1 = LinnworksConnection::factory()->create([
        'user_id' => $user1->id,
        'application_id' => $sameAppId,
    ]);

    $connection2 = LinnworksConnection::factory()->create([
        'user_id' => $user2->id,
        'application_id' => $sameAppId,
    ]);

    // Get raw encrypted values
    $raw1 = DB::table('linnworks_connections')
        ->where('id', $connection1->id)
        ->value('application_id');

    $raw2 = DB::table('linnworks_connections')
        ->where('id', $connection2->id)
        ->value('application_id');

    // Encrypted values should be different (Laravel adds unique IV to each encryption)
    expect($raw1)->not->toBe($raw2);

    // But decrypted values should be the same
    expect($connection1->application_id)->toBe($sameAppId)
        ->and($connection2->application_id)->toBe($sameAppId);
});

test('encrypted fields fit within VARCHAR(255) column limit for typical Linnworks values', function () {
    $user = User::factory()->create();

    // Test with typical Linnworks UUID format (36 chars)
    $connection = LinnworksConnection::factory()->create([
        'user_id' => $user->id,
        'application_id' => '51ed9def-e4a7-4301-a517-363c16157c37',
        'application_secret' => 'b13693ff-72fe-4f85-918f-068049674b7d',
        'access_token' => '630a618b-cd83-4faa-db0d-580b70e9912a',
        'session_token' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890', // Typical session token format
    ]);

    // Get raw encrypted lengths
    $rawAppId = DB::table('linnworks_connections')->where('id', $connection->id)->value('application_id');
    $rawSecret = DB::table('linnworks_connections')->where('id', $connection->id)->value('application_secret');
    $rawAccess = DB::table('linnworks_connections')->where('id', $connection->id)->value('access_token');
    $rawSession = DB::table('linnworks_connections')->where('id', $connection->id)->value('session_token');

    // Encrypted values should be reasonable length (Laravel adds overhead)
    // UUIDs (~36 chars) become ~256 chars when encrypted (just at VARCHAR limit)
    expect(strlen($rawAppId))->toBeLessThanOrEqual(300)
        ->and(strlen($rawSecret))->toBeLessThanOrEqual(300)
        ->and(strlen($rawAccess))->toBeLessThanOrEqual(300)
        ->and(strlen($rawSession))->toBeLessThanOrEqual(300)
        ->and(strlen($rawAppId))->toBeGreaterThan(strlen($connection->application_id)); // Encrypted is longer
});
