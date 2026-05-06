<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email-i eshte i detyrueshem.',
            'email.email' => 'Ju lutem vendosni nje email te vlefshem.',
            'password.required' => 'Fjalekalimi eshte i detyrueshem.',
        ];
    }

    /**
     * Authenticate the user against the shared DIS users table.
     *
     * Pranon email parësor OSE work_email — i njëjti njeri shpesh ka adresën
     * personale gmail si login dhe @zeroabsolute.com si email pune. Mirror i
     * sjelljes së DIS / HRMS LoginRequest.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $entered = $this->input('email');
        $password = $this->input('password');
        $remember = $this->boolean('remember');

        if (Auth::attempt(['email' => $entered, 'password' => $password], $remember)) {
            RateLimiter::clear($this->throttleKey());
            return;
        }

        $user = User::where('work_email', $entered)->first();
        if ($user && Auth::attempt(['email' => $user->email, 'password' => $password], $remember)) {
            RateLimiter::clear($this->throttleKey());
            return;
        }

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'credentials' => 'Email-i ose fjalekalimi nuk eshte i sakte.',
        ]);
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'credentials' => "Shume perpjekje. Provoni perseri pas {$seconds} sekondave.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')) . '|marketing|' . $this->ip());
    }
}
