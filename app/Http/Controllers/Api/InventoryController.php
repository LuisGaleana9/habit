<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * GET /api/inventory
     *
     * List all resources in the authenticated player's inventory.
     */
    public function index(Request $request): JsonResponse
    {
        $inventory = $request->user()
            ->inventories()
            ->orderBy('resource_name')
            ->get(['resource_name', 'quantity']);

        return response()->json([
            'data' => $inventory,
        ]);
    }
}
