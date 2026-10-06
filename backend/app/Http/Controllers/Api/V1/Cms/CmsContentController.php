<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\GalleryItem;
use App\Models\Notice;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * One CRUD for the simple website content types: /cms/{type} with type = testimonials | faqs | notices | gallery.
 */
class CmsContentController extends Controller
{
    private const TYPES = [
        'testimonials' => Testimonial::class,
        'faqs' => Faq::class,
        'notices' => Notice::class,
        'gallery' => GalleryItem::class,
    ];

    public function index(string $type): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);

        $items = $this->model($type)::query()
            ->when($type === 'notices', fn ($q) => $q->latest('id'), fn ($q) => $q->orderBy('sort_order')->latest('id'))
            ->get()
            ->map(fn (Model $m) => $this->present($m));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);
        $class = $this->model($type);

        $data = $this->validated($request, $type, creating: true);
        $item = $class::create($data);

        return response()->json(['data' => $this->present($item)], 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);
        $item = $this->model($type)::findOrFail($id);

        $item->update($this->validated($request, $type, creating: false, item: $item));

        return response()->json(['data' => $this->present($item)]);
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        Gate::authorize(Permission::CMS_MANAGE);
        $item = $this->model($type)::findOrFail($id);

        if ($item instanceof GalleryItem) {
            Storage::disk('public')->delete($item->image_path);
        }
        $item->delete();

        return response()->json(null, 204);
    }

    /** @return class-string<Model> */
    private function model(string $type): string
    {
        return self::TYPES[$type] ?? abort(404);
    }

    private function validated(Request $request, string $type, bool $creating, ?Model $item = null): array
    {
        $common = ['sort_order' => ['nullable', 'integer', 'min:0']];

        $rules = match ($type) {
            'testimonials' => [
                'name' => ['required', 'string', 'max:255'],
                'relation' => ['nullable', 'string', 'max:255'],
                'content' => ['required', 'string', 'max:2000'],
                'rating' => ['nullable', 'integer', 'between:1,5'],
                'is_published' => ['boolean'],
            ],
            'faqs' => [
                'question' => ['required', 'string', 'max:255'],
                'answer' => ['required', 'string', 'max:5000'],
                'category' => ['nullable', 'string', 'max:50'],
                'is_published' => ['boolean'],
            ],
            'notices' => [
                'title' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:10000'],
                'audience' => ['required', Rule::in(['all', 'parents', 'staff'])],
                'show_on_website' => ['boolean'],
                'is_published' => ['boolean'],
                'publish_at' => ['nullable', 'date'],
                'expires_at' => ['nullable', 'date', 'after:publish_at'],
            ],
            'gallery' => [
                'title' => ['required', 'string', 'max:255'],
                'category' => ['nullable', 'string', 'max:50'],
                'image' => [$creating ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'consent_confirmed' => ['boolean'],
                'is_published' => ['boolean'],
            ],
        };

        $data = $request->validate([...$rules, ...$common], [
            'image.required' => 'Choose a photo to upload.',
        ]);
        unset($data['image']);

        if ($type === 'notices' && $creating) {
            $data['slug'] = Str::slug($data['title']).'-'.Str::lower(Str::random(5));
            $data['created_by'] = $request->user()->id;
        }

        if ($type === 'gallery') {
            // A photo can only go public with the guardian's photo/media consent (Plan §২৬).
            $published = $data['is_published'] ?? $item?->is_published;
            $consent = $data['consent_confirmed'] ?? $item?->consent_confirmed;
            if ($published && ! $consent) {
                throw ValidationException::withMessages([
                    'consent_confirmed' => 'Confirm that the families in this photo gave photo/media consent before publishing.',
                ]);
            }

            if ($request->hasFile('image')) {
                if ($item?->image_path) {
                    Storage::disk('public')->delete($item->image_path);
                }
                $data['image_path'] = $request->file('image')->store('gallery', 'public');
            }
        }

        return $data;
    }

    private function present(Model $item): array
    {
        $data = $item->toArray();
        if ($item instanceof GalleryItem) {
            $data['image_url'] = $item->imageUrl();
        }

        return $data;
    }
}
