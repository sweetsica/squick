<!DOCTYPE html>
<html lang="vi" x-data="fileManager()" x-init="init()">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>File Manager — Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
        * { font-family: 'Inter', sans-serif; }
        .fm-scrollbar::-webkit-scrollbar { width: 6px; }
        .fm-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .fm-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .fm-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .drop-zone-active { border-color: #3b82f6 !important; background: rgba(59,130,246,0.05) !important; }
        .item-hidden { opacity: 0.45; }
        .tree-item:hover { background: #f1f5f9; }
        .tree-item.active { background: #e0e7ff; color: #4338ca; }
        .ctx-menu { box-shadow: 0 4px 24px rgba(0,0,0,0.12); }
        .grid-item:hover { background: #f8fafc; }
        .grid-item.selected { background: #e0e7ff; border-color: #818cf8; }
        .list-row:hover { background: #f8fafc; }
        .list-row.selected { background: #e0e7ff; }
        @keyframes fadeIn { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }
        .animate-in { animation: fadeIn 0.2s ease-out; }
        .modal-backdrop { background: rgba(0,0,0,0.4); backdrop-filter: blur(2px); }
    </style>
</head>
<body class="bg-gray-50 h-screen flex flex-col overflow-hidden select-none">

    {{-- Top Header --}}
    <header class="bg-white border-b border-gray-200 px-4 py-2.5 flex items-center gap-4 shrink-0 z-30">
        <div class="flex items-center gap-2">
            <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
            <h1 class="text-lg font-semibold text-gray-800">File Manager</h1>
        </div>

        {{-- Search --}}
        <div class="flex-1 max-w-md mx-auto relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" x-model="searchQuery" @input.debounce.400ms="loadFiles()"
                   placeholder="Tìm kiếm file hoặc thư mục..."
                   class="w-full pl-10 pr-4 py-2 text-sm bg-gray-100 border border-transparent rounded-lg focus:bg-white focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 outline-none transition">
        </div>

        {{-- Stats --}}
        <div class="flex items-center gap-4 text-xs text-gray-500" x-show="stats">
            <span><strong class="text-gray-700" x-text="stats?.total_files || 0"></strong> files</span>
            <span><strong class="text-gray-700" x-text="stats?.total_folders || 0"></strong> folders</span>
            <span x-text="stats?.formatted_size || '0 B'"></span>
        </div>

        {{-- Show hidden toggle --}}
        <label class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer">
            <input type="checkbox" x-model="showHidden" @change="loadFiles(); loadUploadDates();" class="rounded text-indigo-600 focus:ring-indigo-500 w-3.5 h-3.5">
            <span>Ẩn/Hiện</span>
        </label>
    </header>

    <div class="flex flex-1 overflow-hidden">

        {{-- Sidebar: Folder Tree + Upload Dates --}}
        <aside class="w-60 bg-white border-r border-gray-200 flex flex-col shrink-0">
            {{-- Folders Section --}}
            <div class="px-3 py-2.5 border-b border-gray-100 flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Thư mục</span>
                <button @click="openNewFolderModal(null)" class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-indigo-600 transition" title="Tạo thư mục mới ở Root">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </button>
            </div>
            <nav class="flex-initial overflow-y-auto fm-scrollbar p-1.5" style="max-height: 40%">
                {{-- Root --}}
                <div class="tree-item flex items-center gap-2 px-2 py-1.5 rounded-md cursor-pointer text-sm"
                     :class="{ 'active': currentFolder === null }"
                     @click="navigateTo(null)">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                    <span>Root</span>
                </div>
                {{-- Tree items --}}
                <template x-for="folder in treeRoots()" :key="folder.id">
                    <div x-html="renderTreeItem(folder, 0)"></div>
                </template>
                {{-- Simple flat tree for reliability --}}
                <template x-for="folder in folderTree.filter(f => !f.parent_id)" :key="'tree-'+folder.id">
                    <div>
                        <div class="tree-item flex items-center gap-2 px-2 py-1.5 rounded-md cursor-pointer text-sm ml-3"
                             :class="{ 'active': currentFolder === folder.id }"
                             @click="navigateTo(folder.id)"
                             @contextmenu.prevent="onContextMenu($event, folder, true)">
                            <svg class="w-4 h-4 shrink-0 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M2 6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                            <span x-text="folder.name" class="truncate"></span>
                        </div>
                        {{-- Level 2 --}}
                        <template x-for="sub in folderTree.filter(f => f.parent_id === folder.id)" :key="'sub-'+sub.id">
                            <div class="tree-item flex items-center gap-2 px-2 py-1.5 rounded-md cursor-pointer text-sm ml-7"
                                 :class="{ 'active': currentFolder === sub.id }"
                                 @click="navigateTo(sub.id)"
                                 @contextmenu.prevent="onContextMenu($event, sub, true)">
                                <svg class="w-4 h-4 shrink-0 text-yellow-400" fill="currentColor" viewBox="0 0 24 24"><path d="M2 6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                                <span x-text="sub.name" class="truncate"></span>
                            </div>
                            {{-- Level 3 --}}
                            <template x-for="sub2 in folderTree.filter(f => f.parent_id === sub.id)" :key="'sub2-'+sub2.id">
                                <div class="tree-item flex items-center gap-2 px-2 py-1.5 rounded-md cursor-pointer text-sm ml-11"
                                     :class="{ 'active': currentFolder === sub2.id }"
                                     @click="navigateTo(sub2.id)"
                                     @contextmenu.prevent="onContextMenu($event, sub2, true)">
                                    <svg class="w-4 h-4 shrink-0 text-yellow-300" fill="currentColor" viewBox="0 0 24 24"><path d="M2 6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                                    <span x-text="sub2.name" class="truncate"></span>
                                </div>
                            </template>
                        </template>
                    </div>
                </template>
            </nav>

            {{-- Upload Dates Section --}}
            <div class="px-3 py-2.5 border-t border-b border-gray-100 flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Ngày tải lên</span>
            </div>
            <nav class="flex-1 overflow-y-auto fm-scrollbar p-1.5">
                <template x-for="d in uploadDates" :key="d.date">
                    <div class="tree-item flex items-center gap-2 px-2 py-1.5 rounded-md cursor-pointer text-sm"
                         :class="{ 'active': currentFolder === ('date_' + d.date) }"
                         @click="navigateToDate(d.date)">
                        <svg class="w-4 h-4 shrink-0 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                        <span class="truncate flex-1" x-text="d.date"></span>
                        <span class="text-[10px] text-gray-400" x-text="d.count"></span>
                    </div>
                </template>
            </nav>

            {{-- Storage info --}}
            <div class="px-3 py-3 border-t border-gray-100 text-xs text-gray-400">
                <div class="flex justify-between">
                    <span>Tổng dung lượng</span>
                    <span class="font-medium text-gray-600" x-text="stats?.formatted_size || '0 B'"></span>
                </div>
            </div>
        </aside>

        {{-- Main Content --}}
        <main class="flex-1 flex flex-col overflow-hidden">

            {{-- Toolbar --}}
            <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-2 shrink-0">
                {{-- Breadcrumb --}}
                <nav class="flex items-center gap-1 text-sm flex-1 min-w-0">
                    <template x-for="(crumb, idx) in breadcrumb" :key="'bc-'+idx">
                        <div class="flex items-center gap-1">
                            <button @click="navigateTo(crumb.id)"
                                    class="hover:text-indigo-600 transition truncate max-w-[150px]"
                                    :class="idx === breadcrumb.length - 1 ? 'text-gray-800 font-medium' : 'text-gray-400'">
                                <span x-text="crumb.name"></span>
                            </button>
                            <svg x-show="idx < breadcrumb.length - 1" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </div>
                    </template>
                </nav>

                {{-- Action buttons --}}
                <div class="flex items-center gap-1.5">
                    <button @click="openUploadModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                        Upload
                    </button>
                    <button @click="openNewFolderModal(currentFolder)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                        Thư mục mới
                    </button>
                    <div class="w-px h-5 bg-gray-200 mx-1"></div>
                    <button @click="viewMode = 'large'" class="p-1.5 rounded-md transition" :class="viewMode === 'large' ? 'bg-indigo-100 text-indigo-600' : 'text-gray-400 hover:text-gray-600'" title="Large Icons">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
                    </button>
                    <button @click="viewMode = 'grid'" class="p-1.5 rounded-md transition" :class="viewMode === 'grid' ? 'bg-indigo-100 text-indigo-600' : 'text-gray-400 hover:text-gray-600'" title="Grid">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                    </button>
                    <button @click="viewMode = 'list'" class="p-1.5 rounded-md transition" :class="viewMode === 'list' ? 'bg-indigo-100 text-indigo-600' : 'text-gray-400 hover:text-gray-600'" title="List">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                    </button>
                    <button @click="loadFiles(); loadFolderTree(); loadStats(); loadUploadDates();" class="p-1.5 rounded-md text-gray-400 hover:text-gray-600 transition" title="Làm mới">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                    </button>
                </div>
            </div>

            {{-- Drop zone / content --}}
            <div class="flex-1 overflow-y-auto fm-scrollbar p-4"
                 @dragover.prevent="dragOver = true"
                 @dragleave.prevent="dragOver = false"
                 @drop.prevent="handleDrop($event)"
                 :class="{ 'drop-zone-active border-2 border-dashed': dragOver }"
                 @click="if($event.target === $el) { selectedItems = []; infoPanel = null; }"
                 @contextmenu.prevent="onContextMenu($event, null, false)">

                {{-- Loading --}}
                <div x-show="loading" class="flex items-center justify-center h-full">
                    <svg class="animate-spin w-8 h-8 text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </div>

                {{-- Empty state --}}
                <div x-show="!loading && items.length === 0" x-cloak class="flex flex-col items-center justify-center h-full text-gray-400">
                    <svg class="w-16 h-16 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0121.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                    <p class="text-sm font-medium">Thư mục trống</p>
                    <p class="text-xs mt-1">Kéo thả file vào đây hoặc nhấn Upload</p>
                </div>

                {{-- LARGE ICONS VIEW --}}
                <div x-show="!loading && items.length > 0 && viewMode === 'large'" x-cloak
                     class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                    <template x-for="item in items" :key="'lg-'+item.id">
                        <div class="grid-item flex flex-col items-center gap-2 p-3 rounded-xl border border-transparent cursor-pointer transition animate-in"
                             :class="{
                                'selected': selectedItems.includes(item.id),
                                'item-hidden': item.is_hidden
                             }"
                             @click.stop="selectItem(item, $event)"
                             @dblclick="item.is_folder ? navigateTo(item.id) : previewFile(item)"
                             @contextmenu.prevent="onContextMenu($event, item, false)">
                            {{-- Thumbnail / Icon --}}
                            <div class="w-full aspect-square max-h-40 rounded-lg overflow-hidden flex items-center justify-center bg-gray-50 border border-gray-100">
                                <template x-if="item.is_date_folder">
                                    <svg class="w-16 h-16 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                                </template>
                                <template x-if="item.is_folder && !item.is_date_folder">
                                    <svg class="w-16 h-16 text-yellow-400" fill="currentColor" viewBox="0 0 24 24"><path d="M2 6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                                </template>
                                <template x-if="!item.is_folder && isImage(item.type)">
                                    <img :src="item.file_url" class="w-full h-full object-cover" loading="lazy" :alt="item.name">
                                </template>
                                <template x-if="!item.is_folder && isVideo(item.type)">
                                    <div class="relative w-full h-full bg-gray-900 flex items-center justify-center">
                                        <video :src="item.file_url" class="w-full h-full object-cover" muted preload="metadata"></video>
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/20">
                                            <svg class="w-10 h-10 text-white/80" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!item.is_folder && !isImage(item.type) && !isVideo(item.type)">
                                    <div class="w-16 h-16 rounded-xl flex items-center justify-center text-white text-lg font-bold uppercase"
                                         :class="getFileColor(item.type)"
                                         x-text="item.type || '?'">
                                    </div>
                                </template>
                            </div>
                            <span class="text-xs text-center text-gray-700 truncate w-full px-1 leading-tight font-medium" x-text="item.name"></span>
                            <span class="text-[10px] text-gray-400" x-text="item.is_folder ? (item.children_count + ' items') : item.formatted_size"></span>
                        </div>
                    </template>
                </div>

                {{-- GRID VIEW --}}
                <div x-show="!loading && items.length > 0 && viewMode === 'grid'" x-cloak
                     class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-2">
                    <template x-for="item in items" :key="item.id">
                        <div class="grid-item flex flex-col items-center gap-1.5 p-3 rounded-xl border border-transparent cursor-pointer transition animate-in"
                             :class="{
                                'selected': selectedItems.includes(item.id),
                                'item-hidden': item.is_hidden
                             }"
                             @click.stop="selectItem(item, $event)"
                             @dblclick="item.is_folder ? navigateTo(item.id) : previewFile(item)"
                             @contextmenu.prevent="onContextMenu($event, item, false)">
                            {{-- Icon --}}
                            <div class="w-12 h-12 flex items-center justify-center">
                                <template x-if="item.is_date_folder">
                                    <svg class="w-12 h-12 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                                </template>
                                <template x-if="item.is_folder && !item.is_date_folder">
                                    <svg class="w-12 h-12 text-yellow-400" fill="currentColor" viewBox="0 0 24 24"><path d="M2 6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                                </template>
                                <template x-if="!item.is_folder && isImage(item.type)">
                                    <img :src="item.file_url" class="w-12 h-12 rounded-lg object-cover" loading="lazy" :alt="item.name">
                                </template>
                                <template x-if="!item.is_folder && !isImage(item.type)">
                                    <div class="w-12 h-12 rounded-lg flex items-center justify-center text-white text-xs font-bold uppercase"
                                         :class="getFileColor(item.type)"
                                         x-text="item.type || '?'">
                                    </div>
                                </template>
                            </div>
                            <span class="text-xs text-center text-gray-700 truncate w-full px-1 leading-tight" x-text="item.name"></span>
                            <span class="text-[10px] text-gray-400" x-text="item.is_folder ? (item.children_count + ' items') : item.formatted_size"></span>
                        </div>
                    </template>
                </div>

                {{-- LIST VIEW --}}
                <div x-show="!loading && items.length > 0 && viewMode === 'list'" x-cloak>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-400 uppercase tracking-wider border-b border-gray-200">
                                <th class="pb-2 pl-2 font-medium w-10"></th>
                                <th class="pb-2 font-medium">Tên</th>
                                <th class="pb-2 font-medium w-24">Loại</th>
                                <th class="pb-2 font-medium w-28">Kích thước</th>
                                <th class="pb-2 font-medium w-40">Ngày tạo</th>
                                <th class="pb-2 font-medium w-20 text-center">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="item in items" :key="'list-'+item.id">
                                <tr class="list-row border-b border-gray-100 cursor-pointer transition animate-in"
                                    :class="{
                                        'selected': selectedItems.includes(item.id),
                                        'item-hidden': item.is_hidden
                                    }"
                                    @click.stop="selectItem(item, $event)"
                                    @dblclick="item.is_folder ? navigateTo(item.id) : previewFile(item)"
                                    @contextmenu.prevent="onContextMenu($event, item, false)">
                                    <td class="py-2 pl-2">
                                        <template x-if="item.is_date_folder">
                                            <svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                                        </template>
                                        <template x-if="item.is_folder && !item.is_date_folder">
                                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 24 24"><path d="M2 6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                                        </template>
                                        <template x-if="!item.is_folder">
                                            <div class="w-5 h-5 rounded flex items-center justify-center text-white text-[8px] font-bold uppercase"
                                                 :class="getFileColor(item.type)" x-text="item.type || '?'"></div>
                                        </template>
                                    </td>
                                    <td class="py-2 text-gray-800 font-medium" x-text="item.name"></td>
                                    <td class="py-2 text-gray-500 uppercase text-xs" x-text="item.is_folder ? 'Folder' : (item.type || '—')"></td>
                                    <td class="py-2 text-gray-500" x-text="item.is_folder ? (item.children_count + ' items') : item.formatted_size"></td>
                                    <td class="py-2 text-gray-400 text-xs" x-text="item.created_at"></td>
                                    <td class="py-2 text-center">
                                        <span x-show="item.is_hidden" class="inline-block w-2 h-2 rounded-full bg-orange-400" title="Đang ẩn"></span>
                                        <span x-show="!item.is_hidden" class="inline-block w-2 h-2 rounded-full bg-green-400" title="Hiển thị"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        {{-- Info Panel (Right sidebar) --}}
        <aside x-show="infoPanel" x-cloak x-transition
               class="w-72 bg-white border-l border-gray-200 flex flex-col shrink-0 overflow-y-auto fm-scrollbar">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Chi tiết</h3>
                <button @click="infoPanel = null" class="p-1 rounded hover:bg-gray-100 text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-4 space-y-4" x-show="infoPanel">
                {{-- Preview --}}
                <div class="flex flex-col items-center">
                    <template x-if="infoPanel?.is_folder">
                        <svg class="w-16 h-16 text-yellow-400" fill="currentColor" viewBox="0 0 24 24"><path d="M2 6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                    </template>
                    <template x-if="infoPanel && !infoPanel.is_folder && isImage(infoPanel.type)">
                        <img :src="infoPanel.file_url" class="w-full h-32 object-contain rounded-lg bg-gray-50 border">
                    </template>
                    <template x-if="infoPanel && !infoPanel.is_folder && !isImage(infoPanel.type)">
                        <div class="w-16 h-16 rounded-xl flex items-center justify-center text-white text-sm font-bold uppercase"
                             :class="getFileColor(infoPanel.type)" x-text="infoPanel.type || '?'"></div>
                    </template>
                    <p class="mt-2 text-sm font-medium text-gray-800 text-center break-all" x-text="infoPanel?.name"></p>
                </div>

                {{-- Info rows --}}
                <div class="space-y-2.5 text-xs">
                    <div class="flex justify-between" x-show="!infoPanel?.is_folder">
                        <span class="text-gray-400">Loại file</span>
                        <span class="text-gray-700 uppercase font-medium" x-text="infoPanel?.type || '—'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Kích thước</span>
                        <span class="text-gray-700 font-medium" x-text="infoPanel?.formatted_size || '—'"></span>
                    </div>
                    <div class="flex justify-between" x-show="infoPanel?.is_folder">
                        <span class="text-gray-400">Số file</span>
                        <span class="text-gray-700 font-medium" x-text="infoPanel?.total_files ?? infoPanel?.children_count ?? '—'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Trạng thái</span>
                        <span class="font-medium" :class="infoPanel?.is_hidden ? 'text-orange-500' : 'text-green-600'" x-text="infoPanel?.is_hidden ? 'Đang ẩn' : 'Hiển thị'"></span>
                    </div>
                    <div class="flex justify-between" x-show="infoPanel?.token">
                        <span class="text-gray-400">Chủ sở hữu</span>
                        <span class="text-gray-700 font-medium truncate max-w-[140px]" x-text="infoPanel?.token || '—'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Ngày tạo</span>
                        <span class="text-gray-700" x-text="infoPanel?.created_at || '—'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Cập nhật</span>
                        <span class="text-gray-700" x-text="infoPanel?.updated_at || '—'"></span>
                    </div>
                    <div x-show="infoPanel?.file_url && !infoPanel?.is_folder" class="pt-1">
                        <span class="text-gray-400 block mb-1">URL</span>
                        <div class="bg-gray-50 rounded p-2 break-all text-[10px] text-gray-500 cursor-pointer hover:bg-gray-100"
                             @click="copyToClipboard(infoPanel.file_url)"
                             title="Click để copy">
                            <span x-text="infoPanel?.file_url"></span>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="space-y-1.5 pt-2 border-t border-gray-100" x-show="!infoPanel?.is_date_folder">
                    <button @click="openRenameModal(infoPanel)" class="w-full text-left px-3 py-2 text-xs rounded-lg hover:bg-gray-50 flex items-center gap-2 text-gray-600">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                        Đổi tên
                    </button>
                    <button @click="toggleVisibility(infoPanel.id)" class="w-full text-left px-3 py-2 text-xs rounded-lg hover:bg-gray-50 flex items-center gap-2 text-gray-600">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span x-text="infoPanel?.is_hidden ? 'Hiện file' : 'Ẩn file'"></span>
                    </button>
                    <button x-show="!infoPanel?.is_folder" @click="downloadFile(infoPanel.id)" class="w-full text-left px-3 py-2 text-xs rounded-lg hover:bg-gray-50 flex items-center gap-2 text-gray-600">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        Tải xuống
                    </button>
                    <button @click="confirmDelete(infoPanel)" class="w-full text-left px-3 py-2 text-xs rounded-lg hover:bg-red-50 flex items-center gap-2 text-red-500">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                        Xóa
                    </button>
                </div>
            </div>
        </aside>
    </div>

    {{-- Context Menu --}}
    <div x-show="contextMenu.show" x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         :style="`left: ${contextMenu.x}px; top: ${contextMenu.y}px;`"
         @click.away="contextMenu.show = false"
         class="ctx-menu fixed z-50 bg-white border border-gray-200 rounded-xl py-1.5 w-52 text-xs">

        {{-- On empty area --}}
        <template x-if="!contextMenu.item">
            <div>
                <button @click="openUploadModal(); contextMenu.show=false" class="w-full px-3 py-2 text-left hover:bg-gray-50 flex items-center gap-2.5 text-gray-700">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                    Upload file
                </button>
                <button @click="openNewFolderModal(currentFolder); contextMenu.show=false" class="w-full px-3 py-2 text-left hover:bg-gray-50 flex items-center gap-2.5 text-gray-700">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                    Tạo thư mục mới
                </button>
                <div class="border-t border-gray-100 my-1"></div>
                <button @click="loadFiles(); contextMenu.show=false" class="w-full px-3 py-2 text-left hover:bg-gray-50 flex items-center gap-2.5 text-gray-700">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                    Làm mới
                </button>
            </div>
        </template>

        {{-- On item --}}
        <template x-if="contextMenu.item">
            <div>
                <button x-show="contextMenu.item?.is_folder" @click="navigateTo(contextMenu.item.id); contextMenu.show=false" class="w-full px-3 py-2 text-left hover:bg-gray-50 flex items-center gap-2.5 text-gray-700">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776"/></svg>
                    Mở thư mục
                </button>
                <button x-show="!contextMenu.item?.is_folder" @click="previewFile(contextMenu.item); contextMenu.show=false" class="w-full px-3 py-2 text-left hover:bg-gray-50 flex items-center gap-2.5 text-gray-700">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Xem file
                </button>
                <button x-show="!contextMenu.item?.is_date_folder" @click="showInfo(contextMenu.item.id); contextMenu.show=false" class="w-full px-3 py-2 text-left hover:bg-gray-50 flex items-center gap-2.5 text-gray-700">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>
                    Thông tin
                </button>
                <template x-if="!contextMenu.item?.is_date_folder">
                <div>
                <div class="border-t border-gray-100 my-1"></div>
                <button @click="openRenameModal(contextMenu.item); contextMenu.show=false" class="w-full px-3 py-2 text-left hover:bg-gray-50 flex items-center gap-2.5 text-gray-700">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                    Đổi tên
                </button>
                <button @click="toggleVisibility(contextMenu.item.id); contextMenu.show=false" class="w-full px-3 py-2 text-left hover:bg-gray-50 flex items-center gap-2.5 text-gray-700">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                    <span x-text="contextMenu.item?.is_hidden ? 'Hiện file' : 'Ẩn file'"></span>
                </button>
                <button x-show="!contextMenu.item?.is_folder" @click="downloadFile(contextMenu.item.id); contextMenu.show=false" class="w-full px-3 py-2 text-left hover:bg-gray-50 flex items-center gap-2.5 text-gray-700">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Tải xuống
                </button>
                <button x-show="!contextMenu.item?.is_folder && contextMenu.item?.file_url" @click="copyToClipboard(contextMenu.item.file_url); contextMenu.show=false" class="w-full px-3 py-2 text-left hover:bg-gray-50 flex items-center gap-2.5 text-gray-700">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.54a4.5 4.5 0 00-1.242-7.244l-4.5-4.5a4.5 4.5 0 00-6.364 6.364L6 10.5"/></svg>
                    Copy link
                </button>
                <div class="border-t border-gray-100 my-1"></div>
                <button @click="confirmDelete(contextMenu.item); contextMenu.show=false" class="w-full px-3 py-2 text-left hover:bg-red-50 flex items-center gap-2.5 text-red-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                    Xóa
                </button>
                </div>
                </template>
            </div>
        </template>
    </div>

    {{-- MODALS --}}

    {{-- New Folder Modal --}}
    <div x-show="modals.newFolder" x-cloak class="fixed inset-0 z-50 flex items-center justify-center modal-backdrop" @click.self="modals.newFolder = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 animate-in" @keydown.escape.window="modals.newFolder = false">
            <h3 class="text-base font-semibold text-gray-800 mb-4">Tạo thư mục mới</h3>
            <input type="text" x-model="newFolderName" x-ref="newFolderInput"
                   @keydown.enter="createFolder()"
                   placeholder="Tên thư mục..."
                   class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none">
            <div class="flex justify-end gap-2 mt-4">
                <button @click="modals.newFolder = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition">Hủy</button>
                <button @click="createFolder()" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium">Tạo</button>
            </div>
        </div>
    </div>

    {{-- Rename Modal --}}
    <div x-show="modals.rename" x-cloak class="fixed inset-0 z-50 flex items-center justify-center modal-backdrop" @click.self="modals.rename = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 animate-in" @keydown.escape.window="modals.rename = false">
            <h3 class="text-base font-semibold text-gray-800 mb-4">Đổi tên</h3>
            <input type="text" x-model="renameName" x-ref="renameInput"
                   @keydown.enter="renameItem()"
                   class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none">
            <div class="flex justify-end gap-2 mt-4">
                <button @click="modals.rename = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition">Hủy</button>
                <button @click="renameItem()" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium">Lưu</button>
            </div>
        </div>
    </div>

    {{-- Upload Modal --}}
    <div x-show="modals.upload" x-cloak class="fixed inset-0 z-50 flex items-center justify-center modal-backdrop" @click.self="modals.upload = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 animate-in" @keydown.escape.window="modals.upload = false">
            <h3 class="text-base font-semibold text-gray-800 mb-4">Upload file</h3>
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center transition"
                 :class="{ 'border-indigo-400 bg-indigo-50': uploadDragOver }"
                 @dragover.prevent="uploadDragOver = true"
                 @dragleave.prevent="uploadDragOver = false"
                 @drop.prevent="handleUploadDrop($event)">
                <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                <p class="text-sm text-gray-500">Kéo thả file vào đây</p>
                <p class="text-xs text-gray-400 mt-1">hoặc</p>
                <label class="inline-block mt-2 px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg cursor-pointer hover:bg-indigo-700 transition">
                    Chọn file
                    <input type="file" multiple class="hidden" @change="handleFileSelect($event)">
                </label>
            </div>
            {{-- File list --}}
            <div x-show="uploadFiles.length > 0" class="mt-4 max-h-40 overflow-y-auto fm-scrollbar space-y-1">
                <template x-for="(f, idx) in uploadFiles" :key="idx">
                    <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg text-xs">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="font-medium text-gray-700 truncate" x-text="f.name"></span>
                            <span class="text-gray-400 shrink-0" x-text="formatBytes(f.size)"></span>
                        </div>
                        <button @click="uploadFiles.splice(idx, 1)" class="text-gray-400 hover:text-red-500 shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>
            </div>
            {{-- Progress --}}
            <div x-show="uploading" class="mt-3">
                <div class="w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-indigo-600 h-1.5 rounded-full transition-all" :style="`width: ${uploadProgress}%`"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1 text-center" x-text="`Uploading... ${uploadProgress}%`"></p>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button @click="modals.upload = false; uploadFiles = [];" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition">Hủy</button>
                <button @click="uploadFilesToServer()" :disabled="uploadFiles.length === 0 || uploading"
                        class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                    Upload <span x-show="uploadFiles.length > 0" x-text="`(${uploadFiles.length})`"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Delete Confirm Modal --}}
    <div x-show="modals.delete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center modal-backdrop" @click.self="modals.delete = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 animate-in">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-800">Xác nhận xóa</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Bạn có chắc muốn xóa "<span x-text="deleteTarget?.name" class="font-medium text-gray-700"></span>"?</p>
                </div>
            </div>
            <p x-show="deleteTarget?.is_folder" class="text-xs text-orange-600 bg-orange-50 rounded-lg px-3 py-2 mb-4">Thư mục và toàn bộ nội dung bên trong sẽ bị xóa vĩnh viễn.</p>
            <div class="flex justify-end gap-2">
                <button @click="modals.delete = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition">Hủy</button>
                <button @click="deleteItem()" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">Xóa</button>
            </div>
        </div>
    </div>

    {{-- Preview Modal --}}
    <div x-show="modals.preview" x-cloak class="fixed inset-0 z-50 flex items-center justify-center modal-backdrop" @click.self="modals.preview = false" @keydown.escape.window="modals.preview = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[85vh] flex flex-col animate-in">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between shrink-0">
                <h3 class="text-sm font-semibold text-gray-700 truncate" x-text="previewItem?.name"></h3>
                <button @click="modals.preview = false" class="p-1 rounded hover:bg-gray-100 text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-auto p-5 flex items-center justify-center bg-gray-50">
                <template x-if="previewItem && isImage(previewItem.type)">
                    <img :src="previewItem.file_url" class="max-w-full max-h-[70vh] object-contain rounded-lg shadow-sm">
                </template>
                <template x-if="previewItem && isVideo(previewItem.type)">
                    <video :src="previewItem.file_url" controls class="max-w-full max-h-[70vh] rounded-lg shadow-sm"></video>
                </template>
                <template x-if="previewItem && isPdf(previewItem.type)">
                    <iframe :src="previewItem.file_url" class="w-full h-[70vh] rounded-lg border"></iframe>
                </template>
                <template x-if="previewItem && !isImage(previewItem.type) && !isVideo(previewItem.type) && !isPdf(previewItem.type)">
                    <div class="text-center text-gray-400">
                        <div class="w-20 h-20 rounded-2xl flex items-center justify-center text-white text-2xl font-bold uppercase mx-auto mb-4"
                             :class="getFileColor(previewItem?.type)" x-text="previewItem?.type || '?'"></div>
                        <p class="text-sm">Không thể xem trước file này</p>
                        <button @click="downloadFile(previewItem.id)" class="mt-3 px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition">Tải xuống</button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div x-show="toast.show" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl shadow-lg text-sm font-medium flex items-center gap-2"
         :class="{
            'bg-green-600 text-white': toast.type === 'success',
            'bg-red-600 text-white': toast.type === 'error',
            'bg-gray-800 text-white': toast.type === 'info'
         }">
        <span x-text="toast.message"></span>
    </div>

    <script>
    function fileManager() {
        return {
            // State
            items: [],
            folderTree: [],
            uploadDates: [],
            breadcrumb: [{ id: null, name: 'Root' }],
            currentFolder: null,
            selectedItems: [],
            viewMode: 'large',
            showHidden: false,
            searchQuery: '',
            loading: false,
            dragOver: false,
            stats: null,
            infoPanel: null,

            // Context menu
            contextMenu: { show: false, x: 0, y: 0, item: null },

            // Modals
            modals: { newFolder: false, rename: false, upload: false, delete: false, preview: false },

            // New folder
            newFolderName: '',
            newFolderParent: null,

            // Rename
            renameId: null,
            renameName: '',

            // Upload
            uploadFiles: [],
            uploading: false,
            uploadProgress: 0,
            uploadDragOver: false,

            // Delete
            deleteTarget: null,

            // Preview
            previewItem: null,

            // Toast
            toast: { show: false, message: '', type: 'success' },

            csrfToken: document.querySelector('meta[name="csrf-token"]').content,

            async init() {
                await Promise.all([this.loadFiles(), this.loadFolderTree(), this.loadStats(), this.loadUploadDates()]);
                document.addEventListener('click', () => { this.contextMenu.show = false; });
            },

            async apiFetch(url, options = {}) {
                const defaultHeaders = {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                };
                if (!(options.body instanceof FormData)) {
                    defaultHeaders['Content-Type'] = 'application/json';
                }
                const res = await fetch(url, {
                    ...options,
                    headers: { ...defaultHeaders, ...options.headers },
                });
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    throw new Error(err.error || err.message || 'Request failed');
                }
                return res.json();
            },

            async loadFiles() {
                this.loading = true;
                try {
                    const params = new URLSearchParams();
                    if (typeof this.currentFolder === 'string' && this.currentFolder.startsWith('date_')) {
                        params.set('date', this.currentFolder.replace('date_', ''));
                    } else if (this.currentFolder) {
                        params.set('parent_id', this.currentFolder);
                    }
                    if (this.showHidden) params.set('show_hidden', '1');
                    if (this.searchQuery) params.set('search', this.searchQuery);

                    const data = await this.apiFetch(`/fm/files?${params}`);
                    this.items = data.items;
                    this.breadcrumb = data.breadcrumb;
                } catch (e) {
                    this.showToast('Lỗi tải dữ liệu: ' + e.message, 'error');
                } finally {
                    this.loading = false;
                }
            },

            async loadFolderTree() {
                try {
                    this.folderTree = await this.apiFetch('/fm/folder-tree');
                } catch (e) { /* silent */ }
            },

            async loadUploadDates() {
                try {
                    const params = new URLSearchParams();
                    if (this.showHidden) params.set('show_hidden', '1');
                    this.uploadDates = await this.apiFetch(`/fm/upload-dates?${params}`);
                } catch (e) { /* silent */ }
            },

            async loadStats() {
                try {
                    this.stats = await this.apiFetch('/fm/stats');
                } catch (e) { /* silent */ }
            },

            treeRoots() {
                return []; // Using template-based tree instead
            },

            navigateTo(folderId) {
                this.currentFolder = folderId;
                this.selectedItems = [];
                this.infoPanel = null;
                this.searchQuery = '';
                this.loadFiles();
            },

            navigateToDate(date) {
                this.navigateTo('date_' + date);
            },

            selectItem(item, event) {
                if (event.ctrlKey || event.metaKey) {
                    const idx = this.selectedItems.indexOf(item.id);
                    if (idx > -1) this.selectedItems.splice(idx, 1);
                    else this.selectedItems.push(item.id);
                } else {
                    this.selectedItems = [item.id];
                }
                if (item.is_date_folder) {
                    this.infoPanel = item;
                } else {
                    this.showInfo(item.id);
                }
            },

            onContextMenu(event, item, isTree) {
                this.contextMenu = {
                    show: true,
                    x: Math.min(event.clientX, window.innerWidth - 220),
                    y: Math.min(event.clientY, window.innerHeight - 300),
                    item: item,
                };
                if (item) {
                    this.selectedItems = [item.id];
                }
            },

            // Folder operations
            openNewFolderModal(parentId) {
                this.newFolderParent = parentId;
                this.newFolderName = '';
                this.modals.newFolder = true;
                this.$nextTick(() => { this.$refs.newFolderInput?.focus(); });
            },

            async createFolder() {
                if (!this.newFolderName.trim()) return;
                try {
                    await this.apiFetch('/fm/folder', {
                        method: 'POST',
                        body: JSON.stringify({ name: this.newFolderName, parent_id: this.newFolderParent }),
                    });
                    this.modals.newFolder = false;
                    this.showToast('Đã tạo thư mục "' + this.newFolderName + '"', 'success');
                    this.loadFiles();
                    this.loadFolderTree();
                    this.loadStats();
                } catch (e) {
                    this.showToast('Lỗi: ' + e.message, 'error');
                }
            },

            // Rename
            openRenameModal(item) {
                this.renameId = item.id;
                this.renameName = item.name;
                this.modals.rename = true;
                this.$nextTick(() => { this.$refs.renameInput?.focus(); this.$refs.renameInput?.select(); });
            },

            async renameItem() {
                if (!this.renameName.trim()) return;
                try {
                    await this.apiFetch(`/fm/rename/${this.renameId}`, {
                        method: 'PUT',
                        body: JSON.stringify({ name: this.renameName }),
                    });
                    this.modals.rename = false;
                    this.showToast('Đã đổi tên', 'success');
                    this.loadFiles();
                    this.loadFolderTree();
                    if (this.infoPanel?.id === this.renameId) {
                        this.infoPanel.name = this.renameName;
                    }
                } catch (e) {
                    this.showToast('Lỗi: ' + e.message, 'error');
                }
            },

            // Visibility
            async toggleVisibility(id) {
                try {
                    const data = await this.apiFetch(`/fm/toggle-visibility/${id}`, { method: 'PUT' });
                    this.showToast(data.is_hidden ? 'Đã ẩn file' : 'Đã hiện file', 'success');
                    this.loadFiles();
                    this.loadStats();
                    if (this.infoPanel?.id === id) {
                        this.infoPanel.is_hidden = data.is_hidden;
                    }
                } catch (e) {
                    this.showToast('Lỗi: ' + e.message, 'error');
                }
            },

            // Delete
            confirmDelete(item) {
                this.deleteTarget = item;
                this.modals.delete = true;
            },

            async deleteItem() {
                if (!this.deleteTarget) return;
                try {
                    await this.apiFetch(`/fm/delete/${this.deleteTarget.id}`, { method: 'DELETE' });
                    this.modals.delete = false;
                    this.showToast('Đã xóa "' + this.deleteTarget.name + '"', 'success');
                    if (this.infoPanel?.id === this.deleteTarget.id) this.infoPanel = null;
                    this.selectedItems = this.selectedItems.filter(id => id !== this.deleteTarget.id);
                    this.deleteTarget = null;
                    this.loadFiles();
                    this.loadFolderTree();
                    this.loadStats();
                    this.loadUploadDates();
                } catch (e) {
                    this.showToast('Lỗi: ' + e.message, 'error');
                }
            },

            // Upload
            openUploadModal() {
                this.uploadFiles = [];
                this.uploading = false;
                this.uploadProgress = 0;
                this.modals.upload = true;
            },

            handleFileSelect(event) {
                const files = Array.from(event.target.files);
                this.uploadFiles.push(...files);
            },

            handleUploadDrop(event) {
                this.uploadDragOver = false;
                const files = Array.from(event.dataTransfer.files);
                this.uploadFiles.push(...files);
            },

            handleDrop(event) {
                this.dragOver = false;
                const files = Array.from(event.dataTransfer.files);
                if (files.length > 0) {
                    this.uploadFiles = files;
                    this.uploadFilesToServer();
                }
            },

            async uploadFilesToServer() {
                if (this.uploadFiles.length === 0) return;
                this.uploading = true;
                this.uploadProgress = 0;

                const formData = new FormData();
                this.uploadFiles.forEach(f => formData.append('files[]', f));
                if (this.currentFolder) formData.append('parent_id', this.currentFolder);

                try {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '/fm/upload');
                    xhr.setRequestHeader('X-CSRF-TOKEN', this.csrfToken);
                    xhr.setRequestHeader('Accept', 'application/json');

                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                        }
                    };

                    await new Promise((resolve, reject) => {
                        xhr.onload = () => {
                            if (xhr.status >= 200 && xhr.status < 300) resolve(JSON.parse(xhr.responseText));
                            else reject(new Error('Upload failed'));
                        };
                        xhr.onerror = () => reject(new Error('Network error'));
                        xhr.send(formData);
                    });

                    this.showToast(`Đã upload ${this.uploadFiles.length} file`, 'success');
                    this.uploadFiles = [];
                    this.modals.upload = false;
                    this.loadFiles();
                    this.loadStats();
                    this.loadUploadDates();
                } catch (e) {
                    this.showToast('Lỗi upload: ' + e.message, 'error');
                } finally {
                    this.uploading = false;
                }
            },

            // Info
            async showInfo(id) {
                try {
                    this.infoPanel = await this.apiFetch(`/fm/info/${id}`);
                } catch (e) { /* silent */ }
            },

            // Download
            downloadFile(id) {
                window.open(`/fm/download/${id}`, '_blank');
            },

            // Preview
            previewFile(item) {
                this.previewItem = item;
                this.modals.preview = true;
            },

            // Helpers
            isImage(type) { return ['jpg','jpeg','png','gif','bmp','webp','svg','ico'].includes((type||'').toLowerCase()); },
            isVideo(type) { return ['mp4','webm','ogg','mov','avi'].includes((type||'').toLowerCase()); },
            isPdf(type) { return (type||'').toLowerCase() === 'pdf'; },

            getFileColor(type) {
                const t = (type||'').toLowerCase();
                const map = {
                    pdf: 'bg-red-500', doc: 'bg-blue-600', docx: 'bg-blue-600',
                    xls: 'bg-green-600', xlsx: 'bg-green-600', csv: 'bg-green-500',
                    ppt: 'bg-orange-500', pptx: 'bg-orange-500',
                    zip: 'bg-yellow-600', rar: 'bg-yellow-600', '7z': 'bg-yellow-600',
                    jpg: 'bg-pink-500', jpeg: 'bg-pink-500', png: 'bg-pink-500', gif: 'bg-pink-400', webp: 'bg-pink-500', svg: 'bg-pink-400',
                    mp4: 'bg-purple-500', mov: 'bg-purple-500', avi: 'bg-purple-500', webm: 'bg-purple-500',
                    mp3: 'bg-cyan-500', wav: 'bg-cyan-500',
                    js: 'bg-yellow-500', ts: 'bg-blue-500', py: 'bg-blue-400', php: 'bg-indigo-500',
                    html: 'bg-orange-600', css: 'bg-blue-400', json: 'bg-gray-600',
                    txt: 'bg-gray-400', md: 'bg-gray-500',
                };
                return map[t] || 'bg-gray-500';
            },

            formatBytes(bytes) {
                if (!bytes) return '0 B';
                const u = ['B','KB','MB','GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(1024));
                return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + u[i];
            },

            copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(() => {
                    this.showToast('Đã copy link', 'info');
                });
            },

            showToast(message, type = 'success') {
                this.toast = { show: true, message, type };
                setTimeout(() => { this.toast.show = false; }, 3000);
            },
        };
    }
    </script>
</body>
</html>
