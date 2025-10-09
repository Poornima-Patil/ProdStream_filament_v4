<?php

namespace App\Filament\Admin\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title(__('filament-panels::pages/auth/login.notifications.throttled.title', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]))
                ->body(array_key_exists('body', __('filament-panels::pages/auth/login.notifications.throttled') ?: []) ? __('filament-panels::pages/auth/login.notifications.throttled.body', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]) : null)
                ->danger()
                ->send();

            return null;
        }

        $data = $this->form->getState();

        if (! Auth::guard()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Auth::guard()->user();

        // Check if user has any tenants (factories)
        if ($user && method_exists($user, 'getTenants')) {
            $panel = filament()->getCurrentPanel();
            $tenants = $user->getTenants($panel);

            if ($tenants->isEmpty()) {
                // User has no factories assigned - log them out completely
                Auth::guard()->logout();

                // Invalidate the session
                request()->session()->invalidate();
                request()->session()->regenerateToken();

                Notification::make()
                    ->title('Access Denied')
                    ->body('Your account is not assigned to any factory. Please contact your administrator to resolve this issue.')
                    ->danger()
                    ->persistent()
                    ->send();

                $this->throwFailureValidationException();
            }
        }

        return app(LoginResponse::class);
    }
}
