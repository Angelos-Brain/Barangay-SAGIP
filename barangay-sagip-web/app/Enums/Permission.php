<?php

namespace App\Enums;

/**
 * Feature 3: Role-Based Access Control.
 *
 * Every ability the application gates on. Each case is registered as a Laravel
 * Gate ability in AppServiceProvider from the role map in UserRole::permissions(),
 * so authorization always has exactly one source of truth.
 */
enum Permission: string
{
    /** See every emergency request in the barangay, regardless of category. */
    case RequestsViewAny = 'requests.viewAny';

    /** See only the requests matching the responder's own specialization. */
    case RequestsViewSpecialization = 'requests.viewSpecialization';

    /** See only requests the user filed themselves. */
    case RequestsViewOwn = 'requests.viewOwn';

    case RequestsCreate = 'requests.create';
    case RequestsUpdateStatus = 'requests.updateStatus';
    case RequestsAssign = 'requests.assign';

    case PersonnelManage = 'personnel.manage';

    /** Update one's own responder record (location, availability, tags). */
    case PersonnelSelfService = 'personnel.selfService';

    case ReportsView = 'reports.view';
    case MapView = 'map.view';
    case HotspotsView = 'hotspots.view';

    case EvacuationCentersView = 'evacuationCenters.view';
    case EvacuationCentersManage = 'evacuationCenters.manage';

    /** Approve or reject pending resident accounts. */
    case AccountsVerify = 'accounts.verify';

    /** Read the append-only audit trail. */
    case AuditView = 'audit.view';

    /** Go on duty, which is geofenced to the barangay hall. */
    case TanodCheckIn = 'tanod.checkIn';

    case SosTrigger = 'sos.trigger';

    public function label(): string
    {
        return match ($this) {
            self::RequestsViewAny => 'View all emergency requests',
            self::RequestsViewSpecialization => 'View requests in own specialization',
            self::RequestsViewOwn => 'View own requests',
            self::RequestsCreate => 'File an emergency request',
            self::RequestsUpdateStatus => 'Update request status',
            self::RequestsAssign => 'Assign responders',
            self::PersonnelManage => 'Manage response personnel',
            self::PersonnelSelfService => 'Update own responder record',
            self::ReportsView => 'View and export reports',
            self::MapView => 'View the response map',
            self::HotspotsView => 'View incident hotspots',
            self::EvacuationCentersView => 'View evacuation centers',
            self::EvacuationCentersManage => 'Manage evacuation centers',
            self::AccountsVerify => 'Verify resident accounts',
            self::AuditView => 'View the audit log',
            self::TanodCheckIn => 'Check in for tanod duty',
            self::SosTrigger => 'Trigger an SOS',
        };
    }
}
