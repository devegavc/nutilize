<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountSetupDeliveryException;
use App\Services\AdminActivityService;
use App\Services\EmailChangeOtpService;
use App\Services\ProgramChairOfficeResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        $activityLogs = AdminActivityService::recentForUser((int) ($authUser->user_id ?? 0));

        return view('dashboard-profile', compact('programs', 'activityLogs'));
    }

    public function update(Request $request, EmailChangeOtpService $emailChange): JsonResponse
    {
        $user = $request->user();

        $request->merge([
            'email' => trim((string) $request->input('email', '')),
        ]);

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

        $originalEmail = (string) $user->email;
        $emailChanged = strcasecmp($originalEmail, (string) $validated['email']) !== 0;

        $user->first_name = $validated['first_name'];
        $user->middle_initial = isset($validated['middle_initial']) && $validated['middle_initial'] !== ''
            ? strtoupper($validated['middle_initial'])
            : null;
        $user->last_name = $validated['last_name'];
        $user->full_name = $fullName;
        $user->suffix = $validated['suffix'] ?? null;
        $user->email = $emailChanged ? $originalEmail : $validated['email'];
        $user->contact_number = $validated['contact_number'] ?? null;
        $user->phone_number = $validated['phone_number'] ?? null;

        if ($user->shouldSelectProgram()) {
            $user->program_id = (int) $validated['program_id'];
        }

        try {
            $user->save();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                throw ValidationException::withMessages([
                    'email' => 'This email address is already in use.',
                ]);
            }

            throw $exception;
        }

        AdminActivityService::log((int) $user->user_id, 'Updated profile', 'Account');

        if ($user->shouldSelectProgram()) {
            ProgramChairOfficeResolver::reconcilePendingLegacyPcApprovalsForUser((int) $user->user_id);
        }

        if ($emailChanged) {
            try {
                $emailChange->issue($user, (string) $validated['email']);
            } catch (AccountSetupDeliveryException) {
                return response()->json([
                    'message' => 'Your profile was saved, but the verification email could not be sent. Your email was not changed.',
                ], 502);
            }

            return response()->json([
                'message' => 'We sent a verification code to your new email. Enter that code to confirm the change.',
                'email_verification_required' => true,
                'user' => $this->profilePayload($user),
            ]);
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->profilePayload($user),
        ]);
    }

    public function verifyEmail(Request $request, EmailChangeOtpService $emailChange): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $emailChange->confirm($request->user(), $validated['code']);

        AdminActivityService::log((int) $user->user_id, 'Confirmed email change', 'Account');

        return response()->json([
            'message' => 'Your email address has been updated.',
            'user' => $this->profilePayload($user),
        ]);
    }

    private function profilePayload($user): array
    {
        return [
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
        ];
    }
}
