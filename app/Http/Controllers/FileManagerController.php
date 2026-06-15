<?php

namespace App\Http\Controllers;

use App\Models\ReportUpload;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class FileManagerController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    public function listFiles(Request $request)
    {
        $parentId = $request->get('parent_id');
        $showHidden = $request->boolean('show_hidden', false);
        $search = $request->get('search');

        $query = ReportUpload::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('original_name')
                       ->get()
                       ->map(function ($item) {
                           return [
                               'id' => $item->id,
                               'name' => $item->original_name ?? $item->name,
                               'unique_name' => $item->name,
                               'type' => $item->type,
                               'file_url' => $item->file_url,
                               'file_path' => $item->file_path,
                               'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                               'updated_at' => $item->updated_at?->format('Y-m-d H:i:s'),
                           ];
                       });

        $breadcrumb = [['id' => null, 'name' => 'Root']];

        return response()->json([
            'items' => $items,
            'breadcrumb' => $breadcrumb,
            'current_folder' => $parentId,
        ]);
    }

    public function folderTree()
    {
        $folders = ReportUpload::orderBy('original_name')
            ->get(['id', 'name', 'original_name'])
            ->map(function ($f) {
                return [
                    'id' => $f->id,
                    'name' => $f->original_name ?? $f->name,
                ];
            });

        return response()->json($folders);
    }

    public function createFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $folder = ReportUpload::create([
            'name' => $request->name,
            'original_name' => $request->name,
            'file_path' => '',
            'file_url' => '',
            'type' => 'folder',
        ]);

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $folder->id,
                'name' => $folder->original_name,
                'type' => 'folder',
                'is_folder' => true,
                'is_hidden' => false,
                'size' => 0,
                'formatted_size' => '0 B',
                'parent_id' => $folder->parent_id,
                'created_at' => $folder->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $folder->updated_at->format('Y-m-d H:i:s'),
                'children_count' => 0,
            ],
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'files' => 'required',
            'files.*' => 'file|max:102400',
        ]);

        $files = $request->file('files');
        if (!is_array($files)) {
            $files = [$files];
        }

        $uploaded = [];
        $date = Carbon::today()->format('d-m-Y');

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $uniqueName = pathinfo($originalName, PATHINFO_FILENAME) . '_' . time() . '_' . Str::random(5) . '.' . $extension;
            $fileSize = $file->getSize();

            $publicPath = "vault/" . $date;
            $destinationPath = public_path($publicPath);

            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $uniqueName);

            $path = $publicPath . '/' . $uniqueName;
            $linkFile = URL::to('/') . '/' . $path;

            $record = ReportUpload::create([
                'name' => $uniqueName,
                'original_name' => $originalName,
                'file_path' => $path,
                'file_url' => $linkFile,
                'token' => $request->token,
                'type' => $extension,
            ]);

            $uploaded[] = [
                'id' => $record->id,
                'name' => $record->original_name,
                'unique_name' => $record->name,
                'type' => $record->type,
                'is_folder' => false,
                'is_hidden' => false,
                'size' => $record->size,
                'formatted_size' => $record->formatted_size,
                'file_url' => $record->file_url,
                'file_path' => $record->file_path,
                'parent_id' => $record->parent_id,
                'created_at' => $record->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $record->updated_at->format('Y-m-d H:i:s'),
                'children_count' => 0,
            ];
        }

        return response()->json([
            'success' => true,
            'items' => $uploaded,
        ]);
    }

    public function rename(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $item = ReportUpload::findOrFail($id);
        $item->update(['original_name' => $request->name]);

        return response()->json(['success' => true, 'item' => $item]);
    }

    public function move(Request $request, $id)
    {
        return response()->json(['success' => true]);
    }

    public function toggleVisibility($id)
    {
        $item = ReportUpload::findOrFail($id);

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy($id)
    {
        $item = ReportUpload::findOrFail($id);

        if ($item->file_path) {
            $fullPath = public_path($item->file_path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        $item->delete();

        return response()->json(['success' => true]);
    }

    public function info($id)
    {
        $item = ReportUpload::findOrFail($id);

        $data = [
            'id' => $item->id,
            'name' => $item->original_name ?? $item->name,
            'unique_name' => $item->name,
            'type' => $item->type,
            'file_url' => $item->file_url,
            'file_path' => $item->file_path,
            'token' => $item->token,
            'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $item->updated_at?->format('Y-m-d H:i:s'),
        ];

        return response()->json($data);
    }

    public function download($id)
    {
        $item = ReportUpload::findOrFail($id);

        $fullPath = public_path($item->file_path);
        if (!file_exists($fullPath)) {
            return response()->json(['error' => 'File not found on disk'], 404);
        }

        return response()->download($fullPath, $item->original_name ?? $item->name);
    }

    public function stats()
    {
        $totalFiles = ReportUpload::count();
        $totalFolders = 0;
        $totalSize = 0;
        $hiddenCount = 0;

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = $totalSize > 0 ? floor(log($totalSize, 1024)) : 0;
        $formattedSize = round($totalSize / pow(1024, max($i, 1) == 0 ? 1 : $i), 2) . ' ' . $units[$i];
        if ($totalSize == 0) $formattedSize = '0 B';

        return response()->json([
            'total_files' => $totalFiles,
            'total_folders' => $totalFolders,
            'total_size' => $totalSize,
            'formatted_size' => $formattedSize,
            'hidden_count' => $hiddenCount,
        ]);
    }
}
