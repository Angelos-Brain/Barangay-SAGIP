<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Feature 3: Audit Log.
 *
 * Read-only. There is deliberately no store/update/destroy — entries are
 * written by the Auditable trait and the AuditLogger service, and the model
 * refuses to be modified or deleted.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize(Permission::AuditView->value);

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $entries = AuditLog::with('user')
            ->forAction($filters['action'] ?? null)
            ->forUser($filters['user_id'] ?? null)
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['q'] ?? null, function ($query, $term) {
                $query->where(function ($inner) use ($term) {
                    $inner->where('user_label', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('auditable_type', 'like', "%{$term}%");
                });
            })
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('audit.index', [
            'entries' => $entries,
            'filters' => $filters,
            'actions' => AuditLog::query()
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }
}
