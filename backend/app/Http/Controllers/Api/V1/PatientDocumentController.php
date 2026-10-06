<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientDocumentResource;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Services\AuditLogger;
use App\Services\TimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Documents live on the private disk (storage/app/private) and are only served through download(). */
class PatientDocumentController extends Controller
{
    public function index(Request $request, Patient $patient): AnonymousResourceCollection
    {
        Gate::authorize('view', $patient);

        $documents = $patient->documents()->with('uploader')->latest()
            ->when(! $request->user()->can('viewClinical', $patient), fn ($q) => $q->whereNotIn('category', PatientDocument::CLINICAL_CATEGORIES))
            ->get();

        return PatientDocumentResource::collection($documents);
    }

    public function store(Request $request, Patient $patient, TimelineService $timeline): JsonResponse
    {
        Gate::authorize('update', $patient);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:10240'],
            'category' => ['required', Rule::in(PatientDocument::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'visible_to_parent' => ['sometimes', 'boolean'],
        ]);

        $file = $request->file('file');
        $document = $patient->documents()->create([
            'category' => $data['category'],
            'title' => $data['title'],
            'path' => $file->storeAs("patients/{$patient->id}/documents", Str::uuid().'.'.$file->extension(), 'local'),
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'visible_to_parent' => $request->boolean('visible_to_parent'),
            'uploaded_by' => $request->user()->id,
        ]);

        $timeline->record($patient, 'document.uploaded', "Document uploaded: {$document->title}", $document);

        return (new PatientDocumentResource($document->load('uploader')))->response()->setStatusCode(201);
    }

    public function download(Request $request, PatientDocument $document): StreamedResponse
    {
        Gate::authorize('viewDocument', [$document->patient, $document]);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        AuditLogger::log('viewed', $document);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(PatientDocument $document): JsonResponse
    {
        Gate::authorize('update', $document->patient);

        $document->delete(); // soft delete; the file is kept for the record

        return response()->json(null, 204);
    }
}
