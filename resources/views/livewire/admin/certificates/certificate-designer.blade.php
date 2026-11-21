<div class="h-[calc(100vh-4rem)] flex flex-col -m-6 bg-slate-900 text-slate-100 font-sans overflow-hidden"
     x-data="certificateDesigner(@entangle('designData'), @entangle('selectedElementId'), @entangle('zoom'), @entangle('showGrid'), @entangle('snapToGrid'))">

    {{-- Top Navigation & Action Toolbar --}}
    <header class="h-14 border-b border-slate-800 bg-slate-950 px-4 flex items-center justify-between shrink-0 z-20">
        {{-- Left: Back & Template Selector --}}
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.certificates.index') }}"
               class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition"
               title="Back to Certificate Management">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>

            <div class="h-5 w-px bg-slate-800"></div>

            <div class="flex items-center space-x-2">
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open"
                            type="button"
                            class="flex items-center space-x-2 px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 border border-slate-700 text-sm font-semibold text-white transition">
                        <span class="max-w-[200px] truncate">{{ $template?->name ?? 'Select Template' }}</span>
                        @if($isDefault)
                            <span class="px-1.5 py-0.5 text-[10px] uppercase font-bold rounded bg-amber-500/20 text-amber-400 border border-amber-500/30">Default</span>
                        @endif
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open"
                         @click.away="open = false"
                         x-transition
                         class="absolute left-0 mt-2 w-72 rounded-xl bg-slate-900 border border-slate-800 shadow-2xl py-2 z-50 text-slate-200"
                         style="display: none;">
                        <div class="px-3 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-800 flex justify-between items-center">
                            <span>Academy Templates</span>
                            <button wire:click="$set('showNewTemplateModal', true); open = false" class="text-blue-400 hover:text-blue-300 normal-case font-medium text-xs flex items-center">
                                + New
                            </button>
                        </div>
                        <div class="max-h-60 overflow-y-auto py-1">
                            @foreach($templates as $t)
                                <button type="button"
                                        wire:click="loadTemplate('{{ $t->id }}'); open = false"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-slate-800 flex items-center justify-between {{ $t->id === $templateId ? 'bg-blue-600/20 text-blue-400 font-semibold' : '' }}">
                                    <span class="truncate">{{ $t->name }}</span>
                                    @if($t->is_default)
                                        <span class="text-[10px] bg-amber-500/20 text-amber-300 px-1 rounded">Default</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Center: Canvas Controls (Zoom, Grid, Placeholders Toggle) --}}
        <div class="flex items-center space-x-2">
            <div class="flex items-center bg-slate-900 border border-slate-800 rounded-lg p-0.5 text-xs text-slate-300">
                <button type="button" @click="zoomOut()" class="p-1.5 hover:bg-slate-800 rounded text-slate-400 hover:text-white" title="Zoom Out (Ctrl -)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                </button>
                <span class="px-2 font-mono font-medium" x-text="zoom + '%'">100%</span>
                <button type="button" @click="zoomIn()" class="p-1.5 hover:bg-slate-800 rounded text-slate-400 hover:text-white" title="Zoom In (Ctrl +)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </button>
                <button type="button" @click="resetZoom()" class="px-2 py-1 hover:bg-slate-800 rounded text-slate-400 hover:text-white" title="Reset Zoom">
                    Fit
                </button>
            </div>

            <div class="h-4 w-px bg-slate-800"></div>

            <button type="button"
                    @click="showGrid = !showGrid"
                    :class="showGrid ? 'bg-blue-600/30 text-blue-400 border-blue-500/40' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'"
                    class="p-1.5 rounded-lg border text-xs flex items-center space-x-1 transition"
                    title="Toggle Alignment Grid">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16M6 4v16M12 4v16M18 4v16"/></svg>
            </button>

            <button type="button"
                    @click="togglePreviewData()"
                    :class="showSampleData ? 'bg-emerald-600/30 text-emerald-400 border-emerald-500/40' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'"
                    class="px-2.5 py-1.5 rounded-lg border text-xs font-medium flex items-center space-x-1.5 transition"
                    title="Toggle between Placeholders and Realistic Sample Data">
                <span class="w-2 h-2 rounded-full" :class="showSampleData ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'"></span>
                <span x-text="showSampleData ? 'Sample Data ON' : 'Placeholders'">Placeholders</span>
            </button>
        </div>

        {{-- Right: Save, Publish, Preview, Actions --}}
        <div class="flex items-center space-x-2">
            <button wire:click="openPreviewModal"
                    type="button"
                    class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-medium flex items-center space-x-1.5 transition border border-slate-700">
                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span>Live PDF Preview</span>
            </button>

            <button wire:click="saveDraft"
                    type="button"
                    class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-medium transition border border-slate-700">
                Save Draft
            </button>

            <button wire:click="publishVersion"
                    type="button"
                    class="px-3.5 py-1.5 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white text-sm font-semibold shadow-lg shadow-blue-600/20 flex items-center space-x-1.5 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Publish</span>
            </button>

            {{-- More Actions Menu --}}
            <div class="relative" x-data="{ menuOpen: false }">
                <button @click="menuOpen = !menuOpen"
                        type="button"
                        class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                </button>
                <div x-show="menuOpen"
                     @click.away="menuOpen = false"
                     x-transition
                     class="absolute right-0 mt-2 w-56 rounded-xl bg-slate-900 border border-slate-800 shadow-2xl py-2 z-50 text-slate-200 text-sm"
                     style="display: none;">
                    <button wire:click="duplicateTemplate(); menuOpen = false" class="w-full text-left px-4 py-2 hover:bg-slate-800 flex items-center space-x-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span>Duplicate Template</span>
                    </button>
                    @if(! $isDefault)
                        <button wire:click="setAsDefault(); menuOpen = false" class="w-full text-left px-4 py-2 hover:bg-slate-800 flex items-center space-x-2">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            <span>Set as Default</span>
                        </button>
                    @endif
                    <button wire:click="$set('showVersionHistoryModal', true); menuOpen = false" class="w-full text-left px-4 py-2 hover:bg-slate-800 flex items-center space-x-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Version History</span>
                    </button>
                    <div class="h-px bg-slate-800 my-1"></div>
                    <div class="px-4 py-1.5 text-xs text-slate-400 font-semibold uppercase">Load Preset</div>
                    <button wire:click="loadPreset('classic'); menuOpen = false" class="w-full text-left px-4 py-1.5 text-xs hover:bg-slate-800">Classic Academic</button>
                    <button wire:click="loadPreset('modern'); menuOpen = false" class="w-full text-left px-4 py-1.5 text-xs hover:bg-slate-800">Modern Professional</button>
                    <button wire:click="loadPreset('gold'); menuOpen = false" class="w-full text-left px-4 py-1.5 text-xs hover:bg-slate-800">Gold Achievement</button>
                    <button wire:click="loadPreset('minimalist'); menuOpen = false" class="w-full text-left px-4 py-1.5 text-xs hover:bg-slate-800">Minimal Corporate</button>
                </div>
            </div>
        </div>
    </header>

    {{-- Main Workspace: 3-Panel Jotform Architecture --}}
    <div class="flex-1 flex overflow-hidden">

        {{-- LEFT PANEL: Elements & Dynamic Field Insertions --}}
        <aside class="w-72 bg-slate-950 border-r border-slate-800 flex flex-col shrink-0 z-10" x-data="{ activeTab: 'elements' }">
            {{-- Tab selector --}}
            <div class="flex border-b border-slate-800 bg-slate-900/50 p-1">
                <button type="button"
                        @click="activeTab = 'elements'"
                        :class="activeTab === 'elements' ? 'bg-slate-800 text-white font-semibold' : 'text-slate-400 hover:text-slate-200'"
                        class="flex-1 py-1.5 text-xs rounded-lg transition text-center">
                    Elements
                </button>
                <button type="button"
                        @click="activeTab = 'fields'"
                        :class="activeTab === 'fields' ? 'bg-slate-800 text-white font-semibold' : 'text-slate-400 hover:text-slate-200'"
                        class="flex-1 py-1.5 text-xs rounded-lg transition text-center">
                    Fields
                </button>
                <button type="button"
                        @click="activeTab = 'uploads'"
                        :class="activeTab === 'uploads' ? 'bg-slate-800 text-white font-semibold' : 'text-slate-400 hover:text-slate-200'"
                        class="flex-1 py-1.5 text-xs rounded-lg transition text-center">
                    Uploads
                </button>
            </div>

            {{-- Tab 1: Elements --}}
            <div x-show="activeTab === 'elements'" class="flex-1 overflow-y-auto p-3 space-y-4">
                {{-- Typography & Headings --}}
                <div>
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Text Blocks</h4>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="addElement('heading')" class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-blue-500/50 hover:bg-slate-800/80 text-left transition group">
                            <span class="block text-xs font-bold text-slate-200 group-hover:text-blue-400">Heading</span>
                            <span class="text-[10px] text-slate-500">Certificate Title</span>
                        </button>
                        <button type="button" wire:click="addElement('subtitle')" class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-blue-500/50 hover:bg-slate-800/80 text-left transition group">
                            <span class="block text-xs font-bold text-slate-200 group-hover:text-blue-400">Subtitle</span>
                            <span class="text-[10px] text-slate-500">Presented To...</span>
                        </button>
                        <button type="button" wire:click="addElement('student_name')" class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-blue-500/50 hover:bg-slate-800/80 text-left transition group">
                            <span class="block text-xs font-bold text-emerald-400 group-hover:text-emerald-300">Recipient</span>
                            <span class="text-[10px] text-slate-500">&#123;&#123;student_name&#125;&#125;</span>
                        </button>
                        <button type="button" wire:click="addElement('course_title')" class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-blue-500/50 hover:bg-slate-800/80 text-left transition group">
                            <span class="block text-xs font-bold text-blue-400 group-hover:text-blue-300">Course</span>
                            <span class="text-[10px] text-slate-500">&#123;&#123;course_title&#125;&#125;</span>
                        </button>
                        <button type="button" wire:click="addElement('text')" class="p-2.5 col-span-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-blue-500/50 hover:bg-slate-800/80 text-left transition group">
                            <span class="block text-xs font-bold text-slate-200 group-hover:text-blue-400">Custom Text Paragraph</span>
                            <span class="text-[10px] text-slate-500">Freeform paragraph with custom formatting</span>
                        </button>
                    </div>
                </div>

                {{-- Shapes & Lines --}}
                <div>
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Shapes & Dividers</h4>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="addElement('line')" class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-blue-500/50 hover:bg-slate-800/80 text-left transition">
                            <span class="block text-xs font-bold text-slate-200">Divider Line</span>
                            <span class="text-[10px] text-slate-500">Accent separator</span>
                        </button>
                        <button type="button" wire:click="addElement('rectangle')" class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-blue-500/50 hover:bg-slate-800/80 text-left transition">
                            <span class="block text-xs font-bold text-slate-200">Rectangle</span>
                            <span class="text-[10px] text-slate-500">Badge or block</span>
                        </button>
                        <button type="button" wire:click="addElement('circle')" class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-blue-500/50 hover:bg-slate-800/80 text-left transition">
                            <span class="block text-xs font-bold text-slate-200">Circle / Seal</span>
                            <span class="text-[10px] text-slate-500">Medallion shape</span>
                        </button>
                        <button type="button" wire:click="addElement('badge')" class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-blue-500/50 hover:bg-slate-800/80 text-left transition">
                            <span class="block text-xs font-bold text-amber-400">Gold Badge</span>
                            <span class="text-[10px] text-slate-500">Achievement seal</span>
                        </button>
                    </div>
                </div>

                {{-- Verification & Signatures --}}
                <div>
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Verification & Signatures</h4>
                    <div class="space-y-2">
                        <button type="button" wire:click="addElement('qr')" class="w-full p-2.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-blue-500/50 hover:bg-slate-800/80 text-left transition flex items-center space-x-3">
                            <div class="w-8 h-8 rounded bg-white p-1 shrink-0 flex items-center justify-center text-slate-900 font-mono text-[9px]">QR</div>
                            <div>
                                <span class="block text-xs font-bold text-slate-200">Verification QR Code</span>
                                <span class="text-[10px] text-slate-400">Direct scannable certificate link</span>
                            </div>
                        </button>
                        <button type="button" wire:click="addElement('signature')" class="w-full p-2.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-blue-500/50 hover:bg-slate-800/80 text-left transition flex items-center space-x-3">
                            <div class="w-8 h-8 rounded bg-slate-800 shrink-0 flex items-center justify-center text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </div>
                            <div>
                                <span class="block text-xs font-bold text-slate-200">Signatory Block</span>
                                <span class="text-[10px] text-slate-400">Instructor/Director name & title</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Tab 2: Dynamic Placeholders --}}
            <div x-show="activeTab === 'fields'" class="flex-1 overflow-y-auto p-3 space-y-2" style="display: none;">
                <p class="text-xs text-slate-400 mb-3">Click any placeholder to copy or insert it into the active text element.</p>
                @foreach($placeholders as $tag => $desc)
                    <div class="p-2 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 transition">
                        <div class="flex items-center justify-between">
                            <code class="text-xs font-mono font-bold text-blue-400 cursor-pointer"
                                  @click="insertPlaceholder('{{ $tag }}')"
                                  title="Click to insert into selected element">
                                {{ $tag }}
                            </code>
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ $tag }}')"
                                    class="text-[10px] text-slate-400 hover:text-white px-1.5 py-0.5 rounded bg-slate-800">
                                Copy
                            </button>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1">{{ $desc }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Tab 3: Uploads & Assets --}}
            <div x-show="activeTab === 'uploads'" class="flex-1 overflow-y-auto p-3 space-y-4" style="display: none;">
                <form wire:submit.prevent="uploadAsset">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Asset Role</label>
                            <select wire:model="assetType" class="w-full bg-slate-900 border border-slate-800 rounded-lg text-xs p-2 text-slate-200">
                                <option value="logo">Academy Logo</option>
                                <option value="signature">Digital Signature</option>
                                <option value="badge">Honor Badge / Seal</option>
                                <option value="background">Background Watermark</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Image File (PNG, JPG, SVG)</label>
                            <input type="file" wire:model="uploadedAsset" class="w-full text-xs text-slate-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-500">
                            @error('uploadedAsset') <span class="text-red-400 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                        <button type="submit" class="w-full py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-semibold transition">
                            Upload to Certificate
                        </button>
                    </div>
                </form>
            </div>
        </aside>

        {{-- CENTER PANEL: Interactive Canvas Area --}}
        <main class="flex-1 bg-slate-900 flex flex-col overflow-hidden relative">
            {{-- Notification flash message --}}
            @if(session('status'))
                <div x-data="{ show: true }"
                     x-show="show"
                     x-init="setTimeout(() => show = false, 4000)"
                     class="absolute top-3 left-1/2 -translate-x-1/2 z-40 px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-medium shadow-xl flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            {{-- Canvas Scroll Container --}}
            <div class="flex-1 overflow-auto p-12 flex items-center justify-center relative cursor-default"
                 @click.self="selectElement(null)"
                 id="canvas-viewport">

                {{-- The Certificate Canvas --}}
                <div id="certificate-canvas"
                     class="relative transition-transform duration-75 select-none shadow-2xl"
                     :style="getCanvasStyle()"
                     @click.self="selectElement(null)">

                    {{-- Optional Grid Background --}}
                    <div x-show="showGrid"
                         class="absolute inset-0 pointer-events-none z-0"
                         style="background-size: 20px 20px; background-image: linear-gradient(to right, rgba(255,255,255,0.06) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.06) 1px, transparent 1px);">
                    </div>

                    {{-- Outer Border Frame --}}
                    <template x-if="designData.canvas.borderWidth > 0">
                        <div class="absolute pointer-events-none z-1"
                             :style="getOuterBorderStyle()"></div>
                    </template>

                    {{-- Inner Border Frame --}}
                    <template x-if="designData.canvas.innerBorderWidth > 0">
                        <div class="absolute pointer-events-none z-1"
                             :style="getInnerBorderStyle()"></div>
                    </template>

                    {{-- Elements Render Loop --}}
                    <template x-for="(el, index) in designData.elements" :key="el.id">
                        <div :id="'el-' + el.id"
                             class="absolute transition-shadow group"
                             :class="{
                                 'ring-2 ring-blue-500 shadow-md': selectedElementId === el.id,
                                 'hover:ring-1 hover:ring-blue-400/50 cursor-move': selectedElementId !== el.id && !el.isLocked,
                                 'cursor-not-allowed': el.isLocked
                             }"
                             :style="getElementStyle(el)"
                             @mousedown="startDrag($event, el)"
                             @click.stop="selectElement(el.id)">

                            {{-- Text Element Content --}}
                            <template x-if="el.type === 'text'">
                                <div class="w-full h-full whitespace-pre-wrap select-none"
                                     :style="getTextStyle(el)"
                                     x-text="renderElementContent(el)">
                                </div>
                            </template>

                            {{-- Line Element --}}
                            <template x-if="el.type === 'line'">
                                <div class="w-full h-full flex items-center">
                                    <div class="w-full border-t"
                                         :style="'border-color: ' + (el.color || '#94a3b8') + '; border-width: ' + (el.borderWidth || 1) + 'px; border-style: ' + (el.borderStyle || 'solid') + ';'"></div>
                                </div>
                            </template>

                            {{-- Rectangle Shape --}}
                            <template x-if="el.type === 'rectangle'">
                                <div class="w-full h-full"
                                     :style="'background-color: ' + (el.backgroundColor || 'transparent') + '; border: ' + (el.borderWidth || 0) + 'px ' + (el.borderStyle || 'solid') + ' ' + (el.borderColor || 'transparent') + '; border-radius: ' + (el.borderRadius || 0) + 'px;'"></div>
                            </template>

                            {{-- Circle Shape --}}
                            <template x-if="el.type === 'circle'">
                                <div class="w-full h-full rounded-full"
                                     :style="'background-color: ' + (el.backgroundColor || 'transparent') + '; border: ' + (el.borderWidth || 0) + 'px ' + (el.borderStyle || 'solid') + ' ' + (el.borderColor || 'transparent') + ';'"></div>
                            </template>

                            {{-- QR Code Element --}}
                            <template x-if="el.type === 'qr'">
                                <div class="w-full h-full bg-white p-1 rounded flex items-center justify-center border border-slate-300">
                                    <img :src="sampleData.verification_qr" class="w-full h-full object-contain pointer-events-none" alt="QR Code">
                                </div>
                            </template>

                            {{-- Image / Signature / Badge --}}
                            <template x-if="el.type === 'image' || el.type === 'logo' || el.type === 'signature' || el.type === 'badge'">
                                <div class="w-full h-full flex items-center justify-center overflow-hidden">
                                    <template x-if="el.src">
                                        <img :src="el.src" class="w-full h-full object-contain pointer-events-none" alt="Asset">
                                    </template>
                                    <template x-if="!el.src">
                                        <div class="w-full h-full bg-slate-200 text-slate-500 text-[10px] flex items-center justify-center border border-dashed border-slate-400">
                                            <span x-text="el.type"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- Selection Bounding Box & 8 Resize Handles (when selected and not locked) --}}
                            <template x-if="selectedElementId === el.id && !el.isLocked">
                                <div class="absolute inset-0 pointer-events-none border border-blue-500">
                                    {{-- NW handle --}}
                                    <div class="absolute -top-1.5 -left-1.5 w-3 h-3 bg-white border-2 border-blue-600 rounded-xs pointer-events-auto cursor-nwse-resize"
                                         @mousedown.stop="startResize($event, el, 'nw')"></div>
                                    {{-- NE handle --}}
                                    <div class="absolute -top-1.5 -right-1.5 w-3 h-3 bg-white border-2 border-blue-600 rounded-xs pointer-events-auto cursor-nesw-resize"
                                         @mousedown.stop="startResize($event, el, 'ne')"></div>
                                    {{-- SE handle --}}
                                    <div class="absolute -bottom-1.5 -right-1.5 w-3 h-3 bg-white border-2 border-blue-600 rounded-xs pointer-events-auto cursor-nwse-resize"
                                         @mousedown.stop="startResize($event, el, 'se')"></div>
                                    {{-- SW handle --}}
                                    <div class="absolute -bottom-1.5 -left-1.5 w-3 h-3 bg-white border-2 border-blue-600 rounded-xs pointer-events-auto cursor-nesw-resize"
                                         @mousedown.stop="startResize($event, el, 'sw')"></div>
                                    {{-- N handle --}}
                                    <div class="absolute -top-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-white border-2 border-blue-600 rounded-xs pointer-events-auto cursor-ns-resize"
                                         @mousedown.stop="startResize($event, el, 'n')"></div>
                                    {{-- S handle --}}
                                    <div class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-white border-2 border-blue-600 rounded-xs pointer-events-auto cursor-ns-resize"
                                         @mousedown.stop="startResize($event, el, 's')"></div>
                                    {{-- W handle --}}
                                    <div class="absolute top-1/2 -left-1.5 -translate-y-1/2 w-3 h-3 bg-white border-2 border-blue-600 rounded-xs pointer-events-auto cursor-ew-resize"
                                         @mousedown.stop="startResize($event, el, 'w')"></div>
                                    {{-- E handle --}}
                                    <div class="absolute top-1/2 -right-1.5 -translate-y-1/2 w-3 h-3 bg-white border-2 border-blue-600 rounded-xs pointer-events-auto cursor-ew-resize"
                                         @mousedown.stop="startResize($event, el, 'e')"></div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </main>

        {{-- RIGHT PANEL: Properties Inspector --}}
        <aside class="w-80 bg-slate-950 border-l border-slate-800 flex flex-col shrink-0 z-10 overflow-y-auto">

            {{-- 1. If an Element is Selected --}}
            <template x-if="selectedElement">
                <div class="p-4 space-y-5">
                    {{-- Element Header & Quick Actions --}}
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                        <div>
                            <span class="text-xs font-bold text-white uppercase tracking-wider" x-text="selectedElement.type + ' Properties'"></span>
                            <span class="block text-[10px] text-slate-500 font-mono" x-text="selectedElement.id"></span>
                        </div>
                        <div class="flex items-center space-x-1">
                            <button type="button"
                                    @click="toggleLock(selectedElement.id)"
                                    class="p-1.5 rounded hover:bg-slate-800 text-slate-400 hover:text-white"
                                    :title="selectedElement.isLocked ? 'Unlock Element' : 'Lock Element'">
                                <svg x-show="!selectedElement.isLocked" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                <svg x-show="selectedElement.isLocked" class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                            </button>
                            <button type="button"
                                    @click="duplicate(selectedElement.id)"
                                    class="p-1.5 rounded hover:bg-slate-800 text-slate-400 hover:text-white"
                                    title="Duplicate (Ctrl+D)">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                            <button type="button"
                                    @click="deleteEl(selectedElement.id)"
                                    class="p-1.5 rounded hover:bg-red-500/20 text-slate-400 hover:text-red-400"
                                    title="Delete (Del)">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Content Input (for text elements) --}}
                    <template x-if="selectedElement.type === 'text'">
                        <div class="space-y-2">
                            <label class="block text-xs font-semibold text-slate-300">Text Content / Placeholders</label>
                            <textarea rows="3"
                                      x-model="selectedElement.content"
                                      class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2 text-xs text-white font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                        </div>
                    </template>

                    {{-- Typography Inspector (for text elements) --}}
                    <template x-if="selectedElement.type === 'text'">
                        <div class="space-y-3">
                            <label class="block text-xs font-semibold text-slate-300">Typography</label>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <span class="block text-[10px] text-slate-500 mb-1">Font Family</span>
                                    <select x-model="selectedElement.fontFamily" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                                        <option value="Helvetica">Helvetica</option>
                                        <option value="Georgia">Georgia (Serif)</option>
                                        <option value="Times-Roman">Times New Roman</option>
                                        <option value="Courier">Courier (Mono)</option>
                                        <option value="Arial">Arial</option>
                                    </select>
                                </div>
                                <div>
                                    <span class="block text-[10px] text-slate-500 mb-1">Font Size (pt)</span>
                                    <input type="number" x-model.number="selectedElement.fontSize" min="6" max="120" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <span class="block text-[10px] text-slate-500 mb-1">Weight</span>
                                    <select x-model="selectedElement.fontWeight" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                                        <option value="normal">Regular</option>
                                        <option value="bold">Bold</option>
                                        <option value="600">Semi-Bold</option>
                                        <option value="300">Light</option>
                                    </select>
                                </div>
                                <div>
                                    <span class="block text-[10px] text-slate-500 mb-1">Alignment</span>
                                    <select x-model="selectedElement.textAlign" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                                        <option value="center">Center</option>
                                        <option value="left">Left</option>
                                        <option value="right">Right</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 items-center">
                                <div>
                                    <span class="block text-[10px] text-slate-500 mb-1">Text Color</span>
                                    <div class="flex items-center space-x-1.5">
                                        <input type="color" x-model="selectedElement.color" class="w-6 h-6 rounded border border-slate-700 bg-transparent cursor-pointer">
                                        <input type="text" x-model="selectedElement.color" class="flex-1 bg-slate-900 border border-slate-800 rounded text-xs p-1 text-white font-mono">
                                    </div>
                                </div>
                                <div>
                                    <span class="block text-[10px] text-slate-500 mb-1">Letter Spacing</span>
                                    <input type="text" x-model="selectedElement.letterSpacing" placeholder="e.g. 2px" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Geometry & Positioning --}}
                    <div class="space-y-3">
                        <label class="block text-xs font-semibold text-slate-300">Position & Dimensions</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="block text-[10px] text-slate-500 mb-1">X (pt)</span>
                                <input type="number" x-model.number="selectedElement.x" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-500 mb-1">Y (pt)</span>
                                <input type="number" x-model.number="selectedElement.y" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-500 mb-1">Width (pt)</span>
                                <input type="number" x-model.number="selectedElement.width" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-500 mb-1">Height (pt)</span>
                                <input type="number" x-model.number="selectedElement.height" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                            </div>
                        </div>
                    </div>

                    {{-- Alignment & Layering Shortcuts --}}
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-slate-300">Align to Canvas</label>
                        <div class="grid grid-cols-3 gap-1.5">
                            <button type="button" @click="align('left')" class="p-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 rounded text-[11px] text-slate-300">Left</button>
                            <button type="button" @click="align('center')" class="p-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 rounded text-[11px] text-slate-300">Center</button>
                            <button type="button" @click="align('right')" class="p-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 rounded text-[11px] text-slate-300">Right</button>
                            <button type="button" @click="align('top')" class="p-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 rounded text-[11px] text-slate-300">Top</button>
                            <button type="button" @click="align('middle')" class="p-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 rounded text-[11px] text-slate-300">Middle</button>
                            <button type="button" @click="align('bottom')" class="p-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 rounded text-[11px] text-slate-300">Bottom</button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-slate-300">Layer Order</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="reorder('bring_to_front')" class="p-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 rounded text-xs text-slate-300">Bring to Front</button>
                            <button type="button" @click="reorder('send_to_back')" class="p-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 rounded text-xs text-slate-300">Send to Back</button>
                        </div>
                    </div>
                </div>
            </template>

            {{-- 2. If Canvas Background is Selected (Default state) --}}
            <template x-if="!selectedElement">
                <div class="p-4 space-y-5">
                    <div class="pb-3 border-b border-slate-800">
                        <span class="text-xs font-bold text-white uppercase tracking-wider">Page & Frame Settings</span>
                        <span class="block text-[10px] text-slate-500">Configure page geometry & border styling</span>
                    </div>

                    {{-- Page Size & Orientation --}}
                    <div class="space-y-3">
                        <label class="block text-xs font-semibold text-slate-300">Page Setup</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="block text-[10px] text-slate-500 mb-1">Paper Size</span>
                                <select x-model="designData.canvas.pageSize" @change="updatePaperSize()" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                                    <option value="A4">A4 (842 x 595 pt)</option>
                                    <option value="Letter">US Letter (792 x 612 pt)</option>
                                </select>
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-500 mb-1">Orientation</span>
                                <select x-model="designData.canvas.orientation" @change="updatePaperSize()" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                                    <option value="landscape">Landscape</option>
                                    <option value="portrait">Portrait</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <span class="block text-[10px] text-slate-500 mb-1">Background Color</span>
                            <div class="flex items-center space-x-1.5">
                                <input type="color" x-model="designData.canvas.backgroundColor" class="w-6 h-6 rounded border border-slate-700 bg-transparent cursor-pointer">
                                <input type="text" x-model="designData.canvas.backgroundColor" class="flex-1 bg-slate-900 border border-slate-800 rounded text-xs p-1 text-white font-mono">
                            </div>
                        </div>
                    </div>

                    {{-- Outer Border Styling --}}
                    <div class="space-y-3">
                        <label class="block text-xs font-semibold text-slate-300">Outer Border Frame</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="block text-[10px] text-slate-500 mb-1">Thickness (pt)</span>
                                <input type="number" x-model.number="designData.canvas.borderWidth" min="0" max="30" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-500 mb-1">Style</span>
                                <select x-model="designData.canvas.borderStyle" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                                    <option value="solid">Solid</option>
                                    <option value="double">Double</option>
                                    <option value="dashed">Dashed</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="block text-[10px] text-slate-500 mb-1">Inset (pt)</span>
                                <input type="number" x-model.number="designData.canvas.borderInset" min="0" max="60" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-500 mb-1">Color</span>
                                <div class="flex items-center space-x-1">
                                    <input type="color" x-model="designData.canvas.borderColor" class="w-6 h-6 rounded border border-slate-700 bg-transparent cursor-pointer">
                                    <input type="text" x-model="designData.canvas.borderColor" class="flex-1 bg-slate-900 border border-slate-800 rounded text-xs p-1 text-white font-mono">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Inner Border Accent Styling --}}
                    <div class="space-y-3">
                        <label class="block text-xs font-semibold text-slate-300">Inner Border Accent</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="block text-[10px] text-slate-500 mb-1">Thickness (pt)</span>
                                <input type="number" x-model.number="designData.canvas.innerBorderWidth" min="0" max="15" class="w-full bg-slate-900 border border-slate-800 rounded text-xs p-1.5 text-white">
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-500 mb-1">Color</span>
                                <div class="flex items-center space-x-1">
                                    <input type="color" x-model="designData.canvas.innerBorderColor" class="w-6 h-6 rounded border border-slate-700 bg-transparent cursor-pointer">
                                    <input type="text" x-model="designData.canvas.innerBorderColor" class="flex-1 bg-slate-900 border border-slate-800 rounded text-xs p-1 text-white font-mono">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </aside>
    </div>

    {{-- Live Preview Modal --}}
    @if($showPreviewModal)
        <div class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-5xl h-[85vh] flex flex-col shadow-2xl overflow-hidden">
                <div class="h-14 px-6 border-b border-slate-800 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <h3 class="text-base font-bold text-white">Live PDF Preview</h3>
                        <span class="text-xs text-slate-400">Generated with exact Dompdf printer coordinates</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        @if($template)
                            <a href="{{ route('admin.certificates.designer.download-pdf', $template->id) }}"
                               class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold transition flex items-center space-x-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                <span>Download PDF</span>
                            </a>
                        @endif
                        <button wire:click="$set('showPreviewModal', false)" class="p-1.5 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 bg-slate-950 p-4">
                    @if($previewPdfUrl)
                        <iframe src="{{ $previewPdfUrl }}" class="w-full h-full rounded-lg border border-slate-800" title="Certificate PDF Preview"></iframe>
                    @else
                        <div class="w-full h-full flex items-center justify-center text-slate-500 text-sm">
                            Rendering PDF preview...
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Version History Modal --}}
    @if($showVersionHistoryModal)
        <div class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-2xl flex flex-col shadow-2xl overflow-hidden">
                <div class="h-14 px-6 border-b border-slate-800 flex items-center justify-between">
                    <h3 class="text-base font-bold text-white">Version History & Rollback</h3>
                    <button wire:click="$set('showVersionHistoryModal', false)" class="p-1.5 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 max-h-[60vh] overflow-y-auto space-y-3">
                    @foreach($versions as $v)
                        <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm font-bold text-white">Version {{ $v->version_number }}</span>
                                    @if($v->status === 'published')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">Published</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-800 text-slate-400">Draft</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-400 mt-1">{{ $v->change_summary ?? 'No change notes recorded.' }}</div>
                                <div class="text-[10px] text-slate-500 mt-1">
                                    By {{ $v->author?->name ?? 'System' }} &bull; {{ $v->created_at->diffForHumans() }}
                                </div>
                            </div>
                            @if($v->version_number !== $template->currentVersion?->version_number)
                                <button type="button"
                                        wire:click="restoreVersion('{{ $v->id }}')"
                                        class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-semibold rounded-lg border border-slate-700 transition">
                                    Restore Version
                                </button>
                            @else
                                <span class="text-xs text-blue-400 font-semibold px-2 py-1">Active Version</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- New Template Modal --}}
    @if($showNewTemplateModal)
        <div class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md flex flex-col shadow-2xl overflow-hidden">
                <div class="h-14 px-6 border-b border-slate-800 flex items-center justify-between">
                    <h3 class="text-base font-bold text-white">Create New Template</h3>
                    <button wire:click="$set('showNewTemplateModal', false)" class="p-1.5 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Template Name</label>
                        <input type="text" wire:model="newTemplateName" placeholder="e.g. Master Executive Certificate" class="w-full bg-slate-950 border border-slate-800 rounded-lg p-2 text-sm text-white focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Starter Preset Design</label>
                        <select wire:model="newTemplatePreset" class="w-full bg-slate-950 border border-slate-800 rounded-lg p-2 text-sm text-white">
                            <option value="classic">Classic Academic</option>
                            <option value="modern">Modern Professional</option>
                            <option value="gold">Gold Achievement</option>
                            <option value="minimalist">Minimal Corporate</option>
                        </select>
                    </div>
                    <div class="pt-2 flex justify-end space-x-2">
                        <button type="button" wire:click="$set('showNewTemplateModal', false)" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold">Cancel</button>
                        <button type="button" wire:click="createNewTemplate" class="px-4 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold">Create Template</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Alpine.js Interactive Canvas Logic --}}
