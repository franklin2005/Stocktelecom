<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    // Mostrar el formulario para solicitar el enlace de restablecimiento de contraseña.
    public function showLinkRequestForm(): View
    {
        return view('auth.password-email');
    }

    // Procesar la solicitud de envío del enlace de restablecimiento de contraseña.
    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $credentials = $request->validate(
            [
                'email' => ['required', 'email', 'exists:users,email'],
            ],
            [
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Ingresa un correo electrónico válido.',
                'email.exists' => 'No encontramos ningún usuario con ese correo.',
            ]
        );
        // intentar enviar el enlace de restablecimiento
        $status = Password::sendResetLink(
            ['email' => $credentials['email']]
        );
        // verificar el resultado del intento
        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Te hemos enviado un enlace para restablecer tu contraseña, revisa tu correo.');
        }
        // si fallo, retornar con error
        return back()->withErrors([
            'email' => 'No hemos podido enviar el enlace de recuperación. Inténtalo nuevamente en unos minutos.',
        ]);
    }
}
