<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\Trainer;
use App\Services\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** Website settings, service pages and public team profiles. */
class WebsiteSetupController extends Controller
{
    public function settings(SiteSettings $settings): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);

        return response()->json(['data' => $settings->all()]);
    }

    public function updateSettings(Request $request, SiteSettings $settings): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);

        $data = $request->validate([
            'tagline' => ['nullable', 'string', 'max:150'],
            'hero_title' => ['nullable', 'string', 'max:200'],
            'hero_subtitle' => ['nullable', 'string', 'max:500'],
            'about_title' => ['nullable', 'string', 'max:150'],
            'about_body' => ['nullable', 'string', 'max:5000'],
            'mission' => ['nullable', 'string', 'max:1000'],
            'vision' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_hours' => ['nullable', 'string', 'max:150'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'map_embed_url' => ['nullable', 'url', 'max:1000', 'starts_with:https://www.google.com/maps/embed'],
            'stat_children' => ['nullable', 'string', 'max:20'],
            'stat_years' => ['nullable', 'string', 'max:20'],
        ], ['map_embed_url.starts_with' => 'Use the "Embed a map" link from Google Maps (starts with https://www.google.com/maps/embed).']);

        $settings->update($data);

        return response()->json(['data' => $settings->all()]);
    }

    public function services(): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);

        return response()->json(['data' => Service::orderBy('category')->orderBy('sort_order')->get()
            ->map(fn (Service $s) => [...$s->only(['id', 'category', 'name', 'name_bn', 'slug', 'short_description', 'description', 'default_duration_min', 'is_bookable_online', 'show_on_website', 'is_active', 'sort_order']), 'image_url' => $s->imageUrl()])]);
    }

    public function updateService(Request $request, Service $service): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);

        $service->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'default_duration_min' => ['nullable', 'integer', 'between:5,480'],
            'is_bookable_online' => ['boolean'],
            'show_on_website' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]));

        return response()->json(['message' => 'Service updated.']);
    }

    public function uploadServiceImage(Request $request, Service $service): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);

        if ($service->image_path) {
            Storage::disk('public')->delete($service->image_path);
        }
        $service->update(['image_path' => $request->file('image')->store('services', 'public')]);

        return response()->json(['image_url' => $service->imageUrl()]);
    }

    /** Public profiles of therapists and trainers (TRAINER ≠ THERAPIST: listed separately). */
    public function team(): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);

        $present = fn (string $kind) => fn ($p) => [
            'kind' => $kind, 'id' => $p->id, 'name' => $p->name, 'designation' => $p->designation ?? null,
            'qualification' => $p->qualification, 'experience_years' => $p->experience_years, 'bio' => $p->bio,
            'show_on_website' => $p->show_on_website, 'sort_order' => $p->sort_order, 'photo_url' => $p->photoUrl(), 'status' => $p->status,
        ];

        return response()->json(['data' => [
            'therapists' => Therapist::orderBy('sort_order')->orderBy('name')->get()->map($present('therapist')),
            'trainers' => Trainer::orderBy('sort_order')->orderBy('name')->get()->map($present('trainer')),
        ]]);
    }

    public function updateTeamMember(Request $request, string $kind, int $id): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);
        $person = $this->teamMember($kind, $id);

        $person->update($request->validate([
            'designation' => [$kind === 'therapist' ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'between:0,60'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'show_on_website' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]));

        return response()->json(['message' => 'Profile updated.']);
    }

    public function uploadTeamPhoto(Request $request, string $kind, int $id): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);
        $person = $this->teamMember($kind, $id);
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);

        if ($person->photo_path) {
            Storage::disk('public')->delete($person->photo_path);
        }
        $person->update(['photo_path' => $request->file('photo')->store('team', 'public')]);

        return response()->json(['photo_url' => $person->photoUrl()]);
    }

    private function teamMember(string $kind, int $id): Therapist|Trainer
    {
        return match ($kind) {
            'therapist' => Therapist::findOrFail($id),
            'trainer' => Trainer::findOrFail($id),
            default => abort(404),
        };
    }
}
