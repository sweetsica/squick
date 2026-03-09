<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium File Upload</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.10.1/sweetalert2.all.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #0f172a; color: #f8fafc; }
        .glass { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .drop-zone { transition: all 0.3s ease; border: 2px dashed rgba(255, 255, 255, 0.2); }
        .drop-zone--over { border-color: #3b82f6; background: rgba(59, 130, 246, 0.1); transform: scale(1.02); }
        [v-cloak] { display: none; }
    </style>
    <script>
        console.log('Upload page scripts initializing...');
        function notify(type, title, text) {
            console.log(`Notification [${type}]: ${title} - ${text}`);
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: type, title: title, text: text, timer: type === 'success' ? 2000 : null });
            } else {
                alert(`${type.toUpperCase()}: ${title}\n${text}`);
            }
        }
        
        // Check for Swal fallback
        if (typeof Swal === 'undefined') {
            console.warn('SweetAlert2 failed to load from primary CDN. Attempting fallback...');
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/sweetalert2@11';
            document.head.appendChild(script);
        }
    </script>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="max-w-xl w-full glass p-8 rounded-3xl shadow-2xl">
        <h1 class="text-3xl font-bold text-center mb-2 bg-gradient-to-r from-blue-400 to-purple-500 bg-clip-text text-transparent">Upload Your Files</h1>
        <p class="text-slate-400 text-center mb-8">Drag & drop your files here or click to browse</p>

        <div id="drop-zone" class="drop-zone rounded-2xl p-12 flex flex-col items-center justify-center cursor-pointer group">
            <svg class="w-16 h-16 text-slate-500 group-hover:text-blue-500 transition-colors mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            <span class="text-slate-300 font-medium">Click or Drag & Drop</span>
            <input type="file" id="file-input" multiple class="hidden">
        </div>

        <div id="file-list" class="mt-8 space-y-4"></div>

        <button id="upload-btn" class="w-full mt-8 py-4 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-500 hover:to-purple-500 text-white font-bold rounded-xl transition-all shadow-lg active:scale-95 hidden">
            Start Uploading
        </button>
        
        <div id="debug-info" class="mt-4 p-2 text-xs text-slate-500 font-mono break-all hidden border-t border-slate-800"></div>
    </div>

    <script>
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const fileList = document.getElementById('file-list');
        const uploadBtn = document.getElementById('upload-btn');
        const debugInfo = document.getElementById('debug-info');
        let selectedFiles = [];

        function logDebug(msg) {
            console.log(msg);
            debugInfo.classList.remove('hidden');
            const p = document.createElement('p');
            p.textContent = `> ${msg}`;
            debugInfo.appendChild(p);
        }

        dropZone.addEventListener('click', () => {
            logDebug('Drop zone clicked, opening file input');
            fileInput.click();
        });

        fileInput.addEventListener('change', (e) => {
            logDebug(`File input changed: ${e.target.files.length} files selected`);
            handleFiles(e.target.files);
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drop-zone--over');
        });

        ['dragleave', 'dragend'].forEach(type => {
            dropZone.addEventListener(type, () => dropZone.classList.remove('drop-zone--over'));
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drop-zone--over');
            logDebug(`Files dropped: ${e.dataTransfer.files.length} files`);
            handleFiles(e.dataTransfer.files);
        });

        function handleFiles(files) {
            selectedFiles = [...selectedFiles, ...Array.from(files)];
            logDebug(`Selected files updated. Total: ${selectedFiles.length}`);
            updateFileList();
            if (selectedFiles.length > 0) uploadBtn.classList.remove('hidden');
        }

        function updateFileList() {
            fileList.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'glass p-4 rounded-xl flex items-center justify-between animate-fade-in';
                item.innerHTML = `
                    <div class="flex items-center space-x-4 flex-1">
                        <div class="p-2 bg-blue-500/20 rounded-lg">
                            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate">${file.name}</p>
                            <div class="w-full bg-slate-700 h-1.5 rounded-full mt-2 overflow-hidden">
                                <div id="progress-${index}" class="bg-blue-500 h-full w-0 transition-all duration-300"></div>
                            </div>
                        </div>
                        <button onclick="removeFile(${index})" class="text-slate-500 hover:text-red-500 p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                `;
                fileList.appendChild(item);
            });
        }

        function removeFile(index) {
            logDebug(`Removing file at index ${index}`);
            selectedFiles.splice(index, 1);
            updateFileList();
            if (selectedFiles.length === 0) uploadBtn.classList.add('hidden');
        }

        uploadBtn.addEventListener('click', async () => {
            logDebug('Upload button clicked. Starting loop.');
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<span class="animate-pulse">Uploading...</span>';
            const uploadedData = [];

            for (let i = 0; i < selectedFiles.length; i++) {
                try {
                    logDebug(`Uploading file ${i+1}/${selectedFiles.length}: ${selectedFiles[i].name}`);
                    const result = await uploadFile(selectedFiles[i], i);
                    logDebug(`Upload success for ${selectedFiles[i].name}`);
                    uploadedData.push(result);
                } catch (error) {
                    logDebug(`Upload failed for ${selectedFiles[i].name}: ${error}`);
                    notify('error', 'Upload Failed', `Failed to upload ${selectedFiles[i].name}`);
                }
            }

            if (uploadedData.length > 0) {
                logDebug(`All files processed. Uploaded count: ${uploadedData.length}. Preparing redirect.`);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'All files uploaded successfully',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => performRedirect(uploadedData));
                } else {
                    alert('Success! All files uploaded. Redirecting...');
                    performRedirect(uploadedData);
                }
            } else {
                logDebug('No files were successfully uploaded.');
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = 'Start Uploading';
            }
        });

        function performRedirect(uploadedData) {
            logDebug('Performing POST redirect to result page');
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("result") }}';
            
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);

            uploadedData.forEach((data, index) => {
                logDebug(`Adding data for file ${index} to form`);
                Object.keys(data).forEach(key => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `files[${index}][${key}]`;
                    input.value = data[key];
                    form.appendChild(input);
                });
                // Add size manually
                const sizeInput = document.createElement('input');
                sizeInput.type = 'hidden';
                sizeInput.name = `files[${index}][size]`;
                sizeInput.value = (selectedFiles[index].size / 1024).toFixed(2) + ' KB';
                form.appendChild(sizeInput);
            });

            document.body.appendChild(form);
            form.submit();
        }

        function uploadFile(file, index) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                const formData = new FormData();
                formData.append('files', file);
                formData.append('_token', '{{ csrf_token() }}');

                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percent = (e.loaded / e.total) * 100;
                        document.getElementById(`progress-${index}`).style.width = percent + '%';
                    }
                });

                xhr.onreadystatechange = () => {
                    if (xhr.readyState === 4) {
                        logDebug(`XHR ReadyState 4. Status: ${xhr.status}`);
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                resolve(response);
                            } catch (e) {
                                reject('Invalid JSON response from server');
                            }
                        } else {
                            reject(`Server returned status ${xhr.status}: ${xhr.statusText}`);
                        }
                    }
                };

                xhr.onerror = () => {
                    logDebug('XHR Error occurred');
                    reject('Network error or request blocked');
                };

                xhr.open('POST', '{{ url("/upload") }}', true);
                xhr.send(formData);
            });
        }
    </script>
</body>
</html>