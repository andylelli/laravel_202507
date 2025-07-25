<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use ZipArchive;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TileController extends Controller
{
    public function downloadTiles(Request $request)
    {

        $request->validate([
            'nw.lat' => 'required|numeric',
            'nw.lon' => 'required|numeric',
            'se.lat' => 'required|numeric',
            'se.lon' => 'required|numeric',
            'pindrop_id' => 'required|integer|exists:pindrop,pindrop_id',
        ]);

        $nw = $request->input('nw');
        $se = $request->input('se');
        $pindropId = $request->input('pindrop_id');

        $tiles = $this->getTilesInBoundingBox($nw, $se, 15, 17);

        foreach ($tiles as $tile) {
            $url = "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{$tile['z']}/{$tile['y']}/{$tile['x']}";
            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                $relativePath = "tiles/{$pindropId}/{$tile['z']}/{$tile['y']}";
                $filename = "{$tile['x']}.jpg";
                $fullPath = "{$relativePath}/{$filename}";

                Storage::disk('local')->makeDirectory($relativePath);
                Storage::disk('local')->put($fullPath, $response->body());
            }
        }

        $zipPath = $this->zipTiles($pindropId);

        // Delete the original tile folder
        File::deleteDirectory(storage_path("app/tiles/{$pindropId}"));

        // Save entry to `tile` table
        DB::table('tile')->insert([
            'tile_filename' => basename($zipPath),
            'tile_size' => filesize($zipPath),
            'tile_pindropid' => $pindropId,
            'tile_uxtime' => time()
        ]);

        return response()->json([
            'message' => 'Tiles downloaded, zipped, stored, and cleaned up.',
            'zip_filename' => basename($zipPath),
            'zip_full_path' => $zipPath
        ]);
    }

    public function downloadTileZip($pindropId)
    {
        $zipPath = storage_path("app/tiles/{$pindropId}.zip");

        if (!file_exists($zipPath)) {
            return response()->json(['error' => 'ZIP file not found'], 404);
        }

        return response()->download($zipPath, "tiles_{$pindropId}.zip");
    }

    private function zipTiles($pindropId)
    {
        $tileFolder = storage_path("app/tiles/{$pindropId}");
        $zipFile = storage_path("app/tiles/{$pindropId}.zip");

        if (!file_exists($tileFolder)) {
            throw new \Exception("Tile folder not found: $tileFolder");
        }

        if (file_exists($zipFile)) {
            unlink($zipFile);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tileFolder),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($tileFolder) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }

            $zip->close();
        } else {
            throw new \Exception("Failed to create ZIP file.");
        }

        return $zipFile;
    }

    private function lonLatToTile($lon, $lat, $zoom)
    {
        $xtile = floor((($lon + 180) / 360) * pow(2, $zoom));
        $ytile = floor((1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / pi()) / 2 * pow(2, $zoom));
        return ['x' => $xtile, 'y' => $ytile, 'z' => $zoom];
    }

    private function getTilesInBoundingBox($nw, $se, $minZoom, $maxZoom)
    {
        $tiles = [];

        for ($z = $minZoom; $z <= $maxZoom; $z++) {
            $nwTile = $this->lonLatToTile($nw['lon'], $nw['lat'], $z);
            $seTile = $this->lonLatToTile($se['lon'], $se['lat'], $z);

            $xStart = min($nwTile['x'], $seTile['x']);
            $xEnd   = max($nwTile['x'], $seTile['x']);
            $yStart = min($nwTile['y'], $seTile['y']);
            $yEnd   = max($nwTile['y'], $seTile['y']);

            for ($x = $xStart; $x <= $xEnd; $x++) {
                for ($y = $yStart; $y <= $yEnd; $y++) {
                    $tiles[] = [
                        'x' => $x,
                        'y' => $y,
                        'z' => $z
                    ];
                }
            }
        }

        return $tiles;
    }
}

