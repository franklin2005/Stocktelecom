<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    //formulario de restablecimiento de contraseña
    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.password-reset', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    //procesar el restablecimiento de contraseña
    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(
            [
                'token' => ['required'],
                'email' => ['required', 'email'],
                'password' => ['required', 'confirmed', 'min:8'],
                'password_confirmation' => ['required'],
            ],
            [
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Ingresa un correo electrónico válido.',
                'password.required' => 'La contraseña es obligatoria.',
                'password.confirmed' => 'La confirmación de contraseña no coincide.',
                'password.min' => 'La contraseña debe tener al menos :min caracteres.',
                'password_confirmation.required' => 'Debes confirmar la contraseña.',
            ]
        );
        // intentar restablecer la contraseña
        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],// callback para actualizar la contraseña
            function ($user) use ($data) {
                $user->forceFill([
                    'password' => Hash::make($data['password']),
                    'remember_token' => Str::random(60),
                ])->save();
                    // disparar el evento de restablecimiento de contraseña
                event(new PasswordReset($user));
            }
        );
        // verificar el resultado del intento
        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Tu contraseña ha sido restablecida correctamente. Ya puedes iniciar sesión.');
        }
        // si fallo, retornar con error
        return back()->withErrors([
            'email' => 'No hemos podido restablecer la contraseña con los datos proporcionados.',
        ]);
    }
}
