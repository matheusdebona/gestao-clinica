<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DocumentResource;
use App\Models\Budget;
use App\Models\Document;
use App\Services\BudgetPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly BudgetPdfService $budgetPdfService,
    ) {}

    public function generateBudgetPdf(Request $request, Budget $budget): JsonResponse
    {
        $document = $this->budgetPdfService->generate($budget, $request->user());

        return (new DocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Document::query()->latest('id');

        if ($request->filled('budget_id')) {
            $query->where('budget_id', (int) $request->query('budget_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', (int) $request->query('client_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->query('type'));
        }

        return DocumentResource::collection($query->paginate(20));
    }

    public function show(Document $document): DocumentResource
    {
        return new DocumentResource($document);
    }

    public function download(Document $document): StreamedResponse|Response
    {
        if (! Storage::disk('s3')->exists($document->storage_path)) {
            abort(404, 'Document file not found in storage.');
        }

        return Storage::disk('s3')->download(
            $document->storage_path,
            $document->filename,
            ['Content-Type' => $document->mime_type]
        );
    }

    public function destroy(Document $document): JsonResponse
    {
        if (Storage::disk('s3')->exists($document->storage_path)) {
            Storage::disk('s3')->delete($document->storage_path);
        }

        $document->delete();

        return response()->json([
            'message' => 'Document deleted.',
        ]);
    }
}
