<?php

namespace Database\Seeders;

use App\Enums\Specialization;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Enums\VulnerabilityTag;
use App\Models\EvacuationCenter;
use App\Models\ResponsePersonnel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds one demo account per role plus dynamically-generated response
     * personnel records based on the accepted specializations, rather than
     * relying on a fixed hardcoded list.
     */
    public function run(): void
    {
        $official = User::firstOrCreate(
            ['email' => 'official@sagip.test'],
            [
                'name' => 'Barangay Captain',
                'password' => Hash::make('password'),
                'role' => UserRole::Official,
                'phone_number' => '09170000001',
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
            ]
        );

        $personnelUser = User::firstOrCreate(
            ['email' => 'personnel@sagip.test'],
            [
                'name' => 'Barangay Response Personnel',
                'password' => Hash::make('password'),
                'role' => UserRole::Personnel,
                'phone_number' => '09170000002',
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
                'account_setup_completed_at' => now(),
                'phone_verified_at' => now(),
            ]
        );
        $personnelUser->email_verified_at ??= now();
        $personnelUser->save();

        $resident = User::firstOrCreate(
            ['email' => 'resident@sagip.test'],
            [
                'name' => 'Maria Santos',
                'password' => Hash::make('password'),
                'role' => UserRole::Resident,
                'phone_number' => '09170000003',
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
                'verified_by' => $official->id,
            ]
        );

        $resident->residentProfile()->firstOrCreate(
            ['user_id' => $resident->id],
            [
                'full_name' => 'Maria Santos',
                'address' => '225, Provincial Road, Calatagan Tibang, Virac, Catanduanes',
                'purok_sitio' => 'Purok 2',
                'household_members_count' => 4,
                'emergency_contact_name' => 'Jose Santos',
                'emergency_contact_number' => '09170000004',
                'vulnerability_tags' => [VulnerabilityTag::Elderly->value, VulnerabilityTag::Infant->value],
            ]
        );

        // Feature 1: an account still in the verification queue, so the review
        // screen and the emergency-feature gate both have something to act on.
        $pendingResident = User::firstOrCreate(
            ['email' => 'pending@sagip.test'],
            [
                'name' => 'Pedro Unverified',
                'password' => Hash::make('password'),
                'role' => UserRole::Resident,
                'phone_number' => '09170000005',
                'verification_status' => VerificationStatus::Pending,
            ]
        );

        $pendingResident->residentProfile()->firstOrCreate(
            ['user_id' => $pendingResident->id],
            [
                'full_name' => 'Pedro Unverified',
                'address' => '14, Rizal Street, Calatagan Tibang, Virac, Catanduanes',
                'household_members_count' => 2,
                'vulnerability_tags' => [VulnerabilityTag::Pwd->value],
            ]
        );

        $this->seedDynamicPersonnel($personnelUser->id);
        $this->seedSpecializedStaff();
        $this->seedEvacuationCenters();
    }

    /**
     * Feature 4: a small roster covering each operational status so the map and
     * the occupancy meters have something meaningful to render.
     */
    protected function seedEvacuationCenters(): void
    {
        $centers = [
            ['Virac Central Elementary School', 'Rizal Street, Virac, Catanduanes', 13.5822, 124.2311, 300, 84, 'open', 'Principal Reyes', '09170002001'],
            ['Calatagan Tibang Barangay Gymnasium', 'Provincial Road, Calatagan Tibang, Virac', 13.5931, 124.2062, 150, 150, 'full', 'Kagawad Olivar', '09170002002'],
            ['San Isidro Covered Court', 'San Isidro, Virac, Catanduanes', 13.5988, 124.1975, 120, 0, 'standby', 'Kagawad Tanyag', '09170002003'],
        ];

        foreach ($centers as [$name, $address, $latitude, $longitude, $capacity, $occupancy, $status, $contact, $number]) {
            EvacuationCenter::updateOrCreate(
                ['name' => $name],
                [
                    'address' => $address,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'capacity' => $capacity,
                    'current_occupancy' => $occupancy,
                    'status' => $status,
                    'contact_person' => $contact,
                    'contact_number' => $number,
                ]
            );
        }
    }

    /**
     * Feature 3 & 10: one demo login per role, each responder account tagged
     * with the specializations its role is responsible for.
     */
    protected function seedSpecializedStaff(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@sagip.test'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'phone_number' => '09170000010',
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
            ]
        );

        $responderRoles = [
            [UserRole::Tanod, 'Tanod Ruben Alcantara'],
            [UserRole::Medical, 'Medic Liza Bermudo'],
            [UserRole::FireDisaster, 'Fire Marshal Danny Tabuzo'],
            [UserRole::Weather, 'Weather Watch Elena Rubio'],
            [UserRole::PeaceOrder, 'Peacekeeper Nestor Vargas'],
            [UserRole::GeneralAssistant, 'Assistant Gina Molina'],
        ];

        $offset = 0;

        foreach ($responderRoles as [$role, $name]) {
            $offset++;

            $user = User::firstOrCreate(
                ['email' => $role->value.'@sagip.test'],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'role' => $role,
                    'phone_number' => '091700001'.str_pad((string) $offset, 2, '0', STR_PAD_LEFT),
                    'verification_status' => VerificationStatus::Verified,
                    'verified_at' => now(),
                    'account_setup_completed_at' => now(),
                    'phone_verified_at' => now(),
                ]
            );
            $user->email_verified_at ??= now();
            $user->save();

            $tags = array_map(
                fn (Specialization $specialization) => $specialization->value,
                $role->specializations(),
            );

            ResponsePersonnel::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $name,
                    'specializations' => $tags,
                    'specialization' => $tags[0],
                    'phone_number' => $user->phone_number,
                    'latitude' => round(13.5920 + ($offset * 0.0011), 7),
                    'longitude' => round(124.2050 - ($offset * 0.0009), 7),
                    'is_available' => true,
                    'current_workload' => 0,
                    'last_location_update' => now(),
                ]
            );
        }
    }

    protected function seedDynamicPersonnel(int $personnelUserId): void
    {
        $baseLatitude = 13.5920;
        $baseLongitude = 124.2050;

        foreach (ResponsePersonnel::specializations() as $specializationIndex => $specialization) {
            $personnelCount = $specialization === 'medical' ? 2 : 1;

            for ($offset = 0; $offset < $personnelCount; $offset++) {
                $name = sprintf(
                    '%s %s',
                    ucfirst(str_replace('_', ' ', $specialization)),
                    $offset + 1
                );

                $latitude = round(
                    $baseLatitude + ($specializationIndex * 0.0018) + ($offset * 0.0014),
                    7
                );
                $longitude = round(
                    $baseLongitude + (($specializationIndex % 2 === 0 ? 1 : -1) * ($offset * 0.0025 + $specializationIndex * 0.0012)),
                    7
                );

                ResponsePersonnel::updateOrCreate(
                    [
                        'user_id' => $specialization === 'medical' && $offset === 0 ? $personnelUserId : null,
                        'name' => $name,
                        'specialization' => $specialization,
                    ],
                    [
                        'phone_number' => '0917'.str_pad((string) (($specializationIndex + 1) * 100000 + $offset + 1), 7, '0', STR_PAD_LEFT),
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'is_available' => true,
                        'current_workload' => 0,
                        'last_location_update' => now(),
                    ]
                );
            }
        }
    }
}
