<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sprint 5: public website content (CMS), online appointment requests and contact messages. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->longText('description')->nullable()->after('short_description');
            $table->string('image_path')->nullable()->after('description');
        });

        foreach (['trainers', 'therapists'] as $staff) {
            Schema::table($staff, function (Blueprint $table) {
                $table->string('photo_path')->nullable()->after('bio');
                $table->unsignedSmallInteger('sort_order')->default(0)->after('show_on_website');
            });
        }

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('relation')->nullable(); // "Mother of a 6-year-old"
            $table->text('content');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->string('category', 50)->nullable();
            $table->boolean('is_published')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image_path');
            $table->string('category', 50)->nullable();
            // A child may appear only with the guardian's photo/media consent.
            $table->boolean('consent_confirmed')->default(false);
            $table->boolean('is_published')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('body');
            $table->string('audience', 20)->default('all'); // all | parents | staff
            $table->boolean('show_on_website')->default(true);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Website leads. Not patients yet — reception verifies, then registers the child.
        Schema::create('appointment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique(); // REQ-2026-00001
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('preferred_therapist_id')->nullable()->constrained('therapists')->nullOnDelete();
            $table->string('parent_name');
            $table->string('child_name');
            $table->unsignedTinyInteger('child_age_years')->nullable();
            $table->string('phone', 20)->index();
            $table->string('email')->nullable();
            $table->date('preferred_date')->nullable();
            $table->string('preferred_time', 20)->nullable(); // morning | afternoon | evening
            $table->text('message')->nullable();
            $table->string('status', 20)->default('new')->index(); // new | contacted | converted | rejected | spam
            $table->text('internal_note')->nullable();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('source', 20)->default('website');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new')->index(); // new | read | replied | spam
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('appointment_requests');
        Schema::dropIfExists('notices');
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('testimonials');

        foreach (['trainers', 'therapists'] as $staff) {
            Schema::table($staff, fn (Blueprint $table) => $table->dropColumn(['photo_path', 'sort_order']));
        }

        Schema::table('services', fn (Blueprint $table) => $table->dropColumn(['description', 'image_path']));
    }
};
