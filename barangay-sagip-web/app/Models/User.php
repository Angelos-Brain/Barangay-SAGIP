<?php

namespace App\Models;

use App\Enums\IncidentOutcome;
use App\Enums\Permission;
use App\Enums\PersonnelAccountStatus;
use App\Enums\Specialization;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Enums\VulnerabilityTag;
use App\Models\Concerns\Auditable;
use App\Rules\GmailAddress;
use App\Services\ProfilePhotoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use Auditable, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone_number',
        'verification_status',
        'verified_at',
        'verified_by',
        'verification_note',
        'account_setup_completed_at',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Audit noise: session bookkeeping, not a state change anyone reviews.
     *
     * @var list<string>
     */
    protected array $auditExcept = ['remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
            'account_setup_completed_at' => 'datetime',
            'phone_verified_at' => 'datetime',
        ];
    }

    /**
     * Saved trimmed and lowercased, with the inbox it delivers to kept
     * alongside in `email_canonical` for duplicate checks and sign-in.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null
                ? ['email' => null, 'email_canonical' => null]
                : ['email' => GmailAddress::normalize($value), 'email_canonical' => GmailAddress::canonical($value)],
        );
    }

    /**
     * Whether this account has clicked the emailed verification link.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function residentProfile()
    {
        return $this->hasOne(ResidentProfile::class);
    }

    public function responsePersonnel()
    {
        return $this->hasOne(ResponsePersonnel::class);
    }

    public function emergencyRequests()
    {
        return $this->hasMany(EmergencyRequest::class, 'resident_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isResident(): bool
    {
        return $this->role === UserRole::Resident;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * True for every field-responder role, including the six specialized ones
     * added in Feature 3 and the original generic `personnel` role.
     */
    public function isPersonnel(): bool
    {
        return $this->role?->isOperational() ?? false;
    }

    /**
     * A responder added by an official must finish First Login — open the
     * emailed verification link, then choose a password — before using the app.
     */
    public function needsAccountSetup(): bool
    {
        return $this->isPersonnel() && $this->personnelAccountStatus() !== PersonnelAccountStatus::Active;
    }

    public function personnelAccountStatus(): PersonnelAccountStatus
    {
        if (! $this->hasVerifiedEmail()) {
            return PersonnelAccountStatus::Unclaimed;
        }

        if (! $this->hasChosenPassword()) {
            return PersonnelAccountStatus::EmailVerified;
        }

        return PersonnelAccountStatus::Active;
    }

    /**
     * True once the responder has chosen their own password in First Login;
     * until then the account carries an unguessable placeholder hash.
     */
    public function hasChosenPassword(): bool
    {
        return $this->account_setup_completed_at !== null;
    }

    /**
     * The First Login step a signed-in responder should resume at. Only a
     * verified email signs a responder in, so the email step is never due here.
     */
    public function nextAccountSetupRoute(): string
    {
        return $this->hasChosenPassword() ? 'account.setup.ready' : 'account.setup.password';
    }

    public function isOfficial(): bool
    {
        return $this->role === UserRole::Official;
    }

    public function isTanod(): bool
    {
        return $this->role === UserRole::Tanod;
    }

    /**
     * Officials and admins share the barangay-wide management views.
     */
    public function isOfficialOrAdmin(): bool
    {
        return $this->isOfficial() || $this->isAdmin();
    }

    public function isStaff(): bool
    {
        return $this->role?->isStaff() ?? false;
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->role?->hasPermission($permission) ?? false;
    }

    /**
     * The specializations this user responds to: whatever tags they picked on
     * their responder record, falling back to the ones implied by their role.
     *
     * @return list<Specialization>
     */
    public function specializations(): array
    {
        $tags = $this->responsePersonnel?->specializationEnums() ?? [];

        return $tags !== [] ? $tags : ($this->role?->specializations() ?? []);
    }

    /**
     * Incident categories this user is responsible for (Feature 8 routing and
     * the specialization-scoped request views).
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
     * Feature 1: only a verified account may use the emergency features. Staff
     * accounts are provisioned by the barangay itself, so they are never held
     * behind resident verification.
     */
    public function isVerified(): bool
    {
        return $this->isStaff() || $this->verification_status === VerificationStatus::Verified;
    }

    public function isPendingVerification(): bool
    {
        return ! $this->isStaff() && $this->verification_status === VerificationStatus::Pending;
    }

    public function isRejected(): bool
    {
        return ! $this->isStaff() && $this->verification_status === VerificationStatus::Rejected;
    }

    /**
     * Feature 2: how many of this user's incidents a responder or official
     * closed as a false alarm. Derived from outcome history, never stored.
     */
    public function falseAlarmCount(): int
    {
        return $this->emergencyRequests()
            ->where('outcome', IncidentOutcome::FalseAlarm->value)
            ->count();
    }

    /**
     * Feature 2: flagged for admin review — never suspended automatically.
     */
    public function isFlaggedForFalseAlarms(): bool
    {
        return $this->falseAlarmCount() >= self::falseAlarmFlagThreshold();
    }

    public static function falseAlarmFlagThreshold(): int
    {
        return (int) config('sagip.sos.false_alarm_flag_threshold', 3);
    }

    /**
     * Feature 2: accounts at or over the false-alarm threshold, each carrying a
     * `false_alarm_count` attribute.
     */
    public function scopeFlaggedForFalseAlarms(Builder $query): Builder
    {
        $falseAlarms = fn (Builder $incidents) => $incidents->where('outcome', IncidentOutcome::FalseAlarm->value);

        return $query
            ->whereHas('emergencyRequests', $falseAlarms, '>=', self::falseAlarmFlagThreshold())
            ->withCount(['emergencyRequests as false_alarm_count' => $falseAlarms]);
    }

    /**
     * Feature 1: the household vulnerability markers on this user's profile.
     *
     * @return list<VulnerabilityTag>
     */
    public function vulnerabilityTags(): array
    {
        return $this->residentProfile?->vulnerabilityTagEnums() ?? [];
    }

    public function profilePhotoUrl(): ?string
    {
        return $this->profile_photo_path
            ? Storage::disk(ProfilePhotoService::DISK)->url($this->profile_photo_path)
            : null;
    }
}
