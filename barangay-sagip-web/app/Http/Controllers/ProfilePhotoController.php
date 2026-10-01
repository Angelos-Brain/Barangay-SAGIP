<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfilePhotoRequest;
use App\Models\ResponsePersonnel;
use App\Services\ProfilePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfilePhotoController extends Controller
{
    public function __construct(private ProfilePhotoService $photos) {}

    public function edit(): View
    {
        return view('account.photo', ['user' => Auth::user()]);
    }

    /**
     * Replace the authenticated user's own photo.
     * The user ID is deliberately not accepted from the request.
     */
    public function update(UpdateProfilePhotoRequest $request): RedirectResponse
    {
        $this->photos->replace(Auth::user(), $request->file('photo'));

        return back()->with('status', 'Profile photo updated.');
    }

    /**
     * Let an official set the photo of a personnel record's linked account.
     * Restricted to officials via the 'role:official' middleware in routes/web.php.
     */
    public function updatePersonnel(ResponsePersonnel $personnel, UpdateProfilePhotoRequest $request): RedirectResponse
    {
        abort_unless($personnel->user, 422, 'This personnel record is not linked to a user account.');

        $this->photos->replace($personnel->user, $request->file('photo'));

        return back()->with('status', "Photo updated for {$personnel->name}.");
    }
}
