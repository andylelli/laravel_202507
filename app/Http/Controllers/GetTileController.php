<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

class GetTileController extends Controller
{
    public function getTilesForPindrop($pindropId)
    {
        $zipPath = storage_path("app/tiles/{$pindropId}.zip");

        if (!file_exists($zipPath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tile ZIP file not found for pindrop_id: ' . $pindropId
            ], 404);
        }

        return response()->download($zipPath, "tiles_{$pindropId}.zip", [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
