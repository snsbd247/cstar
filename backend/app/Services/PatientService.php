<?php

namespace App\Services;

use App\Enums\PatientStatus;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\PatientClinicalProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PatientService
{
    public function __construct(
        private IdGenerator $ids,
        private TimelineService $timeline,
        private SystemSettings $settings,
    ) {}

    /**
     * Patient ID from Settings → Patient ID: PREFIX[-BRANCH][-YEAR]-NNNNN. With the branch code each branch counts on its own.
     * Codes are unique in the table; after a format change an old code could in theory repeat, so we skip ahead.
     */
    public function nextCode(int $branchId, bool $preview = false): string
    {
        $cfg = $this->settings->group('patient_id');
        $prefix = $cfg['prefix'];
        $key = 'patient';
        if ($cfg['include_branch_code'] === '1' && ($code = Branch::whereKey($branchId)->value('code'))) {
            $prefix .= '-'.strtoupper($code);
            $key .= ':'.$branchId;
        }
        $digits = (int) $cfg['digits'];
        $withYear = $cfg['include_year'] === '1';

        if ($preview) {
            $number = str_pad('1', $digits, '0', STR_PAD_LEFT);

            return $withYear ? $prefix.'-'.now()->year.'-'.$number : "{$prefix}-{$number}";
        }

        do {
            $code = $this->ids->next($key, $prefix, $digits, null, $withYear);
        } while (Patient::withTrashed()->where('patient_code', $code)->exists());

        return $code;
    }

    /**
     * Registers a child: patient + clinical profile + diagnoses + primary guardian + consents, in one transaction.
     * The guardian is either an existing one (siblings share guardians) or created here.
     */
    public function register(array $data, User $actor, ?UploadedFile $photo = null): Patient
    {
        return DB::transaction(function () use ($data, $actor, $photo) {
            $patient = Patient::create([
                ...Arr::except($data, ['clinical', 'diagnosis_ids', 'guardian', 'consents', 'photo']),
                'patient_code' => $this->nextCode((int) $data['home_branch_id']),
                'registration_date' => $data['registration_date'] ?? today(),
                'status' => PatientStatus::Active,
                'created_by' => $actor->id,
            ]);

            $this->saveClinical($patient, $data);

            if (! empty($data['guardian'])) {
                $this->attachGuardian($patient, $data['guardian'], primary: true);
            }

            foreach ($data['consents'] ?? [] as $type => $granted) {
                $patient->consents()->create([
                    'type' => $type,
                    'granted' => (bool) $granted,
                    'signed_on' => today(),
                    'guardian_id' => $patient->guardians()->wherePivot('is_primary', true)->value('guardians.id'),
                    'recorded_by' => $actor->id,
                ]);
            }

            if ($photo) {
                $this->storePhoto($patient, $photo);
            }

            $this->timeline->record($patient, 'patient.registered', 'Patient registered', $patient,
                "Registered at {$patient->homeBranch->name} as {$patient->patient_code}");

            return $patient;
        });
    }

    public function update(Patient $patient, array $data, bool $canWriteClinical, ?UploadedFile $photo = null): Patient
    {
        return DB::transaction(function () use ($patient, $data, $canWriteClinical, $photo) {
            $patient->update(Arr::except($data, ['clinical', 'diagnosis_ids', 'guardian', 'consents', 'photo']));

            if ($canWriteClinical) {
                $this->saveClinical($patient, $data);
            }

            if ($photo) {
                $this->storePhoto($patient, $photo);
            }

            return $patient;
        });
    }

    /**
     * @param  array{id?: int, name?: string, phone?: string, relationship: string, ...}  $input
     */
    public function attachGuardian(Patient $patient, array $input, bool $primary = false): Guardian
    {
        $guardian = isset($input['id'])
            ? Guardian::findOrFail($input['id'])
            : Guardian::create(Arr::only($input, ['name', 'phone', 'alt_phone', 'email', 'occupation', 'nid', 'address']));

        $primary = $primary || ! empty($input['is_primary']);
        if ($primary) {
            $patient->guardians()->newPivotStatement()->where('patient_id', $patient->id)->update(['is_primary' => false]);
        }

        $patient->guardians()->syncWithoutDetaching([$guardian->id => [
            'relationship' => $input['relationship'],
            'is_primary' => $primary,
            'is_emergency_contact' => (bool) ($input['is_emergency_contact'] ?? $primary),
            'can_access_portal' => (bool) ($input['can_access_portal'] ?? $primary),
        ]]);

        return $guardian;
    }

    public function storePhoto(Patient $patient, UploadedFile $photo): void
    {
        if ($patient->photo_path) {
            Storage::disk('local')->delete($patient->photo_path);
        }

        $path = $photo->storeAs("patients/{$patient->id}", 'photo-'.Str::random(8).'.'.$photo->extension(), 'local');
        $patient->update(['photo_path' => $path]);
    }

    /**
     * Possible duplicates: same family phone with the same birth date, or same name + birth date.
     * Returns minimal identifying fields only.
     */
    public function findDuplicates(?string $phone, ?string $dateOfBirth, ?string $name, ?int $exceptId = null)
    {
        if (! $dateOfBirth || (! $phone && ! $name)) {
            return collect();
        }

        return Patient::query()
            ->with('homeBranch:id,name')
            ->whereDate('date_of_birth', $dateOfBirth)
            ->where(fn ($q) => $q
                ->when($phone, fn ($q) => $q->orWhere('phone', $phone))
                ->when($name, fn ($q) => $q->orWhere('name', 'like', trim($name))))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->limit(5)
            ->get(['id', 'patient_code', 'name', 'date_of_birth', 'home_branch_id']);
    }

    private function saveClinical(Patient $patient, array $data): void
    {
        if (array_key_exists('clinical', $data)) {
            $patient->clinicalProfile()->updateOrCreate([], Arr::only($data['clinical'] ?? [], PatientClinicalProfile::FIELDS));
        }

        if (array_key_exists('diagnosis_ids', $data)) {
            $patient->diagnoses()->sync($data['diagnosis_ids'] ?? []);
        }
    }
}
