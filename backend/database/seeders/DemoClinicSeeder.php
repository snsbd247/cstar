<?php

namespace Database\Seeders;

use App\Enums\EnrollmentType;
use App\Enums\TherapistType;
use App\Models\Branch;
use App\Models\Diagnosis;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\Trainer;
use App\Models\TrainingGroup;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\PatientService;
use Illuminate\Database\Seeder;

/**
 * The plan's own example (Plan §৩): Ayan = training + speech + OT, Sara = speech only, Rafi = training only.
 * Local development only.
 */
class DemoClinicSeeder extends Seeder
{
    public function run(PatientService $patients, EnrollmentService $enrollments): void
    {
        if (Patient::exists()) {
            return;
        }

        $branch = Branch::where('code', 'HQ')->firstOrFail();
        $admin = User::where('email', 'admin@cstar.test')->firstOrFail();
        $speech = Service::where('slug', 'speech-language-therapy')->firstOrFail();
        $ot = Service::where('slug', 'occupational-therapy')->firstOrFail();

        $hasan = Trainer::create([
            'user_id' => User::where('email', 'trainer@cstar.test')->value('id'),
            'branch_id' => $branch->id, 'name' => 'Md. Hasan', 'slug' => 'md-hasan',
            'qualification' => 'B.Sc. in Physiotherapy', 'experience_years' => 6,
        ]);
        Trainer::create(['branch_id' => $branch->id, 'name' => 'Nasrin Akter', 'slug' => 'nasrin-akter', 'experience_years' => 3]);

        $imran = Therapist::create([
            'user_id' => User::where('email', 'therapist@cstar.test')->value('id'),
            'primary_branch_id' => $branch->id, 'name' => 'Imran Hossain', 'slug' => 'imran-hossain',
            'designation' => 'Senior Speech & Language Therapist', 'therapist_type' => TherapistType::SpeechLanguage,
            'experience_years' => 8,
        ]);
        $imran->services()->sync([$speech->id]);

        $farhana = Therapist::create([
            'primary_branch_id' => $branch->id, 'name' => 'Farhana Rahman', 'slug' => 'farhana-rahman',
            'designation' => 'Occupational Therapist', 'therapist_type' => TherapistType::Occupational,
            'experience_years' => 5,
        ]);
        $farhana->services()->sync([$ot->id]);

        $classA = TrainingGroup::create([
            'branch_id' => $branch->id, 'lead_trainer_id' => $hasan->id, 'code' => 'FDA',
            'name' => 'Functional Development A', 'max_students' => 12, 'start_date' => '2026-01-01',
        ]);

        auth()->setUser($admin); // timeline/audit actor
        $asd = Diagnosis::where('name', 'like', 'Autism%')->value('id');
        $delay = Diagnosis::where('name', 'Speech & Language Delay')->value('id');

        $ayan = $patients->register([
            'home_branch_id' => $branch->id, 'name' => 'Ayan Rahman', 'date_of_birth' => '2021-03-14', 'gender' => 'male',
            'father_name' => 'Karim Rahman', 'mother_name' => 'Nusrat Jahan', 'phone' => '01700000007',
            'diagnosis_ids' => [$asd], 'clinical' => ['developmental_history' => 'First words at 30 months.'],
            'guardian' => ['name' => 'Nusrat Jahan', 'phone' => '01700000007', 'relationship' => 'mother'],
            'consents' => ['treatment' => true, 'photo_media' => false],
        ], $admin);
        // The demo parent login (01700000007) belongs to Ayan's mother.
        $ayan->guardians()->first()->update(['user_id' => User::where('phone', '01700000007')->value('id')]);

        $sara = $patients->register([
            'home_branch_id' => $branch->id, 'name' => 'Sara Islam', 'date_of_birth' => '2020-08-02', 'gender' => 'female',
            'phone' => '01811000002', 'diagnosis_ids' => [$delay],
            'guardian' => ['name' => 'Mahmud Islam', 'phone' => '01811000002', 'relationship' => 'father'],
            'consents' => ['treatment' => true],
        ], $admin);

        $rafi = $patients->register([
            'home_branch_id' => $branch->id, 'name' => 'Rafi Ahmed', 'date_of_birth' => '2019-11-20', 'gender' => 'male',
            'phone' => '01911000003', 'diagnosis_ids' => [$asd],
            'guardian' => ['name' => 'Shirin Ahmed', 'phone' => '01911000003', 'relationship' => 'mother'],
            'consents' => ['treatment' => true],
        ], $admin);

        $training = fn () => ['type' => EnrollmentType::Training->value, 'branch_id' => $branch->id, 'training_group_id' => $classA->id, 'start_date' => '2026-09-01'];
        $therapy = fn (Service $s, Therapist $t) => ['type' => EnrollmentType::Therapy->value, 'branch_id' => $branch->id, 'service_id' => $s->id, 'therapist_id' => $t->id, 'sessions_per_week' => 2, 'start_date' => '2026-09-01'];

        $enrollments->create($ayan, $training(), $admin);
        $enrollments->create($ayan, $therapy($speech, $imran), $admin);
        $enrollments->create($ayan, $therapy($ot, $farhana), $admin);
        $enrollments->create($sara, $therapy($speech, $imran), $admin);
        $enrollments->create($rafi, $training(), $admin);
    }
}
