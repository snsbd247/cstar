<?php

namespace App\Enums;

/**
 * The seven C-STAR roles. Trainer and Therapist are deliberately separate roles
 * (TRAINER ≠ THERAPIST) with separate permission sets and separate apps.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case BranchAdmin = 'branch_admin';
    case Receptionist = 'receptionist';
    case Trainer = 'trainer';
    case Therapist = 'therapist';
    case Accountant = 'accountant';
    case Parent = 'parent';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::BranchAdmin => 'Branch Admin',
            self::Receptionist => 'Receptionist',
            self::Trainer => 'Trainer',
            self::Therapist => 'Therapist',
            self::Accountant => 'Accountant',
            self::Parent => 'Parent',
        };
    }

    /** The front-end app a user of this role lands in after login. */
    public function homePath(): string
    {
        return match ($this) {
            self::Trainer => '/trainer',
            self::Therapist => '/therapist',
            self::Parent => '/portal',
            default => '/app',
        };
    }

    /** Roles a Branch Admin may hand out to staff of their own branch. */
    public static function assignableByBranchAdmin(): array
    {
        return [self::Receptionist, self::Trainer, self::Therapist, self::Accountant, self::Parent];
    }

    /** Ordered by privilege: the first matching role decides the user's primary role. */
    public static function byPriority(): array
    {
        return [
            self::SuperAdmin, self::BranchAdmin, self::Accountant, self::Receptionist,
            self::Therapist, self::Trainer, self::Parent,
        ];
    }

    /**
     * Default permissions per role (Plan §৭ and Accounts §১৪).
     * Super Admin is not listed: it passes every check via Gate::before.
     * Record-level scope (assigned students, own children, own branch) is enforced by Policies.
     *
     * @return list<string>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::all(),
            self::BranchAdmin => array_values(array_diff(Permission::all(), [
                Permission::ROLES_MANAGE, Permission::SETTINGS_MANAGE, Permission::CMS_MANAGE,
                Permission::ACCOUNTS_COA_MANAGE, Permission::ACCOUNTS_PERIOD_CLOSE,
                Permission::ACCOUNTS_PAYROLL_MANAGE, Permission::PORTAL_ACCESS,
            ])),
            self::Receptionist => [
                Permission::BRANCHES_VIEW,
                Permission::PATIENTS_VIEW, Permission::PATIENTS_CREATE, Permission::PATIENTS_UPDATE,
                Permission::GUARDIANS_MANAGE,
                Permission::ENROLLMENTS_VIEW, Permission::ENROLLMENTS_MANAGE,
                Permission::CLASSES_VIEW, Permission::TRAINERS_VIEW, Permission::THERAPISTS_VIEW,
                Permission::TRAINING_ATTENDANCE_VIEW,
                Permission::APPOINTMENTS_VIEW, Permission::APPOINTMENTS_MANAGE, Permission::APPOINTMENT_REQUESTS_MANAGE,
                Permission::PACKAGES_VIEW,
                Permission::INVOICES_VIEW, Permission::INVOICES_MANAGE,
                Permission::PAYMENTS_VIEW, Permission::PAYMENTS_CREATE, Permission::DISCOUNTS_APPLY,
                Permission::REPORTS_VIEW,
                Permission::ACCOUNTS_CASH_CLOSING, Permission::ACCOUNTS_EXPENSE_CREATE,
                Permission::INVENTORY_VIEW, Permission::INVENTORY_MANAGE,
            ],
            self::Trainer => [
                Permission::PATIENTS_VIEW, Permission::PATIENTS_VIEW_CLINICAL,
                Permission::ENROLLMENTS_VIEW, Permission::CLASSES_VIEW,
                Permission::TRAINING_ATTENDANCE_VIEW, Permission::TRAINING_ATTENDANCE_MARK,
                Permission::TRAINING_RECORDS_VIEW, Permission::TRAINING_RECORDS_WRITE,
                Permission::PLANS_VIEW, Permission::PLANS_WRITE,
                Permission::HOME_PROGRAMS_VIEW, Permission::HOME_PROGRAMS_WRITE,
            ],
            self::Therapist => [
                Permission::PATIENTS_VIEW, Permission::PATIENTS_VIEW_CLINICAL,
                Permission::ENROLLMENTS_VIEW, Permission::APPOINTMENTS_VIEW,
                Permission::THERAPY_SESSIONS_VIEW, Permission::THERAPY_SESSIONS_WRITE,
                Permission::ASSESSMENTS_VIEW, Permission::ASSESSMENTS_WRITE,
                Permission::PLANS_VIEW, Permission::PLANS_WRITE,
                Permission::HOME_PROGRAMS_VIEW, Permission::HOME_PROGRAMS_WRITE,
            ],
            self::Accountant => [
                Permission::BRANCHES_VIEW, Permission::PATIENTS_VIEW, Permission::ENROLLMENTS_VIEW,
                Permission::PACKAGES_VIEW, Permission::PACKAGES_MANAGE,
                Permission::INVOICES_VIEW, Permission::INVOICES_MANAGE, Permission::INVOICES_VOID,
                Permission::PAYMENTS_VIEW, Permission::PAYMENTS_CREATE, Permission::DISCOUNTS_APPLY,
                Permission::REPORTS_VIEW, Permission::REPORTS_FINANCIAL,
                ...Permission::accounts(),
                Permission::INVENTORY_VIEW, Permission::INVENTORY_MANAGE,
            ],
            self::Parent => [Permission::PORTAL_ACCESS],
        };
    }
}
