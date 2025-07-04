<?php

namespace App\Http\Controllers;

use App\Services\LinnworksOAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class LinnworksCallbackController extends Controller
{
    public function __construct(
        private LinnworksOAuthService $oauthService
    ) {}

    /**
     * Handle Linnworks installation callback (postback)
     */
    public function handleCallback(Request $request): Response
    {
        try {
            // Log everything for debugging
            Log::info('Linnworks installation callback received', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'payload' => $request->all(),
                'raw_content' => $request->getContent(),
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
            ]);

            // Validate required parameters
            $token = $request->input('Token');
            $userId = $request->input('UserId');
            $userEmail = $request->input('UserEmail');
            $applicationId = $request->input('ApplicationId');
            $tracking = $request->input('Tracking'); // Our user ID for tracking

            if (!$token || !$applicationId) {
                Log::error('Missing required parameters in Linnworks callback', [
                    'token' => $token ? 'present' : 'missing',
                    'applicationId' => $applicationId ? 'present' : 'missing',
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required parameters'
                ], 400);
            }

            // Extract our user ID from tracking parameter
            $ourUserId = null;
            if ($tracking && str_starts_with($tracking, 'user_')) {
                $ourUserId = (int) str_replace('user_', '', $tracking);
            }

            if (!$ourUserId) {
                Log::error('Invalid tracking parameter in Linnworks callback', [
                    'tracking' => $tracking,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid tracking parameter'
                ], 400);
            }

            // For most apps, we don't need the secret for the callback
            // The secret is only needed for API calls, not for receiving the token
            $applicationSecret = config('linnworks.application_secret', 'not_required_for_callback');

            // Create the connection automatically
            $connection = $this->oauthService->createConnection(
                $ourUserId,
                $applicationId,
                $applicationSecret,
                $token
            );

            // Test the connection immediately
            $testResult = $this->oauthService->testConnection($connection);

            Log::info('Linnworks connection created via callback', [
                'user_id' => $ourUserId,
                'linnworks_user_id' => $userId,
                'linnworks_email' => $userEmail,
                'connection_test' => $testResult ? 'success' : 'failed',
            ]);

            // Return simple 200 OK response that Linnworks expects
            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Error processing Linnworks callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate installation URL with automatic callback
     */
    public function getInstallationUrl(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'User not authenticated');
        }

        $applicationId = config('linnworks.application_id');
        if (!$applicationId) {
            abort(500, 'Linnworks application not configured');
        }

        // Generate tracking parameter with user ID
        $tracking = 'user_' . $user->id;

        // Generate installation URL with tracking
        $installUrl = "https://apps.linnworks.net/Authorization/Authorize/{$applicationId}?Tracking={$tracking}";

        return response()->json([
            'install_url' => $installUrl,
            'tracking' => $tracking,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test callback endpoint accessibility
     */
    public function testCallback(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'OK',
            'message' => 'Callback endpoint is accessible',
            'timestamp' => now()->toISOString(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ]);
    }
}