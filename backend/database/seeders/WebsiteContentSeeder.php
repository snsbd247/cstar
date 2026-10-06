<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Starting website text for each service: general, factual descriptions of the therapy,
 * no claims about outcomes, prices or staff. C-STAR edits them in the CMS.
 * Only fills empty fields, so CMS edits are never overwritten.
 */
class WebsiteContentSeeder extends Seeder
{
    private const CONTENT = [
        'speech-language-therapy' => [
            'Helps children understand and use language, speak clearly and communicate with others.',
            "Speech & language therapy supports children who are late to talk, hard to understand, or who find it difficult to follow and use language.\n\nAfter an assessment, the therapist sets goals such as first words, sentences, clear sounds, understanding instructions, conversation and social communication. Sessions use play, pictures and everyday routines, and parents receive activities to practise at home.\n\nFor children who do not yet speak, therapy may also introduce other ways to communicate, such as gestures or picture-based systems.",
        ],
        'occupational-therapy' => [
            'Builds the skills children need for everyday life — play, self-care, handwriting and sensory regulation.',
            "Occupational therapy (OT) helps children take part in daily activities: dressing, eating, playing, writing and joining in at school.\n\nThe therapist works on fine and gross motor skills, hand–eye coordination, attention and sensory processing — for example over- or under-reacting to sound, touch or movement. Goals are practical and agreed with the family.",
        ],
        'aba-therapy' => [
            'Structured, play-based teaching of communication, learning and social skills using Applied Behaviour Analysis.',
            "ABA (Applied Behaviour Analysis) therapy breaks skills into small steps and teaches them through structured practice and play, with positive reinforcement.\n\nIt is often used for children with autism to build communication, attention, play, self-help and social skills, and to understand and reduce behaviours that get in the way of learning. Progress is measured regularly and shared with parents.",
        ],
        'oral-placement-therapy' => [
            'Tactile and movement-based therapy for the jaw, lips and tongue to support speech clarity and feeding.',
            "Oral Placement Therapy (OPT) uses touch and guided movement to improve the strength and coordination of the jaw, lips and tongue.\n\nIt can help children whose speech is unclear because of oral-motor difficulties, and is often combined with speech therapy and feeding support.",
        ],
        'special-education' => [
            'Individual teaching that adapts early academic and learning skills to each child\'s way of learning.',
            "Special education supports children who need a different approach to learning — pre-academic skills, reading, writing, numbers, attention and classroom routines.\n\nThe special educator adapts materials and pace to the child, and helps prepare for school or inclusive classrooms.",
        ],
        'parent-guidance' => [
            'Sessions that help parents understand their child\'s needs and support progress at home.',
            "Parents are a child's most important teachers. Parent guidance sessions explain your child's assessment and goals, and show practical strategies for communication, behaviour, play and daily routines at home.",
        ],
        'assessment-evaluation' => [
            'A detailed look at communication, development, motor and daily-living skills — the first step for every child.',
            "Every child at C-STAR starts with an assessment. Depending on the concern this may include speech & language, developmental, occupational, feeding or autism-related assessment.\n\nThe specialist talks with the family, observes and assesses the child, and recommends a plan: therapy, regular training, or both. A written report is shared with the family.",
        ],
        'functional-training' => ['Practical skills for independence, practised every day in a structured class.', null],
        'daily-living-skills' => ['Eating, dressing, toileting and self-care routines taught step by step.', null],
        'motor-skills' => ['Gross and fine motor activities for balance, strength, coordination and hand skills.', null],
        'communication-skills' => ['Everyday communication practised with peers and trainers during class routines.', null],
        'social-skills' => ['Turn-taking, sharing, group play and following group routines.', null],
        'learning-skills' => ['Attention, matching, sorting and early learning activities in a supportive group.', null],
    ];

    public function run(): void
    {
        foreach (self::CONTENT as $slug => [$short, $long]) {
            $service = Service::where('slug', $slug)->first();
            if (! $service) {
                continue;
            }

            $service->update(array_filter([
                'short_description' => $service->short_description ?: $short,
                'description' => $service->description ?: $long,
            ]));
        }
    }
}
