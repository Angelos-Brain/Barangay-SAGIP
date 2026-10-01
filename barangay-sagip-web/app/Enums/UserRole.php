<?php

namespace App\Enums;

/**
 * Feature 3: Role-Based Access Control.
 *
 * `Resident`, `Personnel`, and `Official` are the roles this application
 * shipped with. `Personnel` is retained as the generic responder role (it sees
 * every category); the six specialized operational roles below narrow that to
 * one slice of the response workload, and `Admin` is the only role that can
 * read the audit trail.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Official = 'official';
    case Tanod = 'tanod';
    case Medical = 'medical';
    case FireDisaster = 'fire_disaster';
    case Weather = 'weather';
    case PeaceOrder = 'peace_order';
    case GeneralAssistant = 'general_assistant';
    case Personnel = 'personnel';
    case Resident = 'resident';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Official => 'Barangay Official',
            self::Tanod => 'Tanod',
            self::Medical => 'Medical Responder',
            self::FireDisaster => 'Fire / Disaster Responder',
            self::Weather => 'Weather Monitor',
            self::PeaceOrder => 'Peace & Order Responder',
            self::GeneralAssistant => 'General Assistant',
            self::Personnel => 'Response Personnel',
            self::Resident => 'Resident',
        };
    }

    /**
     * The operational (field responder) roles. These all satisfy
     * `User::isPersonnel()` and the `role:personnel` route middleware.
     *
     * @return list<self>
     */
    public static function operationalRoles(): array
    {
        return [
            self::Tanod,
            self::Medical,
            self::FireDisaster,
            self::Weather,
            self::PeaceOrder,
            self::GeneralAssistant,
            self::Personnel,
        ];
    }

    /**
     * Every role that logs in through the staff-only /admin/login screen.
     *
     * @return list<self>
     */
    public static function staffRoles(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role) => $role !== self::Resident,
        ));
    }

    /**
     * The login role for a responder an official adds with these tags: the
     * narrowest specialized role that covers every tag, or the generic
     * `Personnel` role (assignment-scoped) when no single role does. Tanod is
     * never chosen here — it carries the hall geofence and is assigned
     * deliberately.
     *
     * @param  list<string>  $specializationValues
     */
    public static function forSpecializations(array $specializationValues): self
    {
        foreach ([self::Medical, self::FireDisaster, self::Weather, self::PeaceOrder, self::GeneralAssistant] as $role) {
            $covered = array_map(fn (Specialization $specialization) => $specialization->value, $role->specializations());

            if (array_diff($specializationValues, $covered) === []) {
                return $role;
            }
        }

        return self::Personnel;
    }

    public function isOperational(): bool
    {
        return in_array($this, self::operationalRoles(), true);
    }

    public function isStaff(): bool
    {
        return $this !== self::Resident;
    }

    /**
     * Specializations this role responds to. Drives the specialization-scoped
     * request views; an empty list means the role is not category-scoped.
     *
     * @return list<Specialization>
     */
    public function specializations(): array
    {
        return match ($this) {
            self::Medical => [Specialization::Medical],
            self::FireDisaster => [Specialization::Fire, Specialization::Disaster],
            self::Weather => [Specialization::Weather, Specialization::Disaster],
            self::PeaceOrder => [Specialization::PeaceOrder],
            self::Tanod => [Specialization::PeaceOrder, Specialization::GeneralAssistance],
            self::GeneralAssistant => [Specialization::GeneralAssistance],
            self::Personnel => Specialization::cases(),
            default => [],
        };
    }

    /**
     * Incident categories this role is allowed to see when its request view is
     * specialization-scoped.
     *
     * @return list<string>
     */
    public function incidentCategories(): array
    {
        $categories = [];

        foreach ($this->specializations() as $specialization) {
            foreach ($specialization->incidentCategories() as $category) {
                $categories[$category] = true;
            }
        }

        return array_keys($categories);
    }

    /**
     * The abilities granted to this role.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        $responder = [
            Permission::RequestsViewSpecialization,
            Permission::RequestsUpdateStatus,
            Permission::PersonnelSelfService,
            Permission::MapView,
            Permission::HotspotsView,
            Permission::EvacuationCentersView,
            Permission::SosTrigger,
        ];

        return match ($this) {
            self::Admin => [
                Permission::RequestsViewAny,
                Permission::RequestsUpdateStatus,
                Permission::RequestsAssign,
                Permission::PersonnelManage,
                Permission::ReportsView,
                Permission::MapView,
                Permission::HotspotsView,
                Permission::EvacuationCentersView,
                Permission::EvacuationCentersManage,
                Permission::AccountsVerify,
                Permission::AuditView,
                Permission::SosTrigger,
            ],
            self::Official => [
                Permission::RequestsViewAny,
                Permission::RequestsUpdateStatus,
                Permission::RequestsAssign,
                Permission::PersonnelManage,
                Permission::ReportsView,
                Permission::MapView,
                Permission::HotspotsView,
                Permission::EvacuationCentersView,
                Permission::EvacuationCentersManage,
                Permission::AccountsVerify,
                Permission::SosTrigger,
            ],
            // A tanod is the only role whose duty status is geofenced.
            self::Tanod => [...$responder, Permission::TanodCheckIn],
            // The legacy generic responder covers every category but, as
            // before, its request list stays scoped to its own assignments.
            self::Personnel => $responder,
            self::Medical,
            self::FireDisaster,
            self::Weather,
            self::PeaceOrder,
            self::GeneralAssistant => $responder,
            self::Resident => [
                Permission::RequestsViewOwn,
                Permission::RequestsCreate,
                Permission::MapView,
                Permission::EvacuationCentersView,
                Permission::SosTrigger,
            ],
        };
    }

    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
