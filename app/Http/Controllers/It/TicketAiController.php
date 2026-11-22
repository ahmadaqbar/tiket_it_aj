<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\TicketAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Endpoint JSON asisten AI di detail tiket (IT & IT Admin). Hanya mengembalikan saran; tidak mengubah data.
 */
class TicketAiController extends Controller
{
    public function __invoke(Request $request, Ticket $ticket, string $feature, TicketAiService $ai): JsonResponse
    {
        $this->authorize('manage', $ticket);
        $input = $request->validate([
            'style' => ['nullable', Rule::in(array_keys(TicketAiService::REPLY_STYLES))],
            'instruction' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $data = match ($feature) {
                'classify' => $ai->classify($ticket, $request->user()),
                'troubleshoot' => $ai->troubleshoot($ticket, $request->user()),
                'summary' => $ai->summarize($ticket, $request->user()),
                'reply' => $ai->draftReply($ticket, $request->user(), $input['style'] ?? 'professional', $input['instruction'] ?? null),
                default => abort(404),
            };
        } catch (AiUnavailableException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], $e->status);
        }

        return response()->json(['ok' => true, 'feature' => $feature, 'data' => $data]);
    }
}
