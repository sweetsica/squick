<?php

namespace App\Http\Controllers;

use App\Models\Vault;
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

        $query = Vault::query()->inFolder($parentId);

        if (!$showHidden) {
            $query->visible();
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $items = $query->orderByDesc('is_folder')
                       ->orderBy('original_name')
                       ->get()
                       ->map(function ($item) {
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
                       });

        $breadcrumb = [['id' => null, 'name' => 'Root']];
        if ($parentId) {
            $folder = Vault::find($parentId);
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

    public function folderTree()
    {
        $folders = Vault::folders()
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
            'parent_id' => 'nullable|exists:vaults,id',
        ]);

        $folder = Vault::create([
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
            'parent_id' => 'nullable|exists:vaults,id',
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

            $path = Storage::putFileAs("public/vault/" . $date, $file, $uniqueName);
            $linkFile = URL::to('/') . Storage::url('vault/' . $date . '/' . $uniqueName);

            $record = Vault::create([
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

        $item = Vault::findOrFail($id);
        $item->update(['original_name' => $request->name]);

        return response()->json(['success' => true, 'item' => $item]);
    }

    public function move(Request $request, $id)
    {
        $request->validate([
            'parent_id' => 'nullable|exists:vaults,id',
        ]);

        $item = Vault::findOrFail($id);

        if ($item->is_folder && $request->parent_id) {
            $target = Vault::find($request->parent_id);
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
        $item = Vault::findOrFail($id);
        $item->update(['is_hidden' => !$item->is_hidden]);

        return response()->json([
            'success' => true,
            'is_hidden' => $item->is_hidden,
        ]);
    }

    public function destroy($id)
    {
        $item = Vault::findOrFail($id);

        if (!$item->is_folder && $item->file_path) {
            Storage::delete($item->file_path);
        }

        if ($item->is_folder) {
            $this->deleteRecursive($item);
        }

        $item->delete();

        return response()->json(['success' => true]);
    }

    private function deleteRecursive(Vault $folder)
    {
        foreach ($folder->children as $child) {
            if ($child->is_folder) {
                $this->deleteRecursive($child);
            } else {
                if ($child->file_path) {
                    Storage::delete($child->file_path);
                }
            }
            $child->delete();
        }
    }

    public function info($id)
    {
        $item = Vault::findOrFail($id);

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

    private function folderSize(Vault $folder): int
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

    private function folderFileCount(Vault $folder): int
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
        $item = Vault::findOrFail($id);

        if ($item->is_folder) {
            return response()->json(['error' => 'Cannot download a folder'], 422);
        }

        if (!Storage::exists($item->file_path)) {
            return response()->json(['error' => 'File not found on disk'], 404);
        }

        return Storage::download($item->file_path, $item->original_name ?? $item->name);
    }

    public function stats()
    {
        $totalFiles = Vault::files()->count();
        $totalFolders = Vault::folders()->count();
        $totalSize = Vault::files()->sum('size');
        $hiddenCount = Vault::where('is_hidden', true)->count();

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
