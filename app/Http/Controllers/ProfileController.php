<?php

namespace App\Http\Controllers;

use App\Services\ProgramChairOfficeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show()
    {
        $authUser = auth()->user();
        $programs = $authUser && $authUser->shouldSelectProgram()
            ? \App\Models\AcademicProgram::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['program_id', 'name', 'school_name'])
            : collect();

        return view('dashboard-profile', compact('programs'));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'size:1', 'alpha'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                Rule::unique('pgsql.users', 'email')->ignore($user->user_id, 'user_id'),
            ],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:50'],
        ];

        if ($user->shouldSelectProgram()) {
            $rules['program_id'] = ['required', 'integer', 'exists:pgsql.academic_programs,program_id'];
        }

        $validated = $request->validate($rules);

        $middleInitial = isset($validated['middle_initial']) && $validated['middle_initial'] !== ''
            ? strtoupper($validated['middle_initial']).'.'
            : null;

        $fullName = trim(implode(' ', array_filter([
            $validated['first_name'] ?? null,
            $middleInitial,
            $validated['last_name'] ?? null,
        ])));

        $user->first_name = $validated['first_name'];
        $user->middle_initial = isset($validated['middle_initial']) && $validated['middle_initial'] !== ''
            ? strtoupper($validated['middle_initial'])
            : null;
        $user->last_name = $validated['last_name'];
        $user->full_name = $fullName;
        $user->suffix = $validated['suffix'] ?? null;
        $user->email = $validated['email'];
        $user->contact_number = $validated['contact_number'] ?? null;
        $user->phone_number = $validated['phone_number'] ?? null;

        if ($user->shouldSelectProgram()) {
            $user->program_id = (int) $validated['program_id'];
        }

        $user->save();

        if ($user->shouldSelectProgram()) {
            ProgramChairOfficeResolver::reconcilePendingLegacyPcApprovalsForUser((int) $user->user_id);
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'middle_initial' => $user->middle_initial,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'suffix' => $user->suffix,
                'contact_number' => $user->contact_number,
                'phone_number' => $user->phone_number,
                'program_id' => $user->program_id,
                'role' => $user->role,
            ],
        ]);
    }
}
