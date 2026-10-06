<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Notice;
use App\Models\Testimonial;
use App\Models\Therapist;
use App\Models\Trainer;
use Illuminate\Database\Seeder;

/**
 * Local-only sample content so the website can be reviewed. Testimonials and FAQ answers here are
 * placeholders — real ones must come from C-STAR (testimonials need the family's permission).
 */
class DemoWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        Therapist::query()->update(['show_on_website' => true]);
        Therapist::where('slug', 'imran-hossain')->update([
            'qualification' => 'BSc & MSc in Speech and Language Therapy',
            'bio' => "Sample profile. Works with children with speech delay, autism and unclear speech, with a strong focus on involving parents in every session.",
        ]);
        Therapist::where('slug', 'farhana-rahman')->update([
            'qualification' => 'BSc in Occupational Therapy',
            'bio' => 'Sample profile. Supports children with fine motor, sensory and daily-living difficulties.',
        ]);
        Trainer::query()->update(['show_on_website' => true]);

        if (Testimonial::exists()) {
            return;
        }

        foreach ([
            ['Sample Parent A', 'Mother of a 5-year-old (sample)', 'Sample testimonial — replace with a real parent quote, with the family\'s permission, from Website CMS → Testimonials.'],
            ['Sample Parent B', 'Father of a 7-year-old (sample)', 'Sample testimonial. Real testimonials are only published after the family agrees.'],
            ['Sample Parent C', 'Mother of a 4-year-old (sample)', 'Sample testimonial used for layout review in local development.'],
        ] as $i => [$name, $relation, $content]) {
            Testimonial::create(['name' => $name, 'relation' => $relation, 'content' => $content, 'is_published' => true, 'sort_order' => $i]);
        }

        foreach ([
            ['How do I start?', 'Request an appointment online or call us. The first step is an assessment, after which the team suggests therapy, regular training or both. (Sample answer — please review.)', 'Getting started'],
            ['What is the difference between therapy and regular training?', 'Therapy is one-to-one sessions with a therapist at booked times. Regular training means attending the center on a fixed schedule in a small class with a trainer. A child can do both. (Sample answer — please review.)', 'Getting started'],
            ['Can parents join the sessions?', 'Parents are involved in every plan and receive home practice activities. (Sample answer — please review.)', 'Sessions'],
            ['How will I know about my child\'s progress?', 'Through regular reports and the parent portal, where you can see appointments, attendance, progress and bills. (Sample answer — please review.)', 'Sessions'],
        ] as $i => [$q, $a, $category]) {
            Faq::create(['question' => $q, 'answer' => $a, 'category' => $category, 'sort_order' => $i]);
        }

        Notice::create([
            'title' => 'Sample notice: center closed for public holiday',
            'slug' => 'sample-notice-holiday',
            'body' => 'This is a sample notice for local development. Notices are published from Website CMS → Notices.',
            'is_published' => true,
            'publish_at' => now(),
        ]);
    }
}
