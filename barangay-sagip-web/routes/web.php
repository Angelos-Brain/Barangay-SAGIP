<?php

use App\Http\Controllers\AccountVerificationController;
use App\Http\Controllers\Admin\AuthenticatedSessionController as AdminAuthenticatedSessionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PersonnelAccountSetupController;
use App\Http\Controllers\Auth\PersonnelLoginController;
use App\Http\Controllers\Auth\PersonnelOnboardingController;
use App\Http\Controllers\Auth\PersonnelPasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmergencyRequestController;
use App\Http\Controllers\EvacuationCenterController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResidentProfileController;
use App\Http\Controllers\ResponseAssignmentController;
use App\Http\Controllers\ResponsePersonnelController;
use App\Http\Controllers\SosController;
use App\Http\Controllers\TanodDutyController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:login');
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

Route::middleware('guest')->group(function () {
    Route::get('admin/login', [AdminAuthenticatedSessionController::class, 'create'])->name('admin.login');
    Route::post('admin/login', [AdminAuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')->name('admin.login.store');
});

// Response personnel. First Login (once): mobile number -> SMS code, then the
// password and email steps below. Afterwards: email or mobile + password.
Route::middleware('guest')->group(function () {
    Route::get('personnel/login', [PersonnelLoginController::class, 'create'])->name('personnel.login');
    Route::post('personnel/login', [PersonnelLoginController::class, 'store'])
        ->middleware('throttle:personnel-login')->name('personnel.login.store');

    Route::get('personnel/setup', [PersonnelOnboardingController::class, 'create'])->name('personnel.setup');
    Route::get('personnel/setup/verify', [PersonnelOnboardingController::class, 'showVerify'])->name('personnel.setup.verify');

    Route::get('personnel/password/forgot', [PersonnelPasswordResetController::class, 'create'])->name('personnel.password.request');
    Route::get('personnel/password/verify', [PersonnelPasswordResetController::class, 'showVerify'])->name('personnel.password.verify');
    Route::get('personnel/password/reset', [PersonnelPasswordResetController::class, 'edit'])->name('personnel.password.reset');
    Route::post('personnel/password/reset', [PersonnelPasswordResetController::class, 'update'])->name('personnel.password.update');

    Route::middleware('throttle:personnel-code')->group(function () {
        Route::post('personnel/setup', [PersonnelOnboardingController::class, 'sendCode'])->name('personnel.setup.send');
        Route::post('personnel/setup/resend', [PersonnelOnboardingController::class, 'resend'])->name('personnel.setup.resend');
        Route::post('personnel/password/forgot', [PersonnelPasswordResetController::class, 'sendCode'])->name('personnel.password.send');
        Route::post('personnel/password/resend', [PersonnelPasswordResetController::class, 'resend'])->name('personnel.password.resend');
    });

    Route::middleware('throttle:personnel-login')->group(function () {
        Route::post('personnel/setup/verify', [PersonnelOnboardingController::class, 'verify'])->name('personnel.setup.check');
        Route::post('personnel/password/verify', [PersonnelPasswordResetController::class, 'verify'])->name('personnel.password.check');
    });
});

// The emailed confirmation link may be opened signed in or not, on any device.
Route::get('personnel/email/confirm/{user}/{token}', [PersonnelAccountSetupController::class, 'confirmEmail'])
    ->middleware('throttle:personnel-login')->name('personnel.email.confirm');

Route::post('admin/logout', [AdminAuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('admin.logout');

Route::middleware('auth')->group(function () {
    // First Login, after the SMS step: password, email confirmation, ready.
    // EnsurePersonnelAccountSetup keeps an unfinished account on these screens.
    Route::get('account/setup', [PersonnelAccountSetupController::class, 'index'])->name('account.setup');
    Route::get('account/setup/password', [PersonnelAccountSetupController::class, 'editPassword'])->name('account.setup.password');
    Route::post('account/setup/password', [PersonnelAccountSetupController::class, 'updatePassword'])->name('account.setup.password.store');
    Route::get('account/setup/email', [PersonnelAccountSetupController::class, 'editEmail'])->name('account.setup.email');
    Route::middleware('throttle:personnel-code')->group(function () {
        Route::post('account/setup/email', [PersonnelAccountSetupController::class, 'sendEmail'])->name('account.setup.email.store');
        Route::post('account/setup/email/resend', [PersonnelAccountSetupController::class, 'resendEmail'])->name('account.setup.email.resend');
    });
    Route::get('account/setup/ready', [PersonnelAccountSetupController::class, 'ready'])->name('account.setup.ready');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('requests', [EmergencyRequestController::class, 'index'])->name('requests.index');
    Route::get('map', [MapController::class, 'index'])->name('map.index');

    // Feature 4: every role may read the evacuation center roster; only roles
    // holding evacuationCenters.manage may change it (enforced in the
    // controller and the form request).
    Route::get('evacuation-centers', [EvacuationCenterController::class, 'index'])
        ->name('evacuation-centers.index');
    Route::get('evacuation-centers/create', [EvacuationCenterController::class, 'create'])
        ->name('evacuation-centers.create');
    Route::post('evacuation-centers', [EvacuationCenterController::class, 'store'])
        ->name('evacuation-centers.store');
    Route::get('evacuation-centers/{evacuationCenter}/edit', [EvacuationCenterController::class, 'edit'])
        ->name('evacuation-centers.edit');
    Route::put('evacuation-centers/{evacuationCenter}', [EvacuationCenterController::class, 'update'])
        ->name('evacuation-centers.update');
    Route::delete('evacuation-centers/{evacuationCenter}', [EvacuationCenterController::class, 'destroy'])
        ->name('evacuation-centers.destroy');
    Route::get('map/data', [MapController::class, 'data'])->name('map.data');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
    Route::get('account/verification', [AccountVerificationController::class, 'pending'])
        ->name('account.verification.pending');

    // Feature 2: the SOS button is reachable from every authenticated page, but
    // only by a verified account (Feature 1) and under its own rate limit.
    Route::middleware(['verified.account', 'throttle:sos'])->group(function () {
        Route::post('sos', [SosController::class, 'store'])->name('sos.store');
        Route::post('sos/sms-fallback', [SosController::class, 'smsFallback'])->name('sos.smsFallback');
    });
    // The optional photo / voice note follows the SOS; it is not rate-limited
    // with the trigger so a slow upload never costs the resident their SOS.
    Route::post('sos/{emergencyRequest}/attachment', [SosController::class, 'attach'])
        ->middleware(['verified.account', 'throttle:emergency-request'])
        ->name('sos.attach');
    Route::get('requests/{emergencyRequest}/attachment', [EmergencyRequestController::class, 'attachment'])
        ->name('requests.attachment');
    Route::get('account/photo', [ProfilePhotoController::class, 'edit'])->name('account.photo.edit');
    Route::put('account/photo', [ProfilePhotoController::class, 'update'])->name('account.photo.update');

    Route::middleware('role:resident')->group(function () {
        Route::get('profile', [ResidentProfileController::class, 'edit'])->name('residents.profile.edit');
        Route::put('profile', [ResidentProfileController::class, 'update'])->name('residents.profile.update');
        // Feature 1: emergency reporting stays locked until an official
        // verifies the account.
        Route::middleware('verified.account')->group(function () {
            Route::get('requests/create', [EmergencyRequestController::class, 'create'])->name('requests.create');
            Route::post('requests', [EmergencyRequestController::class, 'store'])
                ->middleware('throttle:emergency-request')
                ->name('requests.store');
        });
    });

    Route::get('requests/{emergencyRequest}', [EmergencyRequestController::class, 'show'])->name('requests.show');

    Route::middleware('role:admin,official,personnel')->group(function () {
        Route::patch('requests/{emergencyRequest}/status', [EmergencyRequestController::class, 'updateStatus'])
            ->name('requests.updateStatus');
        // Feature 2: post-incident validation and the responder-side cooldown
        // override. Both are further limited to the incident's own responders.
        Route::patch('requests/{emergencyRequest}/outcome', [EmergencyRequestController::class, 'updateOutcome'])
            ->name('requests.updateOutcome');
        Route::post('requests/{emergencyRequest}/sos-cooldown/clear', [SosController::class, 'clearCooldown'])
            ->name('requests.clearSosCooldown');
    });

    // Personnel may update only their own live location, availability, and
    // specialization tags.
    Route::middleware('role:personnel')->group(function () {
        Route::post('personnel/location', [ResponsePersonnelController::class, 'updateLocation'])
            ->name('personnel.updateLocation');
        Route::post('personnel/availability', [ResponsePersonnelController::class, 'updateOwnAvailability'])
            ->name('personnel.updateOwnAvailability');
        Route::get('personnel/specializations', [ResponsePersonnelController::class, 'editOwnSpecializations'])
            ->name('personnel.specializations.edit');
        Route::put('personnel/specializations', [ResponsePersonnelController::class, 'updateOwnSpecializations'])
            ->name('personnel.specializations.update');
    });

    // Feature 7: a tanod goes on duty only from inside the barangay hall radius.
    Route::middleware('role:tanod')->group(function () {
        Route::post('tanod/check-in', [TanodDutyController::class, 'checkIn'])->name('tanod.checkIn');
        Route::post('tanod/check-out', [TanodDutyController::class, 'checkOut'])->name('tanod.checkOut');
    });

    Route::middleware('role:admin,official')->group(function () {
        Route::get('requests/{emergencyRequest}/assign', [ResponseAssignmentController::class, 'edit'])->name('requests.assign.edit');
        Route::post('requests/{emergencyRequest}/assign', [ResponseAssignmentController::class, 'store'])->name('requests.assign.store');
        Route::get('personnel', [ResponsePersonnelController::class, 'index'])->name('personnel.index');
        Route::get('personnel/create', [ResponsePersonnelController::class, 'create'])->name('personnel.create');
        Route::post('personnel', [ResponsePersonnelController::class, 'store'])->name('personnel.store');
        Route::get('personnel/{personnel}/edit', [ResponsePersonnelController::class, 'edit'])->name('personnel.edit');
        Route::put('personnel/{personnel}', [ResponsePersonnelController::class, 'update'])->name('personnel.update');
        Route::delete('personnel/{personnel}', [ResponsePersonnelController::class, 'destroy'])->name('personnel.destroy');
        Route::put('personnel/{personnel}/photo', [ProfilePhotoController::class, 'updatePersonnel'])->name('personnel.photo.update');
        Route::post('personnel/{personnel}/toggle-availability', [ResponsePersonnelController::class, 'toggleAvailability'])
            ->name('personnel.toggleAvailability');
        Route::get('verifications', [AccountVerificationController::class, 'index'])->name('verifications.index');
        Route::patch('verifications/{user}', [AccountVerificationController::class, 'update'])->name('verifications.update');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'exportCsv'])->name('reports.export');
    });

    // Feature 3: the audit trail is readable by administrators only.
    Route::middleware('role:admin')->group(function () {
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
});
