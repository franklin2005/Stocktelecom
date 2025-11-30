<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
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
            'technician' => 'Técnico',
            'logistics' => 'Logística',
            'admin' => 'Administrador',
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
        $credentials = $request->validate(
            [
                'role_key' => ['required', Rule::in(array_keys($this->roleGroups))],
                'email' => ['required', 'email'],
                'password' => ['required'],
            ],
            [
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Ingresa un correo electrónico válido.',
                'password.required' => 'La contraseña es obligatoria.',
                'password.confirmed' => 'La confirmación de contraseña no coincide.',
                'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            ]
        );

        $roleKey = $credentials['role_key'];
        unset($credentials['role_key']);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        // Comprobamos rol permitido
        if (! $user || ! in_array($user->role, $this->roleGroups[$roleKey], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'No tienes permisos para acceder a esta área.',
            ]);
        }

        // 👇 IMPORTANTE: si NO ha marcado "recordarme", limpiamos cualquier remember anterior
        if (! $remember) {
            // limpamos el token de "remember" en BD
            $user->setRememberToken(null);
            $user->save();

            // y borramos la cookie de "remember" del navegador
            Cookie::queue(Cookie::forget(Auth::getRecallerName()));
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
            'technician' => route('technician.stock'),
            'logistics' => route('admin.materials'),
            'admin', 'super_admin' => route('admin.personnel'),
            default => route('login'),
        };
    }
}
