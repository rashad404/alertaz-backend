<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AlertParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AlertParseController extends Controller
{
    protected $parserService;

    public function __construct(AlertParserService $parserService)
    {
        $this->parserService = $parserService;
    }

    /**
     * Parse natural language alert input
     *
     * POST /api/alerts/parse
     * Body: { "input": "Bitcoin $100k" }
     */
    public function parse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'input' => 'required|string|min:3|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $input = trim($request->input('input'));

            Log::info('[AlertParseController] Parsing input', ['input' => $input]);

            $result = $this->parserService->parse($input);

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('[AlertParseController] Parse failed', [
                'error' => $e->getMessage(),
                'input' => $request->input('input'),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
