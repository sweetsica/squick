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
        $dateFilter = $request->get('date');
        $showHidden = $request->boolean('show_hidden', false);
        $search = $request->get('search');

        // Date virtual folder: list root-level files uploaded on that date
        if ($dateFilter) {
            $query = ReportUpload::query()
                ->whereNull('parent_id')
                ->where('is_folder', false);

            if (!$showHidden) {
                $query->visible();
            }

            $items = $query->get()
                ->filter(fn ($item) => $item->created_at?->format('d-m-Y') === $dateFilter)
                ->sortBy('original_name')
                ->values()
                ->map(fn ($item) => $this->formatItem($item));

            return response()->json([
                'items' => $items,
                'breadcrumb' => [
                    ['id' => null, 'name' => 'Root'],
                    ['id' => 'date_' . $dateFilter, 'name' => $dateFilter],
                ],
                'current_folder' => 'date_' . $dateFilter,
            ]);
        }

        $query = ReportUpload::query()->inFolder($parentId);

        if (!$showHidden) {
            $query->visible();
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // At root, loose files (no parent) are grouped into virtual date folders
        if ($parentId === null && !$search) {
            $query->where('is_folder', true);
        }

        $items = $query->orderByDesc('is_folder')
                       ->orderBy('original_name')
                       ->get()
                       ->map(fn ($item) => $this->formatItem($item));

        if ($parentId === null && !$search) {
            $looseFiles = ReportUpload::query()->whereNull('parent_id')->where('is_folder', false);
            if (!$showHidden) {
                $looseFiles->visible();
            }

            $dateFolders = $looseFiles->get()
                ->groupBy(fn ($item) => $item->created_at?->format('d-m-Y'))
                ->map(function ($files, $date) {
                    $size = $files->sum('size');
                    return [
                        'id' => 'date_' . $date,
                        'name' => $date,
                        'unique_name' => $date,
                        'type' => 'folder',
                        'is_folder' => true,
                        'is_date_folder' => true,
                        'is_hidden' => false,
                        'size' => $size,
                        'formatted_size' => $this->formatBytes($size),
                        'file_url' => null,
                        'file_path' => null,
                        'parent_id' => null,
                        'created_at' => $files->max('created_at')?->format('Y-m-d H:i:s'),
                        'updated_at' => $files->max('updated_at')?->format('Y-m-d H:i:s'),
                        'children_count' => $files->count(),
                    ];
                })
                ->values();

            $items = $items->concat($dateFolders);
        }

        $breadcrumb = [['id' => null, 'name' => 'Root']];
        if ($parentId) {
            $folder = ReportUpload::find($parentId);
            if ($folder) {
                $breadcrumb = array_merge($breadcrumb, $folder->breadcrumb);
            }
        }

        return response()->json([
            'items' => $items,
            'breadcrumb' => $breadcrumb,
            'current_folder' => $parentId,
        ]);
    }

    public function uploadDates(Request $request)
    {
        $showHidden = $request->boolean('show_hidden', false);

        $query = ReportUpload::query()->whereNull('parent_id')->where('is_folder', false);
        if (!$showHidden) {
            $query->visible();
        }

        $dates = $query->get()
            ->groupBy(fn ($item) => $item->created_at?->format('d-m-Y'))
            ->map(fn ($files, $date) => [
                'date' => $date,
                'count' => $files->count(),
                'sort' => Carbon::createFromFormat('d-m-Y', $date)->timestamp,
            ])
            ->sortByDesc('sort')
            ->values()
            ->map(fn ($d) => ['date' => $d['date'], 'count' => $d['count']]);

        return response()->json($dates);
    }

    private function formatItem(ReportUpload $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->original_name ?? $item->name,
            'unique_name' => $item->name,
            'type' => $item->type,
            'is_folder' => $item->is_folder,
            'is_hidden' => $item->is_hidden,
            'size' => $item->size,
            'formatted_size' => $item->formatted_size,
            'file_url' => $item->file_url,
            'file_path' => $item->file_path,
            'parent_id' => $item->parent_id,
            'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $item->updated_at?->format('Y-m-d H:i:s'),
            'children_count' => $item->is_folder ? $item->children()->count() : 0,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if (!$bytes) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    public function folderTree()
    {
        $folders = ReportUpload::folders()
            ->orderBy('original_name')
            ->get(['id', 'name', 'original_name', 'parent_id'])
            ->map(function ($f) {
                return [
                    'id' => $f->id,
                    'name' => $f->original_name ?? $f->name,
                    'parent_id' => $f->parent_id,
                ];
            });

        return response()->json($folders);
    }

    public function createFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:report_uploads,id',
        ]);

        $folder = ReportUpload::create([
            'name' => $request->name,
            'original_name' => $request->name,
            'file_path' => '',
            'file_url' => '',
            'type' => 'folder',
            'is_folder' => true,
            'parent_id' => $request->parent_id,
            'size' => 0,
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
            'parent_id' => 'nullable|exists:report_uploads,id',
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
                'parent_id' => $request->parent_id,
                'is_folder' => false,
                'size' => $fileSize,
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
        $request->validate([
            'parent_id' => 'nullable|exists:report_uploads,id',
        ]);

        $item = ReportUpload::findOrFail($id);

        if ($item->is_folder && $request->parent_id) {
            $target = ReportUpload::find($request->parent_id);
            $current = $target;
            while ($current) {
                if ($current->id == $item->id) {
                    return response()->json(['error' => 'Cannot move folder into itself or its children'], 422);
                }
                $current = $current->parent;
            }
        }

        $item->update(['parent_id' => $request->parent_id]);

        return response()->json(['success' => true]);
    }

    public function toggleVisibility($id)
    {
        $item = ReportUpload::findOrFail($id);
        $item->update(['is_hidden' => !$item->is_hidden]);

        return response()->json([
            'success' => true,
            'is_hidden' => $item->is_hidden,
        ]);
    }

    public function destroy($id)
    {
        $item = ReportUpload::findOrFail($id);

        if (!$item->is_folder && $item->file_path) {
            $fullPath = public_path($item->file_path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        if ($item->is_folder) {
            $this->deleteRecursive($item);
        }

        $item->delete();

        return response()->json(['success' => true]);
    }

    private function deleteRecursive($folder)
    {
        foreach ($folder->children as $child) {
            if ($child->is_folder) {
                $this->deleteRecursive($child);
            } else {
                if ($child->file_path) {
                    $fullPath = public_path($child->file_path);
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }
                }
            }
            $child->delete();
        }
    }

    public function info($id)
    {
        $item = ReportUpload::findOrFail($id);

        $data = [
            'id' => $item->id,
            'name' => $item->original_name ?? $item->name,
            'unique_name' => $item->name,
            'type' => $item->type,
            'is_folder' => $item->is_folder,
            'is_hidden' => $item->is_hidden,
            'size' => $item->size,
            'formatted_size' => $item->formatted_size,
            'file_url' => $item->file_url,
            'file_path' => $item->file_path,
            'token' => $item->token,
            'parent_id' => $item->parent_id,
            'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $item->updated_at?->format('Y-m-d H:i:s'),
            'breadcrumb' => $item->breadcrumb,
        ];

        if ($item->is_folder) {
            $data['children_count'] = $item->children()->count();
            $data['total_size'] = $this->folderSize($item);
            $data['total_files'] = $this->folderFileCount($item);
        }

        return response()->json($data);
    }

    private function folderSize($folder): int
    {
        $total = 0;
        foreach ($folder->children as $child) {
            if ($child->is_folder) {
                $total += $this->folderSize($child);
            } else {
                $total += $child->size ?? 0;
            }
        }
        return $total;
    }

    private function folderFileCount($folder): int
    {
        $count = 0;
        foreach ($folder->children as $child) {
            if ($child->is_folder) {
                $count += $this->folderFileCount($child);
            } else {
                $count++;
            }
        }
        return $count;
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
        $totalFiles = ReportUpload::files()->count();
        $totalFolders = ReportUpload::folders()->count();
        $totalSize = ReportUpload::files()->sum('size');
        $hiddenCount = ReportUpload::where('is_hidden', true)->count();

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
