<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth
     */
    public function redirect(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        try {
            \Log::info('Google OAuth redirect initiated');
            
            return Socialite::driver('google')
                ->stateless() // Use stateless OAuth for API (no sessions)
                ->scopes(['openid', 'profile', 'email', 'https://www.googleapis.com/auth/calendar'])
                ->redirect();
        } catch (\Exception $e) {
            \Log::error('Google OAuth redirect error: ' . $e->getMessage());
            abort(500, 'Failed to initiate Google login: ' . $e->getMessage());
        }
    }

    /**
     * Handle Google OAuth callback
     */
    public function callback(Request $request): \Illuminate\Http\RedirectResponse|JsonResponse|Response|View
    {
        try {
            \Log::info('Google OAuth callback received');
            
            $googleUser = Socialite::driver('google')->stateless()->user();
            \Log::info('Google user retrieved', ['email' => $googleUser->email]);

            // Check if user exists by email
            $user = User::where('email', $googleUser->email)->first();

            if ($user) {
                // Link Google account to existing user
                $user->google_id = $googleUser->id;
                $user->google_email = $googleUser->email;
                $user->google_access_token = encrypt($googleUser->token);
                $user->google_refresh_token = encrypt($googleUser->refreshToken);
                
                // Update avatar if not set
                if (!$user->avatar && $googleUser->avatar) {
                    $user->avatar = $googleUser->avatar;
                }
                
                $user->save();
            } else {
                // Create new user with Google account
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'password' => bcrypt(uniqid()), // Random password (user won't use it)
                    'avatar' => $googleUser->avatar,
                    'google_id' => $googleUser->id,
                    'google_email' => $googleUser->email,
                    'google_access_token' => encrypt($googleUser->token),
                    'google_refresh_token' => encrypt($googleUser->refreshToken),
                    'role' => 'student',
                    'status' => 'active',
                ]);
            }

            // Update last login
            $user->last_login = now();
            $user->save();

            // Create Sanctum token
            $token = $user->createToken('auth-token')->plainTextToken;
            \Log::info('Token generated', ['token_preview' => substr($token, 0, 20) . '...', 'user_id' => $user->id]);
            
            $tokenModel = $user->tokens()->latest()->first();

            // Track session
            UserSession::create([
                'user_id' => $user->id,
                'token_id' => $tokenModel->id,
                'device_name' => $request->header('User-Agent', 'Unknown'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'last_activity' => now(),
                'is_active' => true,
            ]);

            // Get frontend URL from environment
            $frontendUrl = env('FRONTEND_URL');
            
            // Get redirect path from query parameter or default to root
            $redirectPath = $request->get('redirect_to', '/');
            
            // Direct redirect with token in URL - frontend will handle storing it
            // This avoids showing the intermediate "Logging you in..." page
            $redirectUrl = rtrim($frontendUrl, '/') . $redirectPath . 
                (strpos($redirectPath, '?') !== false ? '&' : '?') . 
                'token=' . urlencode($token) . '&google_login=success&from_callback=1';
            
            \Log::info('Redirecting to frontend (direct)', [
                'url' => $redirectUrl,
                'token_preview' => substr($token, 0, 20) . '...'
            ]);
            
            return redirect($redirectUrl);

        } catch (\Exception $e) {
            \Log::error('Google OAuth callback error: ' . $e->getMessage());
            
            // On error, redirect to frontend root with error
            $frontendUrl = env('FRONTEND_URL');
            $redirectUrl = rtrim($frontendUrl, '/') . '/?error=' . urlencode('Google authentication failed. Please try again.');
            
            return redirect($redirectUrl);
        }
    }

    /**
     * Disconnect Google account
     */
    public function disconnect(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $user->google_id = null;
        $user->google_email = null;
        $user->google_access_token = null;
        $user->google_refresh_token = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Google account disconnected successfully.',
        ]);
    }
}
