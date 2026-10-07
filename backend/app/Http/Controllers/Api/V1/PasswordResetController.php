<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Messaging\MessagingSettings;
use App\Services\Messaging\Messenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * "Forgot password" by SMS code (Sprint 22 — decision D5: OTP once the SMS gateway exists). A 6-digit code goes to
 * the account's own mobile number, lives 10 minutes and allows 5 tries. The answer never says whether a number is
 * registered. Resetting signs the account out everywhere else.
 */
class PasswordResetController extends Controller
{
    private const TTL_MINUTES = 10;

    private const MAX_TRIES = 5;

    public function __construct(private MessagingSettings $settings) {}

    /** POST /auth/forgot — {phone} */
    public function request(Request $request, Messenger $messenger): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        abort_unless($this->smsReady(), 422, 'Password reset by SMS is not available yet — please ask the center to reset your password.');

        $mobile = Messenger::mobile($data['phone']);
        $user = $mobile ? $this->findUser($mobile) : null;
        if ($user) {
            $code = (string) random_int(100000, 999999);
            Cache::put($this->key($user), ['hash' => Hash::make($code), 'tries' => 0], now()->addMinutes(self::TTL_MINUTES));
            $messenger->send('sms', $mobile, $messenger->compose('পাসওয়ার্ড', "আপনার কোড {$code}। ".self::TTL_MINUTES.' মিনিটের মধ্যে ব্যবহার করুন। কাউকে বলবেন না।'), 'auth.reset_code', $user);
            AuditLogger::log('password_reset_code', $user, userId: $user->id);
        }

        return response()->json(['message' => 'If this number belongs to an account, a code has been sent by SMS.', 'minutes' => self::TTL_MINUTES]);
    }

    /** POST /auth/reset — {phone, code, password, password_confirmation} */
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $mobile = Messenger::mobile($data['phone']);
        $user = $mobile ? $this->findUser($mobile) : null;
        $entry = $user ? Cache::get($this->key($user)) : null;
        $wrong = fn () => throw ValidationException::withMessages(['code' => 'The code is wrong or has expired. কোডটি ভুল বা মেয়াদ শেষ।']);
        if (! $entry) {
            $wrong();
        }
        if (! Hash::check($data['code'], $entry['hash'])) {
            $entry['tries']++;
            $entry['tries'] >= self::MAX_TRIES ? Cache::forget($this->key($user)) : Cache::put($this->key($user), $entry, now()->addMinutes(self::TTL_MINUTES));
            $wrong();
        }

        Cache::forget($this->key($user));
        $user->update(['password' => $data['password'], 'must_change_password' => false]);
        // Sign out every other device that was using the old password.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
        AuditLogger::log('password_reset', $user, userId: $user->id);

        return response()->json(['message' => 'Password changed. You can sign in now.']);
    }

    private function findUser(string $mobile): ?User
    {
        $user = User::where('phone', $mobile)->orWhere('phone', '+88'.$mobile)->orWhere('phone', '88'.$mobile)->first();

        return $user?->isActive() ? $user : null;
    }

    private function key(User $user): string
    {
        return "password-reset:{$user->id}";
    }

    /** Real SMS on the live server; the test (log) gateway is accepted on local and training copies. */
    private function smsReady(): bool
    {
        return app()->isProduction()
            ? $this->settings->flag('sms_enabled') && $this->settings->get('sms_driver') === 'greenweb'
            : true;
    }
}
