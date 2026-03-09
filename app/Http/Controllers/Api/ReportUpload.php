<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportUpload as ReportUploadModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ReportUpload extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            if (!$request->hasFile('files')) {
                return response()->json(['error' => 'No file uploaded under key "files"'], 400);
            }

            $file = $request->file('files');

            // Handle array of files (if sent as files[])
            if (is_array($file)) {
                $file = $file[0];
            }

            $original_name = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $date = Carbon::today()->format('d-m-Y');

            // Generate unique filename: name_timestamp_random.ext
            $unique_name = pathinfo($original_name, PATHINFO_FILENAME) . '_' . time() . '_' . Str::random(5) . '.' . $extension;

            // Save to storage
            $path = Storage::putFileAs("public/report/" . $date, $file, $unique_name);
            $link_file = URL::to('/') . Storage::url('report/' . $date . '/' . $unique_name);

            // Get file type (extension)
            $type = $extension;

            // Save to database
            $reportUpload = ReportUploadModel::create([
                'name' => $unique_name,
                'original_name' => $original_name,
                'file_path' => $path,
                'file_url' => $link_file,
                'token' => $request->token,
                'type' => $type,
            ]);

            return response()->json([
                'id' => $reportUpload->id,
                'name' => $reportUpload->original_name, // User friendly name
                'original_name' => $reportUpload->original_name,
                'unique_name' => $reportUpload->name,
                'file_path' => $reportUpload->file_path,
                'file_url' => $reportUpload->file_url,
                'path' => $path, // legacy support
                'downloadLink' => $link_file, // legacy support
                'token' => $reportUpload->token,
                'type' => $reportUpload->type,
            ]);
        } catch (\Exception $e) {
            \Log::error('Upload failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    public function getFile(Request $request)
    {
        $link = asset('storage/report/' . $request->path);
        return response()->json($link);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
