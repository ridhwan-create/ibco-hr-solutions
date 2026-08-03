<?php

namespace App\Http\Controllers;

use App\Models\HrDocument;
use App\Models\HrDocumentAttachment;
use App\Models\HrDocumentNotification;
use App\Support\AuditLogger;
use App\Support\HrDocumentRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmployeeDocumentController extends Controller
{
    public function __construct(
        private readonly HrDocumentRenderer $renderer,
    ) {}

    public function index(Request $request): Response
    {
        $documents = HrDocument::query()
            ->issuedTo((int) $request->user()->getAuthIdentifier())
            ->with([
                'attachments' => fn ($query) => $query
                    ->where('visible_to_employee', true)
                    ->select([
                        'id', 'hr_document_id', 'attachment_type', 'original_name',
                        'mime_type', 'size', 'created_at',
                    ]),
            ])
            ->latest('issued_at')
            ->get()
            ->map(fn (HrDocument $document) => [
                ...$document->only([
                    'id', 'reference_number', 'template_name', 'category',
                    'subject', 'status', 'issued_at', 'effective_date',
                    'expiry_date', 'acknowledgement_required',
                    'acknowledged_at', 'confidentiality',
                ]),
                'rendered_subject' => $this->renderer->renderText(
                    $document->subject,
                    $document,
                ),
                'display_status' => $document->displayStatus(),
                'days_to_expiry' => $document->expiry_date
                    ? today()->diffInDays($document->expiry_date, false)
                    : null,
                'attachments' => $document->attachments,
            ]);
        $notifications = HrDocumentNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('EmployeeSelfService/Documents', [
            'documents' => $documents,
            'notifications' => $notifications->map(fn (HrDocumentNotification $notification) => [
                'id' => $notification->getKey(),
                'document_id' => $notification->hr_document_id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
            'unreadNotifications' => $notifications->whereNull('read_at')->count(),
            'statistics' => [
                'total' => $documents->count(),
                'acknowledgement_pending' => $documents
                    ->where('acknowledgement_required', true)
                    ->whereNull('acknowledged_at')
                    ->count(),
                'expiring' => $documents
                    ->filter(fn (array $document) => $document['days_to_expiry'] !== null
                        && $document['days_to_expiry'] >= 0
                        && $document['days_to_expiry'] <= 30)
                    ->count(),
                'expired' => $documents->where('display_status', 'expired')->count(),
            ],
        ]);
    }

    public function acknowledge(Request $request, HrDocument $document): RedirectResponse
    {
        $this->authorizeOwner($request, $document);
        if ($document->status !== 'issued') {
            throw ValidationException::withMessages([
                'status' => 'Dokumen ini tidak lagi menunggu perakuan.',
            ]);
        }
        if (! $document->acknowledgement_required) {
            throw ValidationException::withMessages([
                'status' => 'Dokumen ini tidak memerlukan perakuan pekerja.',
            ]);
        }
        $validated = $request->validate([
            'confirmed' => ['accepted'],
        ]);
        $document->update([
            'status' => 'acknowledged',
            'acknowledged_by' => $request->user()->getAuthIdentifier(),
            'acknowledged_at' => now(),
            'acknowledgement_ip' => $request->ip(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'hr_document.acknowledged',
            'hr_documents',
            $document->getKey(),
            oldValues: ['status' => 'issued', 'acknowledged_at' => null],
            newValues: [
                'status' => 'acknowledged',
                'acknowledged_at' => $document->acknowledged_at,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Penerimaan dokumen telah diperakui.',
        ]);
    }

    public function downloadPdf(Request $request, HrDocument $document): HttpResponse
    {
        $this->authorizeOwner($request, $document);
        $name = Str::slug($document->reference_number ?? "dokumen-{$document->id}")
            .'.pdf';

        return response($this->renderer->pdf($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function downloadAttachment(
        Request $request,
        HrDocument $document,
        HrDocumentAttachment $attachment,
    ): HttpResponse {
        $this->authorizeOwner($request, $document);
        abort_unless(
            $attachment->hr_document_id === $document->getKey()
                && $attachment->visible_to_employee,
            404,
        );

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            ['Cache-Control' => 'private, no-store'],
        );
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        HrDocumentNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    private function authorizeOwner(Request $request, HrDocument $document): void
    {
        abort_unless(
            $document->employee_user_id === $request->user()->getAuthIdentifier()
                && in_array($document->status, ['issued', 'acknowledged'], true),
            403,
        );
    }
}
