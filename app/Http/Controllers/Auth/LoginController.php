<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Role group mapping.
     */
    private array $roleGroups = [
        'technician' => ['technician'],
        'logistics' => ['logistics'],
        'admin' => ['admin', 'super_admin'],
    ];

    /**
     * Display the role selection screen.
     */
    public function showRoleSelection(): View
    {
        return view('auth.login-selection');
    }

    /**
     * Display the login form for the chosen role.
     */
    public function showRoleLogin(string $role): View
    {
        if (! array_key_exists($role, $this->roleGroups)) {
            abort(404);
        }

        $titles = [
            'technician' => 'Iniciar sesión Técnico',
            'logistics' => 'Iniciar sesión Logística',
            'admin' => 'Iniciar sesión Administrador',
        ];

        return view('auth.login-role', [
            'roleKey' => $role,
            'title' => $titles[$role] ?? 'Iniciar sesión',
        ]);
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'role_key' => ['required', Rule::in(array_keys($this->roleGroups))],
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $roleKey = $credentials['role_key'];
        unset($credentials['role_key']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if (! $user || ! in_array($user->role, $this->roleGroups[$roleKey], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'No tienes permisos para acceder a esta área.',
            ]);
        }

        return redirect()->intended($this->redirectPath());
    }

    /**
     * Get the post-login redirect path.
     */
    protected function redirectPath(): string
    {
        $user = Auth::user();

        if (! $user) {
            return route('login');
        }

        return match ($user->role) {
            'technician' => route('technician.dashboard'),
            'logistics', 'admin', 'super_admin' => route('admin.dashboard'),
            default => route('login'),
        };
    }
}
