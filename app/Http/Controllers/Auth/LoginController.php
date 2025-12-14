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
   //roles permitidos y sus claves
    private array $roleGroups = [
        'technician' => ['technician'],
        'logistics' => ['logistics'],
        'admin' => ['admin', 'super_admin'],
    ];

    //mostrar seleccion de rol para login
    public function showRoleSelection(): View
    {
        return view('auth.login-selection');
    }

   // mostrar formulario de login segun rol
    public function showRoleLogin(string $role): View
    {   // verificar si la clave de rol es valida
        if (! array_key_exists($role, $this->roleGroups)) {
            abort(404);
        }

        $titles = [
            'technician' => 'Técnico',
            'logistics' => 'Logística',
            'admin' => 'Administrador',
        ];
        // retornar vista de login con datos del rol
        return view('auth.login-role', [
            'roleKey' => $role,
            'title' => $titles[$role] ?? 'Iniciar sesión',
        ]);
    }
// procesar el login
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
        // extraer la clave de rol y eliminarla de las credenciales
        $roleKey = $credentials['role_key'];
        unset($credentials['role_key']);
        // verificar si se ha marcado "recordarme"
        $remember = $request->boolean('remember');
        // intentar autenticar al usuario
        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }
        // regenerar la sesion para evitar fijacion de sesion
        $request->session()->regenerate();
        // obtener el usuario autenticado
        $user = Auth::user();

        // Comprobamos rol permitido
        if (! $user || ! in_array($user->role, $this->roleGroups[$roleKey], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            // lanzar error de validacion
            throw ValidationException::withMessages([
                'email' => 'No tienes permisos para acceder a esta área.',
            ]);
        }

        //  limpiamos cualquier remember anterior si no se marco "recordarme"
        if (! $remember) {
            // limpamos el token de "remember" en BD
            $user->setRememberToken(null);
            $user->save();

            // y borramos la cookie de "remember" del navegador
            Cookie::queue(Cookie::forget(Auth::getRecallerName()));
        }
        // redirigir al usuario a la ruta correspondiente segun rol
        return redirect()->intended($this->redirectPath());
    }

  // determinar la ruta de redireccion segun rol
    protected function redirectPath(): string
    {
        $user = Auth::user();

        if (! $user) {
            return route('login');
        }
        // redirigir segun rol
        return match ($user->role) {
            'technician' => route('technician.stock'),
            'logistics' => route('admin.materials'),
            'admin', 'super_admin' => route('admin.personnel'),
            default => route('login'),
        };
    }
}
