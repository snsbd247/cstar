<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\Cms\CmsContentController;
use App\Http\Controllers\Api\V1\Cms\WebsiteSetupController;
use App\Http\Controllers\Api\V1\EnquiryInboxController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\PatientDocumentController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('auth/password', [AuthController::class, 'changePassword']);

        Route::apiResource('branches', BranchController::class);
        Route::apiResource('users', UserController::class);

        Route::get('roles', [RoleController::class, 'index']);
        Route::get('permissions', [RoleController::class, 'permissions']);
        Route::put('roles/{role}', [RoleController::class, 'update']);

        // Patients (PATIENT ≠ STUDENT)
        Route::get('patients/check-duplicates', [PatientController::class, 'checkDuplicates']);
        Route::apiResource('patients', PatientController::class)->except('destroy');
        Route::get('patients/{patient}/photo', [PatientController::class, 'photo'])->name('patients.photo');
        Route::post('patients/{patient}/photo', [PatientController::class, 'uploadPhoto']);
        Route::get('patients/{patient}/timeline', [PatientController::class, 'timeline']);

        Route::get('guardians/lookup', [GuardianController::class, 'lookup']);
        Route::post('guardians/{guardian}/portal-account', [GuardianController::class, 'createPortalAccount']);
        Route::post('patients/{patient}/guardians', [GuardianController::class, 'store']);
        Route::put('patients/{patient}/guardians/{guardian}', [GuardianController::class, 'update']);
        Route::delete('patients/{patient}/guardians/{guardian}', [GuardianController::class, 'destroy']);

        Route::get('patients/{patient}/documents', [PatientDocumentController::class, 'index']);
        Route::post('patients/{patient}/documents', [PatientDocumentController::class, 'store']);
        Route::get('documents/{document}/download', [PatientDocumentController::class, 'download']);
        Route::delete('documents/{document}', [PatientDocumentController::class, 'destroy']);

        // Enrollments: one patient, many enrollments (training and/or therapy)
        Route::get('enrollments', [EnrollmentController::class, 'index']);
        Route::post('enrollments', [EnrollmentController::class, 'store']);
        Route::get('enrollments/{enrollment}', [EnrollmentController::class, 'show']);
        Route::post('enrollments/{enrollment}/transfer', [EnrollmentController::class, 'transfer']);
        Route::post('enrollments/{enrollment}/{action}', [EnrollmentController::class, 'changeStatus'])
            ->whereIn('action', ['activate', 'hold', 'resume', 'complete', 'discontinue']);

        Route::get('lookups/enrollment-options', [LookupController::class, 'enrollmentOptions']);
        Route::get('lookups/diagnoses', [LookupController::class, 'diagnoses']);

        // Website front-desk inbox
        Route::get('appointment-requests', [EnquiryInboxController::class, 'appointmentRequests']);
        Route::put('appointment-requests/{appointmentRequest}', [EnquiryInboxController::class, 'updateAppointmentRequest']);
        Route::get('contact-messages', [EnquiryInboxController::class, 'contactMessages']);
        Route::put('contact-messages/{contactMessage}', [EnquiryInboxController::class, 'updateContactMessage']);

        // Website CMS
        Route::prefix('cms')->group(function () {
            Route::get('settings', [WebsiteSetupController::class, 'settings']);
            Route::put('settings', [WebsiteSetupController::class, 'updateSettings']);
            Route::get('services', [WebsiteSetupController::class, 'services']);
            Route::put('services/{service}', [WebsiteSetupController::class, 'updateService']);
            Route::post('services/{service}/image', [WebsiteSetupController::class, 'uploadServiceImage']);
            Route::get('team', [WebsiteSetupController::class, 'team']);
            Route::put('team/{kind}/{id}', [WebsiteSetupController::class, 'updateTeamMember'])->whereIn('kind', ['therapist', 'trainer']);
            Route::post('team/{kind}/{id}/photo', [WebsiteSetupController::class, 'uploadTeamPhoto'])->whereIn('kind', ['therapist', 'trainer']);

            $types = ['testimonials', 'faqs', 'notices', 'gallery'];
            Route::get('{type}', [CmsContentController::class, 'index'])->whereIn('type', $types);
            Route::post('{type}', [CmsContentController::class, 'store'])->whereIn('type', $types);
            // POST alias so the gallery can send a new image with multipart/form-data
            Route::match(['put', 'post'], '{type}/{id}', [CmsContentController::class, 'update'])->whereIn('type', $types)->whereNumber('id');
            Route::delete('{type}/{id}', [CmsContentController::class, 'destroy'])->whereIn('type', $types)->whereNumber('id');
        });

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);
    });
});
