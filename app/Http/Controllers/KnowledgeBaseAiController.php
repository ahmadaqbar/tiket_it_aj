<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiManager;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\KnowledgeBaseAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeBaseAiController extends Controller
{
    public function page(AiManager $ai): View
    {
        return view('kb.ask', ['aiUnavailable' => $ai->unavailableReason()]);
    }

    public function ask(Request $request, KnowledgeBaseAiService $ai): JsonResponse
    {
        $question = $request->validate(['question' => ['required', 'string', 'min:5', 'max:1000']])['question'];

        try {
            return response()->json(['ok' => true, 'data' => $ai->ask($question, $request->user())]);
        } catch (AiUnavailableException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], $e->status);
        }
    }
}
