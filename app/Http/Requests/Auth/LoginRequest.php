<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const ATTEMPT_DECAY_SECONDS = 900;

    private const LOCK_LEVEL_TTL_SECONDS = 86400;

    /**
     * Progressive lockout schedule in seconds.
     *
     * @var list<int>
     */
    private const LOCKOUT_SCHEDULE = [60, 120, 300, 900, 1800, 3600];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $attempts = RateLimiter::hit($this->throttleKey(), self::ATTEMPT_DECAY_SECONDS);
            $remainingAttempts = max(0, self::MAX_ATTEMPTS - $attempts);

            if ($attempts >= self::MAX_ATTEMPTS) {
                $seconds = $this->applyProgressiveLockout();

                event(new Lockout($this));

                throw ValidationException::withMessages([
                    'email' => trans('auth.throttle', [
                        'seconds' => $seconds,
                        'minutes' => ceil($seconds / 60),
                    ]),
                    'lock_seconds' => [(string) $seconds],
                ]);
            }

            $message = trans('auth.failed');
            if ($remainingAttempts > 0) {
                $message .= ' '.trans('auth.remaining', ['attempts' => $remainingAttempts]);
            }

            throw ValidationException::withMessages([
                'email' => $message,
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        $this->clearProgressiveLockout();
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $remainingSeconds = $this->remainingLockSeconds();
        if ($remainingSeconds > 0) {
            event(new Lockout($this));

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $remainingSeconds,
                    'minutes' => ceil($remainingSeconds / 60),
                ]),
                'lock_seconds' => [(string) $remainingSeconds],
            ]);
        }

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = $this->applyProgressiveLockout();

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
            'lock_seconds' => [(string) $seconds],
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $email = trim((string) $this->string('email'));

        return Str::transliterate(Str::lower($email));
    }

    private function applyProgressiveLockout(): int
    {
        $levelKey = $this->lockLevelKey();
        $lockUntilKey = $this->lockUntilKey();

        $currentLevel = (int) Cache::get($levelKey, 0);
        $nextLevel = $currentLevel + 1;
        $seconds = $this->lockoutSecondsForLevel($nextLevel);

        Cache::put($levelKey, $nextLevel, self::LOCK_LEVEL_TTL_SECONDS);
        Cache::put($lockUntilKey, now()->addSeconds($seconds)->timestamp, $seconds);

        RateLimiter::clear($this->throttleKey());

        return $seconds;
    }

    private function remainingLockSeconds(): int
    {
        $lockUntil = (int) Cache::get($this->lockUntilKey(), 0);
        if ($lockUntil <= 0) {
            return 0;
        }

        $remaining = $lockUntil - now()->timestamp;
        if ($remaining > 0) {
            return $remaining;
        }

        Cache::forget($this->lockUntilKey());

        return 0;
    }

    private function clearProgressiveLockout(): void
    {
        Cache::forget($this->lockUntilKey());
        Cache::forget($this->lockLevelKey());
    }

    private function lockoutSecondsForLevel(int $level): int
    {
        $index = max(0, $level - 1);

        if (array_key_exists($index, self::LOCKOUT_SCHEDULE)) {
            return self::LOCKOUT_SCHEDULE[$index];
        }

        return self::LOCKOUT_SCHEDULE[array_key_last(self::LOCKOUT_SCHEDULE)];
    }

    private function lockUntilKey(): string
    {
        return 'auth:login:lock:until:'.sha1($this->throttleKey());
    }

    private function lockLevelKey(): string
    {
        return 'auth:login:lock:level:'.sha1($this->throttleKey());
    }
}
