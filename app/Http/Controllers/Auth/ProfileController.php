<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{   // mostrar perfil del usuario
    public function show(): View
    {
        $user = auth()->user();
        return view('profile.show', [
            'user' => $user,
        ]);
    }
    // mostrar formulario de edicion de perfil
    public function passwordEdit(): View
    {
        return view('profile.password');
    }
    // actualizar la contrasena del usuario
    public function passwordUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.required' => 'La contraseña actual es obligatoria.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.min.string' => 'La contraseña debe tener al menos :min caracteres.',
        ]);
        // actualizar la contrasena
        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'Contrasena actualizada correctamente.');
    }
}
