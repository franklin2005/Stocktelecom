<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * Display the registration form.
     */
    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    /**
     * Handle a registration request.
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'technician',
            'tech_code' => $this->generateTechCode(),
        ]);

        StockLocation::create([
            'location_type' => 'user',
            'ref_id' => $user->id,
            'name' => 'Stock de ' . $user->name,
        ]);

        Auth::login($user);

        return redirect()->to(route('technician.stock'));
    }

    /**
     * Generate a unique technician code.
     */
    protected function generateTechCode(): string
    {
        $base = 'TECH-' . Str::upper(Str::random(5));

        while (User::where('tech_code', $base)->exists()) {
            $base = 'TECH-' . Str::upper(Str::random(5));
        }

        return $base;
    }
}
