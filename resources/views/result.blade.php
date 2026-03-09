<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Results</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #0f172a;
            color: #f8fafc;
        }

        .glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .qr-card {
            transition: all 0.3s ease;
        }

        .qr-card:hover {
            transform: translateY(-5px);
            border-color: #3b82f6;
        }

        .qrcode canvas,
        .qrcode img {
            margin: 0 auto;
            padding: 10px;
            background: white;
            border-radius: 12px;
        }
    </style>
</head>

<body class="min-h-screen p-4 md:p-12">
    <div class="max-w-6xl mx-auto">
        <header
            class="mb-12 text-center md:text-left flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <h1
                    class="text-4xl font-bold bg-gradient-to-r from-blue-400 to-purple-500 bg-clip-text text-transparent">
                    Upload Results</h1>
                <p class="text-slate-400 mt-2">Here are your uploaded files and their access links</p>
            </div>
            <a href="{{ route('upload') }}"
                class="inline-flex items-center justify-center px-6 py-3 bg-slate-800 hover:bg-slate-700 text-white rounded-xl transition-all border border-slate-700">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" />
                </svg>
                Upload More
            </a>
        </header>

        @if(empty($files))
            <div class="glass p-12 rounded-3xl text-center">
                <p class="text-slate-500 text-lg">No files found. Please upload something first.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($files as $index => $file)
                    <div class="glass p-6 rounded-3xl qr-card border border-transparent">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-blue-500/20 rounded-2xl">
                                <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span class="px-3 py-1 bg-slate-800 rounded-full text-[10px] font-bold text-blue-400 uppercase tracking-widest border border-slate-700">
                                {{ $file['type'] ?? 'FILE' }}
                            </span>
                        </div>
                        
                        <div class="space-y-4 mb-6">
                            <div>
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">File Name</label>
                                <p class="text-sm font-bold truncate text-slate-200" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                            </div>
                            
                            <div class="flex justify-between gap-4">
                                <div class="flex-1">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Type</label>
                                    <p class="text-xs font-medium text-slate-300">{{ strtoupper($file['type'] ?? 'Unknown') }}</p>
                                </div>
                                <div class="flex-1">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Size</label>
                                    <p class="text-xs font-medium text-slate-300">{{ $file['size'] ?? 'N/A' }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Download URL</label>
                                <div class="flex items-center gap-2">
                                    <p class="text-[10px] font-mono text-blue-400/80 truncate flex-1">{{ $file['file_url'] }}</p>
                                    <button onclick="copyLink('{{ $file['file_url'] }}', this)" class="p-1.5 hover:bg-slate-700 rounded-lg transition-colors text-slate-400 hover:text-white" title="Copy URL">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" /></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="qrcode-{{ $index }}" class="qrcode mb-6 flex justify-center"></div>

                        <div class="space-y-3">
                            <a href="{{ $file['file_url'] }}" target="_blank"
                                class="block w-full text-center py-3 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl transition-all shadow-lg active:scale-95">
                                Download File
                            </a>
                        </div>

                        <script>
                            new QRCode(document.getElementById("qrcode-{{ $index }}"), {
                                text: "{{ $file['file_url'] }}",
                                width: 140,
                                height: 140,
                                colorDark: "#000000",
                                colorLight: "#ffffff",
                                correctLevel: QRCode.CorrectLevel.H
                            });
                        </script>
                    </div>
                @endforeach
            </div>

            <script>
                function copyLink(url, btn) {
                    const originalInfo = btn.innerHTML;
                    const success = () => {
                        btn.innerHTML = '<svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" /></svg>';
                        setTimeout(() => btn.innerHTML = originalInfo, 2000);
                    };

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).then(success).catch(() => fallbackCopy(url, success));
                    } else {
                        fallbackCopy(url, success);
                    }
                }

                function fallbackCopy(text, cb) {
                    const textArea = document.createElement("textarea");
                    textArea.value = text;
                    textArea.style.position = "fixed";
                    textArea.style.left = "-9999px";
                    textArea.style.top = "0";
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        cb();
                    } catch (err) {
                        console.error('Fallback copy failed', err);
                    }
                    document.body.removeChild(textArea);
                }
            </script>
        @endif

        <footer class="mt-20 text-center text-slate-600 border-t border-slate-800 pt-8">
            <p>&copy; 2026 Premium Upload Service. All rights reserved.</p>
        </footer>
    </div>
</body>

</html>