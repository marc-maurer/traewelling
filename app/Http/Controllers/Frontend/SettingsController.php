<?php

namespace App\Http\Controllers\Frontend;

use App\Enum\StatusVisibility;
use App\Exceptions\AlreadyFollowingException;
use App\Http\Controllers\Backend\User\FollowController;
use App\Http\Controllers\Backend\User\FollowController as SettingsBackend;
use App\Http\Controllers\Backend\User\SessionController;
use App\Http\Controllers\Backend\User\TokenController;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SettingsController extends Controller
{

    public function updateMainSettings(Request $request): RedirectResponse {
        $validated = $request->validate([
                                            'username'   => ['required', 'string', 'max:25', 'regex:/^[a-zA-Z0-9_]*$/'],
                                            'name'       => ['required', 'string', 'max:50'],
                                            'email'      => ['required', 'string', 'email:rfc,dns', 'max:255'],
                                            'always_dbl' => ['nullable'],
                                        ]);

        if (auth()->user()->username !== $validated['username']) {
            $request->validate(['username' => ['unique:users']]);
        }

        if (auth()->user()->email !== $validated['email']) {
            $request->validate(['email' => ['unique:users']]);
            $validated['email_verified_at'] = null;
            $validated['email']             = strtolower($validated['email']);
        }
        $validated['always_dbl'] = isset($validated['always_dbl']) && $validated['always_dbl'] === 'on';

        auth()->user()->update($validated);

        if (!auth()->user()->hasVerifiedEmail()) {
            auth()->user()->sendEmailVerificationNotification();
            session()->flash('info', __('email.verification.sent'));
        }

        return back();
    }

    public function updatePrivacySettings(Request $request): RedirectResponse {
        $validated = $request->validate([
                                            'private_profile'           => ['nullable'],
                                            'prevent_index'             => ['required', 'gte:0', 'lte:1'],
                                            'privacy_hide_days'         => ['nullable', 'gte:1',],
                                            'default_status_visibility' => [
                                                'required',
                                                new Enum(StatusVisibility::class),
                                            ]
                                        ]);

        auth()->user()->update([
                                   'prevent_index'             => $validated['prevent_index'],
                                   'private_profile'           => isset($validated['private_profile'])
                                                                  && $validated['private_profile'] === 'on',
                                   'privacy_hide_days'         => $validated['privacy_hide_days'] ?? null,
                                   'default_status_visibility' => $validated['default_status_visibility'],
                               ]);

        return back()->with('success', __('settings.privacy.update.success'));
    }

    public function updatePassword(Request $request): RedirectResponse {
        $userHasPassword = auth()->user()->password != null;

        $validated = $request->validate([
                                            'currentPassword' => [Rule::requiredIf($userHasPassword)],
                                            'password'        => ['required', 'string', 'min:8', 'confirmed']
                                        ]);

        if ($userHasPassword && !Hash::check($validated['currentPassword'], auth()->user()->password)) {
            return back()->withErrors(__('controller.user.password-wrong'));
        }

        auth()->user()->update([
                                   'password' => Hash::make($validated['password'])
                               ]);

        return back()->with('info', __('controller.user.password-changed-ok'));
    }

    public function renderSettings(): Renderable {
        return view('settings.settings', [
            'sessions' => SessionController::index(user: auth()->user()),
            'tokens'   => TokenController::index(user: auth()->user())
        ]);
    }

    /**
     * Approve a follow request
     *
     * @param Request $request
     *
     * @return RedirectResponse
     * @throws AlreadyFollowingException
     */
    public function approveFollower(Request $request): RedirectResponse {
        $validated = $request->validate([
                                            'user_id' => [
                                                'required',
                                                Rule::in(auth()->user()->followRequests->pluck('user_id'))
                                            ]
                                        ]);

        try {
            $approval = SettingsBackend::approveFollower(auth()->user()->id, $validated['user_id']);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        if ($approval) {
            return back()->with('success', __('settings.request.accept-success'));
        }
        return back()->with('danger', __('messages.exception.general'));
    }

    /**
     * Reject a follow request
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function rejectFollower(Request $request): RedirectResponse {
        $validated = $request->validate([
                                            'user_id' => [
                                                'required',
                                                Rule::in(auth()->user()->followRequests->pluck('user_id'))
                                            ]
                                        ]);
        try {
            $approval = FollowController::rejectFollower(auth()->user()->id, $validated['user_id']);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        if ($approval) {
            return back()->with('success', __('settings.request.reject-success'));
        }
        return back()->with('danger', __('messages.exception.general'));
    }
}
