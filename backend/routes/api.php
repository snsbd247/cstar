<?php

use App\Http\Controllers\Api\V1\Accounts\CashClosingController;
use App\Http\Controllers\Api\V1\Accounts\ExpenseController;
use App\Http\Controllers\Api\V1\Accounts\LedgerController;
use App\Http\Controllers\Api\V1\Accounts\PayrollController;
use App\Http\Controllers\Api\V1\Accounts\ReportController;
use App\Http\Controllers\Api\V1\Accounts\SetupController;
use App\Http\Controllers\Api\V1\Accounts\VoucherController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Billing\InvoiceController;
use App\Http\Controllers\Api\V1\Billing\PackageController;
use App\Http\Controllers\Api\V1\Billing\PaymentController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\Cms\CmsContentController;
use App\Http\Controllers\Api\V1\Cms\WebsiteSetupController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EnquiryInboxController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\PatientDocumentController;
use App\Http\Controllers\Api\V1\Portal\PortalController;
use App\Http\Controllers\Api\V1\ReportController as OperationalReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\Therapy\AppointmentController;
use App\Http\Controllers\Api\V1\Therapy\AssessmentController;
use App\Http\Controllers\Api\V1\Therapy\TherapistController;
use App\Http\Controllers\Api\V1\Therapy\TherapySessionController;
use App\Http\Controllers\Api\V1\Training\IndividualPlanController;
use App\Http\Controllers\Api\V1\Training\TrainerController;
use App\Http\Controllers\Api\V1\Training\TrainingAttendanceController;
use App\Http\Controllers\Api\V1\Training\TrainingGroupController;
use App\Http\Controllers\Api\V1\Training\TrainingOverviewController;
use App\Http\Controllers\Api\V1\Training\TrainingRecordController;
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

        Route::get('dashboard', [DashboardController::class, 'summary']);
        Route::get('reports', [OperationalReportController::class, 'index']);
        Route::get('reports/{key}', [OperationalReportController::class, 'show']);
        Route::get('lookups/enrollment-options', [LookupController::class, 'enrollmentOptions']);
        Route::get('lookups/diagnoses', [LookupController::class, 'diagnoses']);
        Route::get('lookups/bookable-services', [LookupController::class, 'bookableServices']);

        // Regular training (TRAINER ≠ THERAPIST, TRAINING SESSION ≠ THERAPY SESSION)
        Route::get('trainers', [TrainerController::class, 'index']);
        Route::post('trainers', [TrainerController::class, 'store']);
        Route::put('trainers/{trainer}', [TrainerController::class, 'update']);

        Route::get('classes', [TrainingGroupController::class, 'index']);
        Route::post('classes', [TrainingGroupController::class, 'store']);
        Route::get('classes/{class}', [TrainingGroupController::class, 'show']);
        Route::put('classes/{class}', [TrainingGroupController::class, 'update']);
        Route::get('classes/{class}/roster', [TrainingGroupController::class, 'roster']);
        Route::get('classes/{class}/attendance', [TrainingAttendanceController::class, 'roster']);
        Route::post('classes/{class}/attendance', [TrainingAttendanceController::class, 'mark']);
        Route::get('classes/{class}/attendance/month', [TrainingAttendanceController::class, 'classMonth']);
        Route::get('classes/{class}/records', [TrainingRecordController::class, 'forDay']);
        Route::post('classes/{class}/records', [TrainingRecordController::class, 'store']);

        Route::get('enrollments/{enrollment}/attendance', [TrainingAttendanceController::class, 'studentMonth']);
        Route::get('enrollments/{enrollment}/plans', [IndividualPlanController::class, 'index']);
        Route::post('enrollments/{enrollment}/plans', [IndividualPlanController::class, 'store']);
        Route::put('plans/{plan}', [IndividualPlanController::class, 'update']);

        Route::get('training-records', [TrainingRecordController::class, 'index']);
        Route::get('training-records/{trainingRecord}', [TrainingRecordController::class, 'show']);
        Route::get('students', [TrainingOverviewController::class, 'students']);
        Route::get('trainer/today', [TrainingOverviewController::class, 'today']);
        Route::get('lookups/activity-types', [TrainingOverviewController::class, 'activityTypes']);
        Route::get('holidays', [TrainingOverviewController::class, 'holidays']);
        Route::post('holidays', [TrainingOverviewController::class, 'storeHoliday']);
        Route::delete('holidays/{holiday}', [TrainingOverviewController::class, 'destroyHoliday']);

        // Therapy (THERAPY APPOINTMENT ≠ STUDENT ATTENDANCE, THERAPY SESSION ≠ TRAINING SESSION)
        Route::get('therapists', [TherapistController::class, 'index']);
        Route::post('therapists', [TherapistController::class, 'store']);
        Route::put('therapists/{therapist}', [TherapistController::class, 'update']);
        Route::put('therapists/{therapist}/schedule', [TherapistController::class, 'updateSchedule']);
        Route::post('therapists/{therapist}/leaves', [TherapistController::class, 'storeLeave']);
        Route::delete('therapist-leaves/{leave}', [TherapistController::class, 'destroyLeave']);

        Route::get('availability', [AppointmentController::class, 'availability']);
        Route::get('appointments', [AppointmentController::class, 'index']);
        Route::post('appointments', [AppointmentController::class, 'store']);
        Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
        Route::post('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
        Route::post('appointments/{appointment}/{action}', [AppointmentController::class, 'changeStatus'])
            ->whereIn('action', ['confirm', 'check-in', 'cancel', 'no-show']);
        Route::get('appointments/{appointment}/session', [TherapySessionController::class, 'show']);
        Route::post('appointments/{appointment}/session', [TherapySessionController::class, 'store']);
        Route::get('therapy-sessions', [TherapySessionController::class, 'index']);
        Route::get('therapist/today', [TherapySessionController::class, 'today']);
        Route::get('therapist/patients', [TherapySessionController::class, 'myPatients']);
        Route::get('enrollments/{enrollment}/slots', [TherapySessionController::class, 'slots']);
        Route::put('enrollments/{enrollment}/slots', [TherapySessionController::class, 'updateSlots']);
        Route::post('enrollments/{enrollment}/generate-appointments', [TherapySessionController::class, 'generate']);

        // Billing: packages, invoices, payments, dues (Plan §১৯) — every money event auto-posts to the ledger
        Route::get('packages', [PackageController::class, 'index']);
        Route::post('packages', [PackageController::class, 'store']);
        Route::put('packages/{package}', [PackageController::class, 'update']);
        Route::get('patient-packages', [PackageController::class, 'patientPackages']);
        Route::post('patients/{patient}/packages', [PackageController::class, 'sell']);
        Route::get('billing/settings', [PackageController::class, 'settings']);
        Route::put('billing/settings', [PackageController::class, 'updateSettings']);
        Route::get('invoices', [InvoiceController::class, 'index']);
        Route::post('invoices', [InvoiceController::class, 'store']);
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy']);
        Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue']);
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void']);
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
        Route::post('billing/training-fees', [InvoiceController::class, 'generateTrainingFees']);
        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('payments/{payment}', [PaymentController::class, 'show']);
        Route::post('payments/{payment}/void', [PaymentController::class, 'void']);
        Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt']);
        Route::post('patients/{patient}/payments', [PaymentController::class, 'store']);
        Route::post('patients/{patient}/refunds', [PaymentController::class, 'refund']);
        Route::get('patients/{patient}/billing', [PaymentController::class, 'account']);
        Route::get('billing/dues', [PaymentController::class, 'dues']);
        Route::get('billing/collection', [PaymentController::class, 'collection']);

        // Accounts (read-only until Accounts A): chart with balances, auto-posted journal
        Route::get('accounts/chart', [LedgerController::class, 'chart']);
        Route::get('accounts/journal', [LedgerController::class, 'journal']);

        // Accounts A: vouchers (maker-checker), expenses, cash closing, period lock, financial reports
        Route::get('accounts/dashboard', [ReportController::class, 'dashboard']);
        Route::get('accounts/vouchers', [VoucherController::class, 'index']);
        Route::post('accounts/vouchers', [VoucherController::class, 'store']);
        Route::get('accounts/vouchers/{voucher}', [VoucherController::class, 'show']);
        Route::put('accounts/vouchers/{voucher}', [VoucherController::class, 'update']);
        Route::delete('accounts/vouchers/{voucher}', [VoucherController::class, 'destroy']);
        Route::post('accounts/vouchers/{voucher}/{action}', [VoucherController::class, 'action'])->whereIn('action', ['submit', 'approve', 'reject', 'reverse']);
        Route::get('accounts/expenses', [ExpenseController::class, 'index']);
        Route::post('accounts/expenses', [ExpenseController::class, 'store']);
        Route::post('accounts/expenses/{expense}/submit', [ExpenseController::class, 'submit']);
        Route::get('accounts/expenses/{expense}/attachment', [ExpenseController::class, 'attachment']);
        Route::get('accounts/expense-options', [ExpenseController::class, 'options']);
        Route::post('accounts/expense-categories', [ExpenseController::class, 'storeCategory']);
        Route::get('accounts/recurring-expenses', [ExpenseController::class, 'recurring']);
        Route::post('accounts/recurring-expenses', [ExpenseController::class, 'saveRecurring']);
        Route::put('accounts/recurring-expenses/{recurring}', [ExpenseController::class, 'saveRecurring']);
        Route::get('accounts/cash-closings', [CashClosingController::class, 'index']);
        Route::get('accounts/cash-closings/expected', [CashClosingController::class, 'expected']);
        Route::post('accounts/cash-closings', [CashClosingController::class, 'store']);
        Route::post('accounts/cash-closings/{closing}/receive', [CashClosingController::class, 'receive']);
        Route::post('accounts/chart', [SetupController::class, 'storeAccount']);
        Route::put('accounts/chart/{account}', [SetupController::class, 'updateAccount']);
        Route::get('accounts/periods', [SetupController::class, 'periods']);
        Route::post('accounts/periods/{period}/close', [SetupController::class, 'closePeriod']);
        Route::post('accounts/periods/{period}/reopen', [SetupController::class, 'reopenPeriod']);
        Route::get('accounts/settings', [SetupController::class, 'settings']);
        Route::put('accounts/settings', [SetupController::class, 'updateSettings']);
        Route::get('accounts/reports/trial-balance', [ReportController::class, 'trialBalance']);
        Route::get('accounts/reports/ledger', [ReportController::class, 'ledger']);
        Route::get('accounts/reports/day-book', [ReportController::class, 'dayBook']);
        Route::get('accounts/reports/income-statement', [ReportController::class, 'incomeStatement']);
        Route::get('accounts/reports/balance-sheet', [ReportController::class, 'balanceSheet']);

        // Accounts B: employees, pay setup, advances, payroll, payslips
        Route::get('hr/employees', [PayrollController::class, 'employees']);
        Route::get('hr/employee-options', [PayrollController::class, 'employeeOptions']);
        Route::post('hr/employees', [PayrollController::class, 'storeEmployee']);
        Route::get('hr/employees/{employee}', [PayrollController::class, 'employee']);
        Route::put('hr/employees/{employee}', [PayrollController::class, 'updateEmployee']);
        Route::post('hr/employees/{employee}/salary-structures', [PayrollController::class, 'saveStructure']);
        Route::post('hr/employees/{employee}/session-rates', [PayrollController::class, 'saveRate']);
        Route::post('hr/employees/{employee}/advances', [PayrollController::class, 'giveAdvance']);
        Route::get('payroll/runs', [PayrollController::class, 'runs']);
        Route::post('payroll/runs', [PayrollController::class, 'storeRun']);
        Route::get('payroll/runs/{run}', [PayrollController::class, 'run']);
        Route::get('payroll/runs/{run}/sheet', [PayrollController::class, 'salarySheet']);
        Route::post('payroll/runs/{run}/{action}', [PayrollController::class, 'action'])->whereIn('action', ['recalculate', 'approve', 'reopen', 'pay']);
        Route::put('payroll/items/{item}', [PayrollController::class, 'updateItem']);
        Route::get('payroll/payslips/{item}/pdf', [PayrollController::class, 'payslip']);
        Route::get('me/payslips', [PayrollController::class, 'myPayslips']);

        // Parent portal (Plan §১৫) — only the parent's own children, only family-facing fields
        Route::prefix('portal')->group(function () {
            Route::get('children', [PortalController::class, 'children']);
            Route::get('children/{patient}/home', [PortalController::class, 'home']);
            Route::get('children/{patient}/schedule', [PortalController::class, 'schedule']);
            Route::get('children/{patient}/attendance', [PortalController::class, 'attendance']);
            Route::get('children/{patient}/progress', [PortalController::class, 'progress']);
            Route::get('children/{patient}/billing', [PortalController::class, 'billing']);
            Route::get('children/{patient}/progress-report', [PortalController::class, 'progressReport']);
            Route::post('children/{patient}/appointment-requests', [PortalController::class, 'requestAppointment'])->middleware('throttle:10,60');
            Route::get('invoices/{invoice}/pdf', [PortalController::class, 'invoicePdf']);
            Route::get('payments/{payment}/receipt', [PortalController::class, 'receiptPdf']);
            Route::get('assessments/{assessment}/pdf', [PortalController::class, 'assessmentPdf']);
            Route::get('profile', [PortalController::class, 'profile']);
        });

        // Assessments → recommendations → enrollment; PDF reports
        Route::get('lookups/assessment-types', [AssessmentController::class, 'types']);
        Route::get('assessments', [AssessmentController::class, 'index']);
        Route::post('patients/{patient}/assessments', [AssessmentController::class, 'store']);
        Route::get('assessments/{assessment}', [AssessmentController::class, 'show']);
        Route::put('assessments/{assessment}', [AssessmentController::class, 'update']);
        Route::post('assessments/{assessment}/share', [AssessmentController::class, 'share']);
        Route::get('assessments/{assessment}/pdf', [AssessmentController::class, 'pdf']);
        Route::get('patients/{patient}/recommendations', [AssessmentController::class, 'recommendations']);
        Route::get('patients/{patient}/progress-report', [AssessmentController::class, 'progressReport']);

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
        Route::get('announcements', [NotificationController::class, 'announcements']);
        Route::post('announcements', [NotificationController::class, 'announce'])->middleware('throttle:20,60');
        Route::get('notification-settings', [NotificationController::class, 'settings']);
        Route::put('notification-settings', [NotificationController::class, 'updateSettings']);
    });
});
