# Deployment Guide: Encrypted Credentials Update

## Overview

This update implements **database-only encrypted credential storage** for Linnworks connections. All sensitive credentials (application_id, application_secret, access_token, session_token) are now automatically encrypted using Laravel's `APP_KEY`.

## Breaking Changes

⚠️ **IMPORTANT**: Existing plain-text credentials in the database need to be encrypted before the app will work.

## Deployment Steps

### 1. Pull Latest Code

```bash
git checkout feature/linnworks-oauth
git pull origin feature/linnworks-oauth
```

### 2. Run Composer Install

```bash
composer install
```

### 3. Encrypt Existing Credentials ⚠️ **REQUIRED**

```bash
php artisan linnworks:encrypt-credentials
```

**Expected Output:**
```
Encrypting Linnworks credentials...
Found 1 connection(s) to process.
Processing connection ID 2...
  - application_id: Plain text detected, will encrypt
  - application_secret: Plain text detected, will encrypt
  - access_token: Plain text detected, will encrypt
  - session_token: Plain text detected, will encrypt
  ✓ Connection 2 encrypted successfully

Encryption complete!
+-----------------------------+-------+
| Status                      | Count |
+-----------------------------+-------+
| Encrypted                   | 1     |
| Skipped (already encrypted) | 0     |
| Failed                      | 0     |
+-----------------------------+-------+
```

### 4. Test the Application

Visit `/settings/linnworks` and verify:
- ✅ No "DecryptException" errors
- ✅ Connection status displays correctly
- ✅ Can connect/disconnect without errors

### 5. Run Tests (Optional)

```bash
vendor/bin/pest
```

**Expected:** 26 passing tests, 1 skipped

## What Changed

### Encryption Implementation

**Before:**
- Credentials stored as plain text in database
- Mixed usage of config vs database credentials
- No encryption at rest

**After:**
- All sensitive fields automatically encrypted using `encrypted` cast
- Database is single source of truth (no config fallbacks)
- Credentials encrypted/decrypted transparently by Laravel

### Files Modified

1. **app/Models/LinnworksConnection.php**
   - Added `encrypted` cast for sensitive fields

2. **app/Services/Linnworks/Auth/AuthenticationService.php**
   - Removed `ApiCredentials` dependency
   - Methods now accept credentials as parameters

3. **app/Services/Linnworks/Auth/SessionManager.php**
   - Uses database credentials (automatically decrypted)

4. **app/Providers/LinnworksServiceProvider.php**
   - Removed `ApiCredentials` singleton binding

5. **config/linnworks.php**
   - Removed sensitive credential keys
   - Kept only non-sensitive settings

### New Command

**`php artisan linnworks:encrypt-credentials`**
- Automatically detects plain-text vs encrypted credentials
- Encrypts only plain-text values
- Idempotent (safe to run multiple times)
- Supports `--force` flag for re-encryption

## Environment Variables

### Can Be Removed (Optional)

These are no longer used by the application:

```env
LINNWORKS_APPLICATION_SECRET=...
LINNWORKS_TOKEN=...
```

### Should Be Kept

Keep these for the installation URL functionality:

```env
LINNWORKS_APPLICATION_ID=51ed9def-e4a7-4301-a517-363c16157c37
```

## Troubleshooting

### Error: "DecryptException: The payload is invalid"

**Cause:** Existing plain-text credentials haven't been encrypted.

**Solution:**
```bash
php artisan linnworks:encrypt-credentials
```

### Error: "No active Linnworks connection found"

**Cause:** Connection was set to inactive.

**Solution:**
1. Go to `/settings/linnworks`
2. Click "Connect to Linnworks"
3. Enter your credentials again

### Need to Re-encrypt After APP_KEY Change?

If you rotate your `APP_KEY`, existing encrypted credentials will be invalid. Use the `--force` flag:

```bash
# 1. Export plain-text credentials BEFORE changing APP_KEY
php artisan tinker
>>> $conn = App\Models\LinnworksConnection::first();
>>> echo "App ID: " . $conn->application_id;  // Save these!
>>> echo "Secret: " . $conn->application_secret;
>>> echo "Token: " . $conn->access_token;

# 2. Change APP_KEY
php artisan key:generate

# 3. Manually update with plain text, then encrypt
>>> DB::table('linnworks_connections')->where('id', $conn->id)->update([
      'application_id' => 'plain-text-app-id',
      'application_secret' => 'plain-text-secret',
      'access_token' => 'plain-text-token',
    ]);
>>> exit

# 4. Re-encrypt with new key
php artisan linnworks:encrypt-credentials
```

## Security Notes

- ✅ Credentials encrypted at rest using AES-256-CBC
- ✅ Unique encryption for each value (includes random IV)
- ✅ No credentials in config files or .env (except public app ID)
- ✅ Automatic encryption/decryption by Laravel
- ✅ VARCHAR(255) columns sufficient for encrypted UUIDs

## Rollback Plan

If you need to rollback:

```bash
# 1. Checkout previous commit
git checkout [previous-commit-hash]

# 2. Your encrypted credentials will fail to read
#    You'll need to re-enter credentials in the UI

# 3. Or manually decrypt and update
php artisan tinker
>>> use Illuminate\Support\Facades\Crypt;
>>> $conn = DB::table('linnworks_connections')->first();
>>> $plainAppId = Crypt::decryptString($conn->application_id);
>>> // Save these values, then update table with plain text
```

## Testing

Run the test suite to verify encryption:

```bash
vendor/bin/pest
```

**Test Coverage:**
- ✅ 9 encryption/decryption tests
- ✅ 7 authentication service tests
- ✅ 11 session manager tests
- ✅ 26 passing tests total

## Support

If you encounter issues:

1. Check logs: `tail -f storage/logs/laravel.log`
2. Verify credentials are encrypted:
   ```bash
   php artisan tinker
   >>> DB::table('linnworks_connections')->first();
   // Should see encrypted gibberish
   ```
3. Verify model decryption works:
   ```bash
   php artisan tinker
   >>> App\Models\LinnworksConnection::first()->application_id;
   // Should see plain text UUID
   ```

---

**Deployed on:** [Date]
**Branch:** feature/linnworks-oauth
**Commits:** 836386c, 0708c1b, c882570, f95ef46, 8c7bcef
