<?php

namespace App\Http\Controllers\Api;

use App\Data\TagData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->tags()->orderBy('name')->get()->map(TagData::fromModel(...))->all()
        );
    }
}
