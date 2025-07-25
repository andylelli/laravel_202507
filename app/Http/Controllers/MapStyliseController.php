<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Facades\Image;
use ZipArchive;

class MapStyliseController extends Controller
{
    private $tileSize = 512;
    private $overlap = 64;
    private $stride;

    public function __construct()
    {
        $this->stride = $this->tileSize - $this->overlap;
    }

    public function handleStored(Request $request)
    {
        $request->validate([
            'zip_path' => 'required|string'
        ]);

        //$skipStylise = $request->input('skip_stylise', true);
        $addStyleImage = true;
    
        // File paths
        $zipPath                = storage_path('app/tiles/' . $request->zip_path);
        $extractPath            = storage_path('app/tiles_raw');
        $stitchedRawPath        = storage_path('app/stitched_map_raw.png');
        $centeredPaddedPath     = storage_path('app/stitched_map_centered_padded.png');
        $readyForChopPath       = storage_path('app/stitched_map_ready_for_chop.png');
        $finalReassembledPath   = storage_path('app/stitched_map_stylised_reassembled.png');
        $finalStylisedPath      = storage_path('app/stitched_map_stylised_cropped.png');
        $panelDir               = storage_path('app/panels');
        $stylisedDir            = storage_path('app/stylised_panels');
        $outputTilesDir         = storage_path('app/tiles_output');
        $styledZip              = storage_path('app/stylised_' . $request->zip_path);
    
        // Clean previous
        File::deleteDirectory($extractPath);
        File::deleteDirectory($panelDir);
        File::deleteDirectory($stylisedDir);
        File::deleteDirectory($outputTilesDir);
        File::delete($stitchedRawPath);
        File::delete($centeredPaddedPath);
        File::delete($readyForChopPath);
        File::delete($finalReassembledPath);
        File::delete($finalStylisedPath);
        File::delete($styledZip);
    
        if (!File::exists($zipPath)) {
            return response()->json(['error' => "Zip not found at $zipPath"], 404);
        }
    
        // Unzip tiles
        $zip = new ZipArchive;
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            return response()->json(['error' => 'Could not unzip file.'], 500);
        }
    
        // Pipeline
        $originalSize = $this->stitchTiles($extractPath, $stitchedRawPath);
    
        $this->padImageToCenteredStride($stitchedRawPath, $centeredPaddedPath, $originalSize);
    
        $this->padImageWithOverlapBorder($centeredPaddedPath, $readyForChopPath, $this->overlap);
    
        $gridSize = $this->chopPanelsUniform($readyForChopPath, $panelDir);
    
        Log::info("Computed grid size", [
            'cols' => $gridSize['cols'],
            'rows' => $gridSize['rows']
        ]);
    
        if ($addStyleImage) {
            Log::info('Starting stylisation with Style Image Control API', [
                'input_directory' => $panelDir,
                'output_directory' => $stylisedDir,
            ]);
            $this->stylisePanels_SI_Control2($panelDir, $stylisedDir);
            Log::info('Completed stylisation with Style Image Control API');
        } else {
            Log::info('Starting stylisation with Stable Diffusion v1.6 API', [
                'input_directory' => $panelDir,
                'output_directory' => $stylisedDir,
            ]);
            $this->stylisePanels_SDv16($panelDir, $stylisedDir);
            Log::info('Completed stylisation with Stable Diffusion v1.6 API');
        }       

    
        $this->reassembleMap($stylisedDir, $finalReassembledPath, $gridSize['cols'], $gridSize['rows']);
    
        $this->cropFinalStylisedMap($finalReassembledPath, $finalStylisedPath, $originalSize);
    
        // NEW: slice into 256x256 slippy-map tiles
        $this->sliceTiles($finalStylisedPath, $originalSize, $outputTilesDir);
    
        // NEW: zip those tiles
        $this->zipTiles($outputTilesDir, $styledZip);
    
