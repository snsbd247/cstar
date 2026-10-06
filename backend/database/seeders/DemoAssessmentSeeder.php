<?php

namespace Database\Seeders;

use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\AssessmentService;
use Illuminate\Database\Seeder;

/**
 * Local demo for Sprint 9: Ayan's speech assessment (final, shared, recommendations already enrolled)
 * and Sara's (final, with a pending Occupational Therapy recommendation for reception to act on).
 */
class DemoAssessmentSeeder extends Seeder
{
    public function run(AssessmentService $assessments): void
    {
        $imran = User::where('email', 'therapist@cstar.test')->first();
        $ayan = Patient::where('name', 'Ayan Rahman')->first();
        $sara = Patient::where('name', 'Sara Islam')->first();
        if (! $imran?->therapist || ! $ayan || ! $sara || $ayan->assessments()->exists()) {
            return;
        }
        auth()->setUser($imran);

        $speechType = AssessmentType::where('name', 'Speech & Language Assessment')->firstOrFail();
        $speech = Service::where('name', 'like', 'Speech%')->firstOrFail();
        $ot = Service::where('name', 'like', 'Occupational%')->firstOrFail();

        $ayanAssessment = $assessments->save($ayan, [
            'assessment_type_id' => $speechType->id,
            'date' => today()->subWeeks(3)->toDateString(),
            'chief_complaint' => 'Limited speech for age; uses single words and gestures. Parents concerned about communication with family.',
            'background' => 'Demo history: full-term birth, motor milestones on time, first words at 2 years. No hearing concerns reported.',
            'section_findings' => [
                'receptive_language' => 'Understands familiar one-step instructions with gesture support.',
                'expressive_language' => 'About 20 single words; no consistent two-word combinations yet.',
                'articulation' => 'Bilabial sounds emerging; final consonants often omitted.',
                'oral_motor' => 'Adequate lip closure; mild drooling when concentrating.',
                'social_communication' => 'Good eye contact with familiar adults; limited joint attention in play.',
            ],
            'summary' => 'Expressive language delay with emerging speech sounds. Good potential with regular therapy and home practice.',
            'recommendations' => 'Speech therapy twice a week plus Regular Training for daily routine and social skills. Review in 3 months.',
            'parent_summary' => 'আয়ান বুঝতে পারে অনেক কিছু, কিন্তু কথা বলায় একটু পিছিয়ে আছে। সপ্তাহে দুইবার স্পিচ থেরাপি এবং বাসায় প্রতিদিন অনুশীলন করলে ভালো উন্নতি আশা করা যায়।',
            'recommendation_items' => [
                ['enrollment_type' => 'therapy', 'service_id' => $speech->id, 'frequency' => '2 sessions / week', 'priority' => 'high'],
                ['enrollment_type' => 'training', 'frequency' => 'Daily (Sat–Thu)', 'priority' => 'normal'],
            ],
            'finalize' => true,
        ], $imran);
        $assessments->share($ayanAssessment->load(['patient', 'type']), true);

        // Ayan is already enrolled in both — link the recommendations to show the journey.
        $enrollments = Enrollment::where('patient_id', $ayan->id)->with('therapyEnrollment')->get();
        foreach ($ayanAssessment->recommendationItems as $item) {
            $match = $enrollments->first(fn ($e) => $e->type->value === $item->enrollment_type
                && ($item->enrollment_type === 'training' || $e->therapyEnrollment?->service_id === $item->service_id));
            if ($match) {
                $item->update(['enrollment_id' => $match->id]);
                $match->update(['source_assessment_id' => $ayanAssessment->id]);
            }
        }

        $assessments->save($sara, [
            'assessment_type_id' => $speechType->id,
            'date' => today()->subWeeks(1)->toDateString(),
            'chief_complaint' => 'Review after the first weeks of speech therapy.',
            'section_findings' => [
                'expressive_language' => 'Now combining two words in structured activities.',
                'articulation' => '/p/ /b/ /m/ clear at word level in about 60% of attempts.',
                'social_communication' => 'Avoids messy play and some textures — sensory sensitivity noted.',
            ],
            'summary' => 'Steady progress in speech. Sensory sensitivities are affecting play and feeding; OT input recommended.',
            'parent_summary' => 'সারার কথা বলায় উন্নতি হচ্ছে। কিছু জিনিস ছুঁতে বা খেতে অস্বস্তি দেখা যাচ্ছে, তাই সপ্তাহে একবার অকুপেশনাল থেরাপির পরামর্শ দেওয়া হলো।',
            'recommendation_items' => [
                ['enrollment_type' => 'therapy', 'service_id' => $ot->id, 'frequency' => '1 session / week', 'priority' => 'normal', 'note' => 'Sensory processing focus'],
            ],
            'finalize' => true,
        ], $imran);
    }
}