<script>
function certificateDesigner(designData, selectedElementId, zoom, showGrid, snapToGrid) {
    return {
        designData: designData,
        selectedElementId: selectedElementId,
        zoom: zoom || 100,
        showGrid: showGrid || false,
        snapToGrid: snapToGrid || false,
        gridSize: 10,
        showSampleData: true,

        sampleData: @json($sampleData),

        // Dragging & Resizing state
        isDragging: false,
        isResizing: false,
        dragEl: null,
        resizeHandle: null,
        startX: 0,
        startY: 0,
        elStartX: 0,
        elStartY: 0,
        elStartW: 0,
        elStartH: 0,

        init() {
            window.addEventListener('mousemove', (e) => this.onMouseMove(e));
            window.addEventListener('mouseup', () => this.onMouseUp());
            window.addEventListener('keydown', (e) => this.onKeyDown(e));
        },

        get selectedElement() {
            if (!this.selectedElementId) return null;
            return this.designData.elements.find(el => el.id === this.selectedElementId);
        },

        selectElement(id) {
            this.selectedElementId = id;
            if (window.Livewire) {
                // sync to Livewire component
                this.$wire.set('selectedElementId', id, false);
            }
        },

        zoomIn() {
            this.zoom = Math.min(160, this.zoom + 10);
            this.$wire.set('zoom', this.zoom, false);
        },

        zoomOut() {
            this.zoom = Math.max(50, this.zoom - 10);
            this.$wire.set('zoom', this.zoom, false);
        },

        resetZoom() {
            this.zoom = 100;
            this.$wire.set('zoom', 100, false);
        },

        togglePreviewData() {
            this.showSampleData = !this.showSampleData;
        },

        renderElementContent(el) {
            let content = el.content || '';
            if (this.showSampleData && el.type === 'text') {
                for (const [key, val] of Object.entries(this.sampleData)) {
                    content = content.replaceAll('{{' + key + '}}', val);
                }
            }
            return content;
        },

        getCanvasStyle() {
            const w = this.designData.canvas.width || 842;
            const h = this.designData.canvas.height || 595;
            const scale = (this.zoom / 100);
            const bg = this.designData.canvas.backgroundColor || '#ffffff';
            let style = `width: ${w}px; height: ${h}px; transform: scale(${scale}); transform-origin: center center; background-color: ${bg};`;
            if (this.designData.canvas.backgroundImage) {
                style += ` background-image: url('${this.designData.canvas.backgroundImage}'); background-size: cover; background-position: center;`;
            }
            return style;
        },

        getOuterBorderStyle() {
            const inset = this.designData.canvas.borderInset || 20;
            const w = (this.designData.canvas.width || 842) - (inset * 2);
            const h = (this.designData.canvas.height || 595) - (inset * 2);
            const bWidth = this.designData.canvas.borderWidth || 4;
            const bStyle = this.designData.canvas.borderStyle || 'solid';
            const bColor = this.designData.canvas.borderColor || '#1e3a8a';
            return `top: ${inset}px; left: ${inset}px; width: ${w}px; height: ${h}px; border: ${bWidth}px ${bStyle} ${bColor};`;
        },

        getInnerBorderStyle() {
            const outerInset = this.designData.canvas.borderInset || 20;
            const innerInset = outerInset + (this.designData.canvas.innerBorderInset || 8);
            const w = (this.designData.canvas.width || 842) - (innerInset * 2);
            const h = (this.designData.canvas.height || 595) - (innerInset * 2);
            const bWidth = this.designData.canvas.innerBorderWidth || 1;
            const bStyle = this.designData.canvas.innerBorderStyle || 'solid';
            const bColor = this.designData.canvas.innerBorderColor || '#d97706';
            return `top: ${innerInset}px; left: ${innerInset}px; width: ${w}px; height: ${h}px; border: ${bWidth}px ${bStyle} ${bColor};`;
        },

        getElementStyle(el) {
            let s = `left: ${el.x}px; top: ${el.y}px; width: ${el.width}px; height: ${el.height}px; z-index: ${el.zIndex || 2};`;
            if (el.rotation) {
                s += ` transform: rotate(${el.rotation}deg);`;
            }
            if (el.opacity < 1) {
                s += ` opacity: ${el.opacity};`;
            }
            return s;
        },

        getTextStyle(el) {
            return `font-family: ${el.fontFamily || 'Helvetica'}, sans-serif; font-size: ${el.fontSize || 14}px; font-weight: ${el.fontWeight || 'normal'}; text-align: ${el.textAlign || 'center'}; color: ${el.color || '#000000'}; line-height: ${el.lineHeight || 1.2}; letter-spacing: ${el.letterSpacing || 'normal'};`;
        },

        startDrag(e, el) {
            if (el.isLocked) return;
            this.isDragging = true;
            this.dragEl = el;
            this.selectedElementId = el.id;
            this.startX = e.clientX;
            this.startY = e.clientY;
            this.elStartX = el.x;
            this.elStartY = el.y;
            e.stopPropagation();
        },

        startResize(e, el, handle) {
            if (el.isLocked) return;
            this.isResizing = true;
            this.dragEl = el;
            this.resizeHandle = handle;
            this.startX = e.clientX;
            this.startY = e.clientY;
            this.elStartX = el.x;
            this.elStartY = el.y;
            this.elStartW = el.width;
            this.elStartH = el.height;
            e.stopPropagation();
        },

        onMouseMove(e) {
            const scale = (this.zoom / 100);
            if (this.isDragging && this.dragEl) {
                const dx = (e.clientX - this.startX) / scale;
                const dy = (e.clientY - this.startY) / scale;
                let newX = Math.round(this.elStartX + dx);
                let newY = Math.round(this.elStartY + dy);

                if (this.snapToGrid) {
                    newX = Math.round(newX / this.gridSize) * this.gridSize;
                    newY = Math.round(newY / this.gridSize) * this.gridSize;
                }

                this.dragEl.x = newX;
                this.dragEl.y = newY;
            } else if (this.isResizing && this.dragEl && this.resizeHandle) {
                const dx = (e.clientX - this.startX) / scale;
                const dy = (e.clientY - this.startY) / scale;
                const handle = this.resizeHandle;

                if (handle.includes('e')) {
                    this.dragEl.width = Math.max(20, Math.round(this.elStartW + dx));
                }
                if (handle.includes('s')) {
                    this.dragEl.height = Math.max(10, Math.round(this.elStartH + dy));
                }
                if (handle.includes('w')) {
                    const newW = Math.max(20, Math.round(this.elStartW - dx));
                    this.dragEl.x = Math.round(this.elStartX + (this.elStartW - newW));
                    this.dragEl.width = newW;
                }
                if (handle.includes('n')) {
                    const newH = Math.max(10, Math.round(this.elStartH - dy));
                    this.dragEl.y = Math.round(this.elStartY + (this.elStartH - newH));
                    this.dragEl.height = newH;
                }
            }
        },

        onMouseUp() {
            if (this.isDragging || this.isResizing) {
                this.isDragging = false;
                this.isResizing = false;
                this.dragEl = null;
                this.resizeHandle = null;
                // Sync back to Livewire
                if (window.Livewire) {
                    this.$wire.set('designData', this.designData, false);
                }
            }
        },

        onKeyDown(e) {
            if (!this.selectedElementId) return;
            // Ignore if active in input or textarea
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;

            if (e.key === 'Delete' || e.key === 'Backspace') {
                this.deleteEl(this.selectedElementId);
                e.preventDefault();
            } else if (e.key === 'd' && (e.ctrlKey || e.metaKey)) {
                this.duplicate(this.selectedElementId);
                e.preventDefault();
            }
        },

        deleteEl(id) {
            this.$wire.deleteElement(id);
        },

        duplicate(id) {
            this.$wire.duplicateElement(id);
        },

        toggleLock(id) {
            this.$wire.toggleLockElement(id);
        },

        align(alignment) {
            if (this.selectedElementId) {
                this.$wire.alignElement(this.selectedElementId, alignment);
            }
        },

        reorder(direction) {
            if (this.selectedElementId) {
                this.$wire.reorderElement(this.selectedElementId, direction);
            }
        },

        insertPlaceholder(tag) {
            if (this.selectedElement && this.selectedElement.type === 'text') {
                this.selectedElement.content = (this.selectedElement.content || '') + ' ' + tag;
                this.$wire.set('designData', this.designData, false);
            } else {
                // Add new text element with this placeholder
                this.$wire.addElement('text', { content: tag });
            }
        },

        updatePaperSize() {
            this.$wire.updateCanvasProperty('pageSize', this.designData.canvas.pageSize);
        }
    };
}
</script>