        return response()->json([
            'message' => 'Stylisation pipeline complete',
            'originalSize' => $originalSize,
            'gridSize' => $gridSize,
            'finalStylised' => $finalStylisedPath,
            'styledZip' => $styledZip
        ]);
    }
    

    private function stitchTiles($dir, $output)
    {
        $tiles = collect(File::allFiles($dir))
            ->filter(fn($file) => preg_match('/(\d+)\/(\d+)\/(\d+)\.(png|jpg)$/', $file))
            ->values();
    
        if ($tiles->isEmpty()) throw new \Exception("No tiles found!");
    
        $parsed = $tiles->map(function ($file) {
            preg_match('/(\d+)\/(\d+)\/(\d+)\.(png|jpg)$/', $file, $m);
            return ['z' => (int)$m[1], 'x' => (int)$m[2], 'y' => (int)$m[3], 'path' => $file->getRealPath()];
        });
    
        $minX = $parsed->min('x');
        $maxX = $parsed->max('x');
        $minY = $parsed->min('y');
        $maxY = $parsed->max('y');
    
        $width  = ($maxY - $minY + 1) * 256;
        $height = ($maxX - $minX + 1) * 256;
    
        $canvas = Image::canvas($width, $height, '#000000');
    
        $tileMap = [];
        $row = 0;
        for ($x = $minX; $x <= $maxX; $x++) {
            $col = 0;
            for ($y = $minY; $y <= $maxY; $y++) {
                $tile = $parsed->firstWhere(fn($t) => $t['x'] === $x && $t['y'] === $y);
                if ($tile) {
                    $img = Image::make($tile['path']);
                    $xOffset = ($y - $minY) * 256;
                    $yOffset = ($x - $minX) * 256;
                    $canvas->insert($img, 'top-left', $xOffset, $yOffset);
    
                    $tileMap[$row][$col] = [
                        'z' => $tile['z'],
                        'x' => $tile['x'],
                        'y' => $tile['y']
                    ];
                }
                $col++;
            }
            $row++;
        }
    
        $canvas->save($output);
        Log::info("Stitched map", compact('width', 'height'));
    
        // Save the mapping
        File::put(storage_path('app/tile_map.json'), json_encode($tileMap));
    
        return ['width' => $width, 'height' => $height];
    }
    
    

    private function padImageToCenteredStride($inputPath, $outputPath, $originalSize)
    {
        $img = Image::make($inputPath);
        $w = $img->width();
        $h = $img->height();

        $targetW = ceil($w / $this->stride) * $this->stride;
        $targetH = ceil($h / $this->stride) * $this->stride;

        $padX = floor(($targetW - $w) / 2);
        $padY = floor(($targetH - $h) / 2);

        $canvas = Image::canvas($targetW, $targetH, '#000000');
        $canvas->insert($img, 'top-left', $padX, $padY);
        $canvas->save($outputPath);

        Log::info("Centered stride padding", [
            'originalWidth' => $w,
            'originalHeight' => $h,
            'targetWidth' => $targetW,
            'targetHeight' => $targetH,
            'padX' => $padX,
            'padY' => $padY
        ]);
    }

    private function padImageWithOverlapBorder($inputPath, $outputPath, $overlap)
    {
        $img = Image::make($inputPath);
        $contentWidth = $img->width();
        $contentHeight = $img->height();
    
        $stride = $this->stride;
        $extendedSize = $this->tileSize + 2 * $overlap;
    
        // Figure out how many panels will cover the content at this stride
        $numCols = ceil(($contentWidth - $this->tileSize) / $stride) + 1;
        $numRows = ceil(($contentHeight - $this->tileSize) / $stride) + 1;
    
        // Minimum *content* area needed to align perfectly to grid
        $requiredContentWidth = $stride * ($numCols - 1) + $this->tileSize;
        $requiredContentHeight = $stride * ($numRows - 1) + $this->tileSize;
    
        // Add overlap borders
        $finalWidth = $requiredContentWidth + 2 * $overlap;
        $finalHeight = $requiredContentHeight + 2 * $overlap;
    
        Log::info("Calculated overlap-border padded size", [
            'contentWidth' => $contentWidth,
            'contentHeight' => $contentHeight,
            'requiredContentWidth' => $requiredContentWidth,
            'requiredContentHeight' => $requiredContentHeight,
            'finalWidth' => $finalWidth,
            'finalHeight' => $finalHeight,
            'overlap' => $overlap,
            'stride' => $stride,
            'numCols' => $numCols,
            'numRows' => $numRows
        ]);
    
        // Center the input content in the required grid-aligned space
        $paddedContent = Image::canvas($requiredContentWidth, $requiredContentHeight, '#000000');
        $padX = floor(($requiredContentWidth - $contentWidth) / 2);
        $padY = floor(($requiredContentHeight - $contentHeight) / 2);
        $paddedContent->insert($img, 'top-left', $padX, $padY);
    
        // Now add the overlap borders around that
        $finalCanvas = Image::canvas($finalWidth, $finalHeight, '#000000');
        $finalCanvas->insert($paddedContent, 'top-left', $overlap, $overlap);
    
        $finalCanvas->save($outputPath);
    
        Log::info("Added overlap border with grid-aligned padding", [
            'newWidth' => $finalWidth,
            'newHeight' => $finalHeight,
            'offsetX' => $overlap,
            'offsetY' => $overlap
        ]);
    }
    
    
    

    private function chopPanelsUniform($input, $outputDir)
    {
        File::makeDirectory($outputDir, 0755, true, true);
    
        $original = Image::make($input);
        $width = $original->width();
        $height = $original->height();
    
        $tileSize = $this->tileSize;
        $overlap = $this->overlap;
        $stride = $this->stride;
        $extendedSize = $tileSize + 2 * $overlap;
    
        Log::info("Chopping panels with uniform overlap", [
            'tileSize' => $tileSize,
            'overlap' => $overlap,
            'extendedSize' => $extendedSize,
            'stride' => $stride,
            'imageWidth' => $width,
            'imageHeight' => $height
        ]);
    
        $count = 0;
        $rows = 0;
        for ($y = 0; $y + $extendedSize <= $height + $stride / 2; $y += $stride) {
            $cols = 0;
            for ($x = 0; $x + $extendedSize <= $width + $stride / 2; $x += $stride) {
                $img = Image::make($input);
    
                $cropWidth = min($extendedSize, $width - $x);
                $cropHeight = min($extendedSize, $height - $y);
    
                // LOG WHERE WE ARE CROPPING FROM
                Log::info("Cropping panel", [
                    'panelGridPosition' => ['col' => $cols, 'row' => $rows],
                    'cropFromX' => $x,
                    'cropFromY' => $y,
                    'cropWidth' => $cropWidth,
                    'cropHeight' => $cropHeight,
                    'inputImageWidth' => $width,
                    'inputImageHeight' => $height
                ]);
    
                $crop = $img->crop($cropWidth, $cropHeight, $x, $y);
                $panel = Image::canvas($extendedSize, $extendedSize, '#000000');
                $panel->insert($crop, 'top-left', 0, 0);
    
                $savePath = $outputDir . "/panel_{$cols}_{$rows}.png";
                $panel->save($savePath);
    
                Log::info("Saved panel", [
                    'path' => $savePath,
                    'finalWidth' => $panel->width(),
                    'finalHeight' => $panel->height()
                ]);
    
                $count++;
                $cols++;
            }
            $rows++;
        }
    
        Log::info("Finished chopping into panels", [
            'rows' => $rows,
            'cols' => $cols,
            'total' => $count,
            'outputDir' => $outputDir
        ]);
    
        return ['cols' => $cols, 'rows' => $rows];
    }
    
    private function stylisePanels_SI_Control2($inputDir, $outputDir)
    {
        File::makeDirectory($outputDir . '/raw_1024', 0755, true, true);
        File::makeDirectory($outputDir . '/cropped_512', 0755, true, true);
    
        $panels = File::files($inputDir);
    
        foreach ($panels as $panel) {
            try {
                Log::info("Sending panel to OpenAI Image Edit API", ['panel' => $panel->getFilename()]);
    
                $prompt = "Create a flat, minimalistic, child-friendly map tile for a festival map. 
    It should clearly show cultivated fields, woodland, a pond or lake, roads and paths, buildings, and field boundaries. 
    Use only solid flat colors without gradients or transparency. Borders must connect seamlessly for tile sets. No text or labels.
    Color palette (strictly follow these HEX colors):
    - Fields: #A8C76F, #93B556 (alternate); borders #D5E8A6
    - Forest: #5E965C (main), #457E44 (shadow), #7DA877 (highlights)
    - Water: #6492A6, shallow edges #A7C5D7
    - Roads: #F3E8B3 (main), #F3F3DC (paths)
    - Buildings: #F6E7C1 (walls), #DFC48B (roofs)
    - Background: #86B358
    Emphasize clarity, organic shapes, and visual appeal for children.";
    
                // Optional mask path (comment out if no mask)
                //$maskPath = storage_path('app/masks/mask.png');
    
                $response = Http::withToken(config('services.openai.key'))
                    ->timeout(120)
                    ->retry(3, 1000)
                    ->attach('image', file_get_contents($panel), 'panel.png')
                    // Uncomment the next line if using a mask:
                    //->attach('mask', file_get_contents($maskPath), 'mask.png')
                    ->post('https://api.openai.com/v1/images/edits', [
                        'prompt' => $prompt,
                        'size' => '1024x1024',
                        'n' => 1,
                    ]);
    
                if ($response->failed()) {
                    Log::error("OpenAI Image Edit request failed", [
                        'status' => $response->status(),
                        'error' => $response->body()
                    ]);
                    continue;
                }
    
                $imageUrl = $response->json('data.0.url');
    
                if (!$imageUrl) {
                    Log::error("No image URL returned from OpenAI", [
                        'response' => $response->json()
                    ]);
                    continue;
                }
    
                // Download the generated image
                $imageContent = Http::timeout(60)->get($imageUrl)->body();
                if (!$imageContent) {
                    Log::error("Failed to download image from OpenAI URL", [
                        'url' => $imageUrl
                    ]);
                    continue;
                }
    
                // Save original image (1024x1024)
                $rawPath = $outputDir . '/raw_1024/' . $panel->getFilename();
                $image = Image::make($imageContent);
                $image->save($rawPath);
                Log::info("Saved raw 1024x1024 panel", ['path' => $rawPath]);
    
                // Resize to 640x640 if needed
                $image = $image->resize(640, 640);
    
                // Crop to 512x512 from 640x640
                $cropped = $image->crop(512, 512, 64, 64);
                $croppedPath = $outputDir . '/cropped_512/' . $panel->getFilename();
                $cropped->save($croppedPath);
                Log::info("Saved cropped 512x512 panel", ['path' => $croppedPath]);
    
                sleep(1);  // Avoid rate limits
    
            } catch (\Exception $e) {
                Log::error('OpenAI Image Edit call failed', [
                    'panel' => $panel->getFilename(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    

    private function stylisePanels_SI_Control($inputDir, $outputDir)
    {
        // Ensure output subdirectories exist
        File::makeDirectory($outputDir . '/raw_640', 0755, true, true);
        File::makeDirectory($outputDir . '/cropped_512', 0755, true, true);
    
        $styleImagePath = storage_path('app/style_reference/style.png');
        if (!File::exists($styleImagePath)) {
            Log::error("Style reference image is missing", ['path' => $styleImagePath]);
            throw new \Exception("Style reference image not found at $styleImagePath. Please add it before running.");
        }
    
        $panels = File::files($inputDir);
    
        foreach ($panels as $panel) {
            try {
                Log::info("Sending panel to Stability AI", ['panel' => $panel->getFilename()]);

                $prompt = "Attached is a map tile. I want you to style it and show me the image based on the following instructions: 
                Create map tile in a flat, minimalistic style, suitable for a tile set. It’s for a festival map so should be fun but without any writing or anything added.
                Imagine you are a cartographer who understands all the importance of detail and accuracy but also able to make the image appealing to a child.
                The map should contain the following elements: cultivated fields, woodland/forest, a pond or lake, roads and paths, buildings, and field boundaries. There should be no text, labels, contour lines, or extra details—emphasize clarity, organic shapes, and color-coded land use.
                Use these specific HEX colors for maximum consistency:
               - Fields: #A8C76F (main), #93B556 (alternate for variety), with field borders in #D5E8A6
               - Forest/Woodland: #5E965C (main tree canopy), #457E44 (shadow/variation), #7DA877 (edge highlights)
               - Water (Pond/Lake): #6492A6 (main), #A7C5D7 (shallow edge/highlight), small island (use a field or woodland color as appropriate)
               - Roads/Paths: #F3E8B3 (main roads), #F3F3DC (minor paths, thin lines)
               - Buildings: #F6E7C1 (walls), #DFC48B (roofs, if shown)
               - Unused Land/Background: #86B358
                 Style instructions:
               - Fields should have curved, organic edges, alternating the two green shades for diversity.
               - Forests must use overlapping circles or soft polygons for a natural canopy, with at least two green shades for depth.
               - Water areas must have smooth edges, with a consistent blue tone and an optional inner shallow highlight.
               - Roads and paths must be consistent in width and color, with soft corners at junctions.
               - Buildings are simple, small rectangles or squares, always in the cream/yellow palette.
               - No gradients or transparency; all fills are solid flat colors.
               - Borders of the tile must match so features connect seamlessly with adjacent tiles.
               - Only use the specified color palette; do not introduce new colors. IMPORTANT TO GO INTO A LOT OF DETAIL!";
                $prompt = mb_convert_encoding($prompt, 'UTF-8', 'UTF-8');
    
                $response = Http::withToken(config('services.stability.key'))
                    ->timeout(120)
                    ->retry(3, 1000)
                    ->attach('init_image', file_get_contents($panel), 'panel.png')
                    ->attach('style_image', file_get_contents($styleImagePath), 'style_1.png')
                    ->asMultipart()
                    ->post('https://api.stability.ai/v2beta/stable-image/control/style', [
                        'style_strength' => 0.8,
                        'text_prompts[0][text]' => $prompt,
                        'text_prompts[0][weight]' => 1,
                        'seed' => 12345,
                        'cfg_scale' => 10,
                        'steps' => 30
                    ]);
    
                if ($response->failed()) {
                    Log::error("HTTP failure from Stability", [
                        'status' => $response->status(),
                        'body_excerpt' => substr($response->body(), 0, 300)
                    ]);
                    continue;
                }
    
                // Save returned PNG temporarily
                $rawPath = $outputDir . '/raw_640/' . $panel->getFilename();
    
                $image = Image::make($response->body());
    
                // Log actual size returned by AI
                Log::info('AI returned image size', [
                    'width' => $image->width(),
                    'height' => $image->height()
                ]);
    
                // Force to 640x640 (AI might send 1024x1024)
                $image = $image->resize(640, 640);
                $image->save($rawPath);
                Log::info("Saved resized raw stylised 640x640 panel", ['path' => $rawPath]);
    
                // Crop 512x512 from the resized 640x640
                $cropped = $image->crop($this->tileSize, $this->tileSize, $this->overlap, $this->overlap);
                $croppedPath = $outputDir . '/cropped_512/' . $panel->getFilename();
                $cropped->save($croppedPath);
                Log::info("Saved cropped 512x512 panel", ['path' => $croppedPath]);
    
                sleep(1);  // rate limit
    
            } catch (\Exception $e) {
                Log::error('Stability AI call failed', [
                    'panel' => $panel->getFilename(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    private function stylisePanels_SDv16($inputDir, $outputDir)
    {
        // Ensure output subdirectories exist
        File::makeDirectory($outputDir . '/raw_640', 0755, true, true);
        File::makeDirectory($outputDir . '/cropped_512', 0755, true, true);

        $panels = File::files($inputDir);

        if (empty($panels)) {
            throw new \Exception("No panels found in input directory: {$inputDir}");
        }

        foreach ($panels as $panel) {
            try {
                Log::info('Sending panel to Stability AI', [
                    'panel' => $panel->getFilename(),
                    'size' => filesize($panel),
                ]);

                $prompt = "Attached is a map tile. I want you to style it and show me the image based on the following instructions: 
 Create map tile in a flat, minimalistic style, suitable for a tile set. It’s for a festival map so should be fun but without any writing or anything added.
 Imagine you are a cartographer who understands all the importance of detail and accuracy but also able to make the image appealing to a child.
 The map should contain the following elements: cultivated fields, woodland/forest, a pond or lake, roads and paths, buildings, and field boundaries. There should be no text, labels, contour lines, or extra details—emphasize clarity, organic shapes, and color-coded land use.
 Use these specific HEX colors for maximum consistency:
- Fields: #A8C76F (main), #93B556 (alternate for variety), with field borders in #D5E8A6
- Forest/Woodland: #5E965C (main tree canopy), #457E44 (shadow/variation), #7DA877 (edge highlights)
- Water (Pond/Lake): #6492A6 (main), #A7C5D7 (shallow edge/highlight), small island (use a field or woodland color as appropriate)
- Roads/Paths: #F3E8B3 (main roads), #F3F3DC (minor paths, thin lines)
- Buildings: #F6E7C1 (walls), #DFC48B (roofs, if shown)
- Unused Land/Background: #86B358
  Style instructions:
- Fields should have curved, organic edges, alternating the two green shades for diversity.
- Forests must use overlapping circles or soft polygons for a natural canopy, with at least two green shades for depth.
- Water areas must have smooth edges, with a consistent blue tone and an optional inner shallow highlight.
- Roads and paths must be consistent in width and color, with soft corners at junctions.
- Buildings are simple, small rectangles or squares, always in the cream/yellow palette.
- No gradients or transparency; all fills are solid flat colors.
- Borders of the tile must match so features connect seamlessly with adjacent tiles.
- Only use the specified color palette; do not introduce new colors. IMPORTANT TO GO INTO A LOT OF DETAIL!";
                $prompt = mb_convert_encoding($prompt, 'UTF-8', 'UTF-8');

                $response = Http::withToken(config('services.stability.key'))
                ->attach(
                    'init_image',
                    file_get_contents($panel),
                    'panel.png'
                )
                ->asMultipart()
                ->post('https://api.stability.ai/v1/generation/stable-diffusion-v1-6/image-to-image', [
                    'init_image_mode' => 'IMAGE_STRENGTH',
                    'image_strength' => 0.6,
                    'text_prompts[0][text]' => $prompt,
                    'text_prompts[0][weight]' => 3,
                    'cfg_scale' => 10,
                    'steps' => 30,
                    'samples' => 1,
                    'sampler' => 'K_HEUN',
                ])
                ->throw();
            

                Log::info('Stability AI call succeeded', [
                    'panel' => $panel->getFilename(),
                    'status' => $response->status()
                ]);

                $artifacts = $response->json('artifacts');
                if (!$artifacts || !isset($artifacts[0]['base64'])) {
                    Log::error("Stability AI response missing expected image data", [
                        'panel' => $panel->getFilename(),
                    ]);
                    continue;
                }

                $contents = base64_decode($artifacts[0]['base64']);
                if ($contents === false || strlen($contents) < 100) {
                    Log::error("Decoded image was empty or invalid!", [
                        'panel' => $panel->getFilename(),
                    ]);
                    continue;
                }

                // Load image from decoded data
                $image = Image::make($contents);

                // Log actual size returned by AI
                Log::info('AI returned image size', [
                    'width' => $image->width(),
                    'height' => $image->height()
                ]);

                // Force resize to 640x640
                $image = $image->resize(640, 640);
                $rawPath = $outputDir . '/raw_640/' . $panel->getFilename();
                $image->save($rawPath);
                Log::info("Saved resized raw stylised 640x640 panel", ['path' => $rawPath]);

                // Crop 512x512 from the resized 640x640
                $cropped = $image->crop($this->tileSize, $this->tileSize, $this->overlap, $this->overlap);
                $croppedPath = $outputDir . '/cropped_512/' . $panel->getFilename();
                $cropped->save($croppedPath);
                Log::info("Saved cropped 512x512 panel", ['path' => $croppedPath]);

                sleep(1);  // rate limit

            } catch (\Exception $e) {
                Log::error('Stability AI API call failed', [
                    'panel' => $panel->getFilename(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $savedPanels = File::files($outputDir . '/raw_640');
        if (empty($savedPanels)) {
            throw new \Exception("Stability AI calls completed but no images saved in {$outputDir}/raw_640");
        }
    }


    private function reassembleMap($inputDir, $outputPath, $numCols, $numRows)
    {
        $tileSize = $this->tileSize;
        $stride = $this->stride;
    
        // Extra black border
        $padX = 0;
        $padY = 0;
    
        $finalWidth = $stride * ($numCols - 1) + $tileSize + $padX;
        $finalHeight = $stride * ($numRows - 1) + $tileSize + $padY;
    
        Log::info("Reassembling stylised map with padding", [
            'finalWidth' => $finalWidth,
            'finalHeight' => $finalHeight,
            'numCols' => $numCols,
            'numRows' => $numRows,
            'padX' => $padX,
            'padY' => $padY
        ]);
    
        $canvas = Image::canvas($finalWidth, $finalHeight, '#000000');
    
        for ($row = 0; $row < $numRows; $row++) {
            for ($col = 0; $col < $numCols; $col++) {
                // *** FIX: use cropped_512 subfolder ***
                $panelPath = "{$inputDir}/cropped_512/panel_{$col}_{$row}.png";
    
                if (!File::exists($panelPath)) {
                    Log::warning("Missing panel", ['panel' => $panelPath]);
                    continue;
                }
    
                $panel = Image::make($panelPath);
    
                // No need to crop again here: it's already 512x512
                $x = $col * $stride + $padX;
                $y = $row * $stride + $padY;
    
                $canvas->insert($panel, 'top-left', $x, $y);
    
                Log::info("Inserted cropped panel", [
                    'panel' => $panelPath,
                    'insertX' => $x,
                    'insertY' => $y
                ]);
            }
        }
    
        $canvas->save($outputPath);
        Log::info("Saved reassembled stylised map with padding", ['outputPath' => $outputPath]);
    }
    
    
    private function cropFinalStylisedMap($inputPath, $outputPath, $originalSize)
    {
        $img = Image::make($inputPath);
        $finalWidth = $img->width();
        $finalHeight = $img->height();

        $cropX = floor(($finalWidth - $originalSize['width']) / 2);
        $cropY = floor(($finalHeight - $originalSize['height']) / 2);

        $img->crop($originalSize['width'], $originalSize['height'], $cropX, $cropY);
        $img->save($outputPath);

        Log::info("Cropped final map to original size", [
            'finalWidth' => $finalWidth,
            'finalHeight' => $finalHeight,
            'cropX' => $cropX,
            'cropY' => $cropY,
            'outputWidth' => $originalSize['width'],
            'outputHeight' => $originalSize['height']
        ]);
    }

    private function sliceTiles($inputPath, $originalSize, $outputDir)
    {
        File::makeDirectory($outputDir, 0755, true, true);
    
        $originalImage = Image::make($inputPath);
        $width = $originalSize['width'];
        $height = $originalSize['height'];
    
        $tileSize = 256;
    
        // Load original mapping
        $tileMapPath = storage_path('app/tile_map.json');
        if (!File::exists($tileMapPath)) {
            throw new \Exception("Missing tile map JSON!");
        }
        $tileMap = json_decode(File::get($tileMapPath), true);
    
        $numRows = count($tileMap);
        $numCols = count($tileMap[0]);
    
        Log::info("Slicing with tile map", [
            'tileSize' => $tileSize,
            'numCols' => $numCols,
            'numRows' => $numRows
        ]);
    
        for ($row = 0; $row < $numRows; $row++) {
            for ($col = 0; $col < $numCols; $col++) {
                $cropX = $col * $tileSize;
                $cropY = $row * $tileSize;
    
                $cropWidth = min($tileSize, $width - $cropX);
                $cropHeight = min($tileSize, $height - $cropY);
    
                // **Important fix:** work from a fresh copy each time
                $img = clone $originalImage;
                $tile = $img->crop($cropWidth, $cropHeight, $cropX, $cropY);
    
                // Use original z/x/y from tile map
                $z = $tileMap[$row][$col]['z'];
                $x = $tileMap[$row][$col]['x'];
                $y = $tileMap[$row][$col]['y'];
    
                $tilePath = "{$outputDir}/{$z}/{$x}";
                File::makeDirectory($tilePath, 0755, true, true);
    
                $tileFile = "{$tilePath}/{$y}.png";
                $tile->save($tileFile);
    
                Log::info("Saved tile", [
                    'path' => $tileFile,
                    'cropX' => $cropX,
                    'cropY' => $cropY,
                    'cropWidth' => $cropWidth,
                    'cropHeight' => $cropHeight
                ]);
            }
        }
    }
    
    
    
    
    private function zipTiles($inputDir, $outputZip)
    {
        $zip = new ZipArchive;
        if ($zip->open($outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($inputDir),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($inputDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }

            $zip->close();
            Log::info("Created zip file", ['outputZip' => $outputZip]);
        } else {
            Log::error("Failed to create zip file", ['outputZip' => $outputZip]);
        }
    }


}
