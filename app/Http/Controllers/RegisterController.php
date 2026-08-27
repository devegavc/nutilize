<?php

namespace App\Http\Controllers;

use App\Models\AcademicProgram;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        $programs = AcademicProgram::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['program_id', 'name', 'school_name']);

        return view('register', compact('programs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'size:1', 'alpha'],
            'last_name' => ['required', 'string', 'max:100'],
            'program_id' => ['required', 'integer', 'exists:pgsql.academic_programs,program_id'],
            'username' => ['required', 'string', 'max:50', 'unique:pgsql.users,username'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:pgsql.users,email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
                'confirmed',
            ],
        ]);

        $middleInitial = isset($validated['middle_initial']) && $validated['middle_initial'] !== ''
            ? strtoupper($validated['middle_initial']).'.'
            : null;

        $fullName = trim(implode(' ', array_filter([
            $validated['first_name'],
            $middleInitial,
            $validated['last_name'],
        ])));

        $user = new User();
        $user->setConnection('pgsql');
        $user->first_name = $validated['first_name'];
        $user->middle_initial = isset($validated['middle_initial']) && $validated['middle_initial'] !== ''
            ? strtoupper($validated['middle_initial'])
            : null;
        $user->last_name = $validated['last_name'];
        $user->full_name = $fullName;
        $user->username = $validated['username'];
        $user->email = $validated['email'];
        $user->password = Hash::make($validated['password']);
        $user->role = 'user';
        $user->program_id = (int) $validated['program_id'];
        $user->is_active = true;
        $user->status_changed_at = now();
        $user->save();

        return redirect()->route('login')->with('status', 'Registration successful. Please log in.');
    }
}