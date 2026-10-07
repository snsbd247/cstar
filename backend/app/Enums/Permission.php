<?php

namespace App\Enums;

/**
 * Permission catalogue (module.action). Defined up-front for every planned module so the
 * role matrix is fixed from day one; modules are built sprint by sprint.
 */
final class Permission
{
    public const BRANCHES_VIEW = 'branches.view';

    public const BRANCHES_MANAGE = 'branches.manage';

    public const USERS_VIEW = 'users.view';

    public const USERS_MANAGE = 'users.manage';

    public const ROLES_MANAGE = 'roles.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const AUDIT_LOGS_VIEW = 'audit_logs.view';

    public const PATIENTS_VIEW = 'patients.view';

    public const PATIENTS_CREATE = 'patients.create';

    public const PATIENTS_UPDATE = 'patients.update';

    public const PATIENTS_DELETE = 'patients.delete';

    public const PATIENTS_VIEW_CLINICAL = 'patients.view_clinical';

    public const GUARDIANS_MANAGE = 'guardians.manage';

    public const ENROLLMENTS_VIEW = 'enrollments.view';

    public const ENROLLMENTS_MANAGE = 'enrollments.manage';

    public const CLASSES_VIEW = 'classes.view';

    public const CLASSES_MANAGE = 'classes.manage';

    public const TRAINERS_VIEW = 'trainers.view';

    public const TRAINERS_MANAGE = 'trainers.manage';

    public const TRAINING_ATTENDANCE_VIEW = 'training_attendance.view';

    public const TRAINING_ATTENDANCE_MARK = 'training_attendance.mark';

    public const TRAINING_RECORDS_VIEW = 'training_records.view';

    public const TRAINING_RECORDS_WRITE = 'training_records.write';

    public const THERAPISTS_VIEW = 'therapists.view';

    public const THERAPISTS_MANAGE = 'therapists.manage';

    public const APPOINTMENTS_VIEW = 'appointments.view';

    public const APPOINTMENTS_MANAGE = 'appointments.manage';

    public const APPOINTMENT_REQUESTS_MANAGE = 'appointment_requests.manage';

    public const THERAPY_SESSIONS_VIEW = 'therapy_sessions.view';

    public const THERAPY_SESSIONS_WRITE = 'therapy_sessions.write';

    public const ASSESSMENTS_VIEW = 'assessments.view';

    public const ASSESSMENTS_WRITE = 'assessments.write';

    public const PLANS_VIEW = 'plans.view';

    public const PLANS_WRITE = 'plans.write';

    public const HOME_PROGRAMS_VIEW = 'home_programs.view';

    public const HOME_PROGRAMS_WRITE = 'home_programs.write';

    public const PACKAGES_VIEW = 'packages.view';

    public const PACKAGES_MANAGE = 'packages.manage';

    public const INVOICES_VIEW = 'invoices.view';

    public const INVOICES_MANAGE = 'invoices.manage';

    public const INVOICES_VOID = 'invoices.void';

    public const PAYMENTS_VIEW = 'payments.view';

    public const PAYMENTS_CREATE = 'payments.create';

    public const DISCOUNTS_APPLY = 'discounts.apply';

    public const ACCOUNTS_VIEW = 'accounts.view';

    public const ACCOUNTS_COA_MANAGE = 'accounts.coa.manage';

    public const ACCOUNTS_EXPENSE_CREATE = 'accounts.expense.create';

    public const ACCOUNTS_VOUCHER_CREATE = 'accounts.voucher.create';

    public const ACCOUNTS_VOUCHER_APPROVE = 'accounts.voucher.approve';

    public const ACCOUNTS_PAYROLL_MANAGE = 'accounts.payroll.manage';

    public const ACCOUNTS_PAYROLL_APPROVE = 'accounts.payroll.approve';

    public const ACCOUNTS_CASH_CLOSING = 'accounts.cash_closing';

    public const ACCOUNTS_PERIOD_CLOSE = 'accounts.period.close';

    public const ACCOUNTS_REPORTS = 'accounts.reports';

    public const INVENTORY_VIEW = 'inventory.view';             // Sprint 20

    public const INVENTORY_MANAGE = 'inventory.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const REPORTS_FINANCIAL = 'reports.financial';

    public const CMS_MANAGE = 'cms.manage';

    public const NOTIFICATIONS_SEND = 'notifications.send';

    public const PORTAL_ACCESS = 'portal.access';

    /** @return list<string> */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }

    /** @return list<string> */
    public static function accounts(): array
    {
        return array_values(array_filter(self::all(), fn (string $p) => str_starts_with($p, 'accounts.')));
    }
}
