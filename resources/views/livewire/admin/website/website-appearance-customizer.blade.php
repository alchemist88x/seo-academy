<div class="space-y-6">
    <x-page-header
        title="Website Appearance & Design System"
        subtitle="Customize global website colors, typography, buttons, header and footer styling without touching code."
    >
        <x-slot:actions>
            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="{{ route('admin.website.index') }}" class="btn btn-secondary btn-sm">
                    &larr; CMS Content
                </a>
                <a href="{{ route('admin.website.banners') }}" class="btn btn-secondary btn-sm">
                    Banners
                </a>
                <a href="{{ route('admin.website.chatbot') }}" class="btn btn-secondary btn-sm">
                    Chatbot
                </a>
                <a href="{{ url('/') }}" target="_blank" class="btn btn-secondary btn-sm inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    <span>View Site</span>
                </a>
                <button type="button"
                        wire:click="resetToDefaults"
                        wire:confirm="Reset all theme colors and design tokens to default SEO Academy styling?"
                        class="btn btn-secondary btn-sm text-red-600 hover:text-red-700">
                    Reset Defaults
                </button>
                <button type="button"
                        wire:click="saveDraft"
                        class="btn btn-secondary btn-sm">
                    Save Draft
                </button>
                <button type="button"
                        wire:click="publishTheme"
                        class="btn btn-primary btn-sm inline-flex items-center gap-1.5 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Publish Theme</span>
                </button>
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- Feedback Alert --}}
    @if ($feedbackMessage)
        <div class="p-4 rounded-xl {{ $feedbackType === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' }} flex items-center justify-between text-sm shadow-xs">
            <div class="flex items-center gap-2 font-medium">
                <span>{{ $feedbackMessage }}</span>
            </div>
            <button type="button" wire:click="$set('feedbackMessage', null)" class="text-slate-400 hover:text-slate-600">
                &times;
            </button>
        </div>
    @endif

    {{-- Main Customizer Workspace: Controls + Live Preview --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        {{-- Left: Customization Form Tabs --}}
        <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            {{-- Category Navigation --}}
            <div class="flex border-b border-slate-200 bg-slate-50/70 overflow-x-auto text-xs font-semibold text-slate-600">
                <button type="button" wire:click="$set('activeSection', 'colors')"
                        class="px-4 py-3 border-b-2 whitespace-nowrap {{ $activeSection === 'colors' ? 'border-blue-600 text-blue-600 bg-white font-bold' : 'border-transparent hover:text-slate-900' }}">
                    Colours
                </button>
                <button type="button" wire:click="$set('activeSection', 'header')"
                        class="px-4 py-3 border-b-2 whitespace-nowrap {{ $activeSection === 'header' ? 'border-blue-600 text-blue-600 bg-white font-bold' : 'border-transparent hover:text-slate-900' }}">
                    Header
                </button>
                <button type="button" wire:click="$set('activeSection', 'buttons')"
                        class="px-4 py-3 border-b-2 whitespace-nowrap {{ $activeSection === 'buttons' ? 'border-blue-600 text-blue-600 bg-white font-bold' : 'border-transparent hover:text-slate-900' }}">
                    Buttons
                </button>
                <button type="button" wire:click="$set('activeSection', 'cards')"
                        class="px-4 py-3 border-b-2 whitespace-nowrap {{ $activeSection === 'cards' ? 'border-blue-600 text-blue-600 bg-white font-bold' : 'border-transparent hover:text-slate-900' }}">
                    Cards
                </button>
                <button type="button" wire:click="$set('activeSection', 'typography')"
                        class="px-4 py-3 border-b-2 whitespace-nowrap {{ $activeSection === 'typography' ? 'border-blue-600 text-blue-600 bg-white font-bold' : 'border-transparent hover:text-slate-900' }}">
                    Fonts
                </button>
                <button type="button" wire:click="$set('activeSection', 'presets')"
                        class="px-4 py-3 border-b-2 whitespace-nowrap {{ $activeSection === 'presets' ? 'border-blue-600 text-blue-600 bg-white font-bold' : 'border-transparent hover:text-slate-900' }}">
                    Presets
                </button>
            </div>

            <div class="p-6 space-y-6">
                {{-- Section: Presets --}}
                @if($activeSection === 'presets')
                    <div class="space-y-4">
                        <h4 class="text-sm font-bold text-slate-800">Quick Palette Presets</h4>
                        <p class="text-xs text-slate-500">Select a pre-designed educational theme palette to update all color tokens simultaneously.</p>
                        
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <button type="button" wire:click="applyPreset('indigo')"
                                    class="p-3 rounded-xl border border-slate-200 hover:border-blue-500 hover:shadow-md transition text-left space-y-2 group">
                                <div class="flex space-x-1.5">
                                    <div class="w-5 h-5 rounded-full bg-blue-600"></div>
                                    <div class="w-5 h-5 rounded-full bg-slate-800"></div>
                                    <div class="w-5 h-5 rounded-full bg-teal-600"></div>
                                </div>
                                <span class="font-bold text-xs text-slate-800 group-hover:text-blue-600 block">Modern Indigo</span>
                            </button>

                            <button type="button" wire:click="applyPreset('emerald')"
                                    class="p-3 rounded-xl border border-slate-200 hover:border-emerald-500 hover:shadow-md transition text-left space-y-2 group">
                                <div class="flex space-x-1.5">
                                    <div class="w-5 h-5 rounded-full bg-emerald-600"></div>
                                    <div class="w-5 h-5 rounded-full bg-emerald-950"></div>
                                    <div class="w-5 h-5 rounded-full bg-amber-500"></div>
                                </div>
                                <span class="font-bold text-xs text-slate-800 group-hover:text-emerald-600 block">Emerald Growth</span>
                            </button>

                            <button type="button" wire:click="applyPreset('slate')"
                                    class="p-3 rounded-xl border border-slate-200 hover:border-slate-500 hover:shadow-md transition text-left space-y-2 group">
                                <div class="flex space-x-1.5">
                                    <div class="w-5 h-5 rounded-full bg-slate-900"></div>
                                    <div class="w-5 h-5 rounded-full bg-slate-700"></div>
                                    <div class="w-5 h-5 rounded-full bg-blue-500"></div>
                                </div>
                                <span class="font-bold text-xs text-slate-800 group-hover:text-slate-900 block">Corporate Slate</span>
                            </button>

                            <button type="button" wire:click="applyPreset('amber')"
                                    class="p-3 rounded-xl border border-slate-200 hover:border-amber-500 hover:shadow-md transition text-left space-y-2 group">
                                <div class="flex space-x-1.5">
                                    <div class="w-5 h-5 rounded-full bg-amber-600"></div>
                                    <div class="w-5 h-5 rounded-full bg-amber-950"></div>
                                    <div class="w-5 h-5 rounded-full bg-blue-600"></div>
                                </div>
                                <span class="font-bold text-xs text-slate-800 group-hover:text-amber-600 block">Midnight Amber</span>
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Section: Colours --}}
                @if($activeSection === 'colors')
                    <div class="space-y-4">
                        <h4 class="text-sm font-bold text-slate-800">Global Website Color Tokens</h4>
                        <p class="text-xs text-slate-500">All public marketing components draw directly from these design tokens.</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                            @php
                                $colorFields = [
                                    'primary_color' => 'Primary Brand',
                                    'secondary_color' => 'Secondary Slate',
                                    'accent_color' => 'Accent / Highlight',
                                    'bg_main' => 'Main Canvas BG',
                                    'bg_secondary' => 'Secondary Canvas',
                                    'bg_card' => 'Card Background',
                                    'text_heading' => 'Heading Text',
                                    'text_body' => 'Body Text',
                                    'text_muted' => 'Muted Text',
                                    'border_color' => 'Border Line',
                                    'link_color' => 'Link Color',
                                    'link_hover_color' => 'Link Hover',
                                ];
                            @endphp

                            @foreach($colorFields as $key => $title)
                                <div class="space-y-1">
                                    <label class="block text-xs font-semibold text-slate-700">{{ $title }}</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="color"
                                               wire:model.live="settings.{{ $key }}"
                                               class="w-9 h-9 rounded-lg border border-slate-300 p-0.5 cursor-pointer bg-white">
                                        <input type="text"
                                               wire:model.live.debounce.300ms="settings.{{ $key }}"
                                               class="flex-1 px-3 py-1.5 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-lg">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Section: Header & Navigation --}}
                @if($activeSection === 'header')
                    <div class="space-y-4">
                        <h4 class="text-sm font-bold text-slate-800">Public Navigation Header</h4>
                        
                        <div class="space-y-3">
                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Header Background</label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" wire:model.live="settings.header_bg" class="w-9 h-9 rounded-lg border border-slate-300 p-0.5">
                                    <input type="text" wire:model.live="settings.header_bg" class="flex-1 px-3 py-1.5 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-lg">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Navigation Link Color</label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" wire:model.live="settings.header_nav_color" class="w-9 h-9 rounded-lg border border-slate-300 p-0.5">
                                    <input type="text" wire:model.live="settings.header_nav_color" class="flex-1 px-3 py-1.5 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-lg">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Navigation Hover Color</label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" wire:model.live="settings.header_nav_hover_color" class="w-9 h-9 rounded-lg border border-slate-300 p-0.5">
                                    <input type="text" wire:model.live="settings.header_nav_hover_color" class="flex-1 px-3 py-1.5 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-lg">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Logo Max Height (px)</label>
                                <input type="number" min="24" max="80" wire:model.live="settings.header_logo_height" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg">
                            </div>
                        </div>

                        <h4 class="text-sm font-bold text-slate-800 pt-4 border-t border-slate-200">Footer Styling</h4>
                        <div class="space-y-3">
                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Footer Background</label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" wire:model.live="settings.footer_bg" class="w-9 h-9 rounded-lg border border-slate-300 p-0.5">
                                    <input type="text" wire:model.live="settings.footer_bg" class="flex-1 px-3 py-1.5 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-lg">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Footer Text Color</label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" wire:model.live="settings.footer_text_color" class="w-9 h-9 rounded-lg border border-slate-300 p-0.5">
                                    <input type="text" wire:model.live="settings.footer_text_color" class="flex-1 px-3 py-1.5 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-lg">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Section: Buttons --}}
                @if($activeSection === 'buttons')
                    <div class="space-y-4">
                        <h4 class="text-sm font-bold text-slate-800">Buttons & Actions</h4>
                        
                        <div class="space-y-3">
                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Primary Button Fill</label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" wire:model.live="settings.btn_primary_bg" class="w-9 h-9 rounded-lg border border-slate-300 p-0.5">
                                    <input type="text" wire:model.live="settings.btn_primary_bg" class="flex-1 px-3 py-1.5 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-lg">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Primary Button Text</label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" wire:model.live="settings.btn_primary_text" class="w-9 h-9 rounded-lg border border-slate-300 p-0.5">
                                    <input type="text" wire:model.live="settings.btn_primary_text" class="flex-1 px-3 py-1.5 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-lg">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Button Corner Radius</label>
                                <select wire:model.live="settings.btn_border_radius" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg">
                                    <option value="0px">Square (0px)</option>
                                    <option value="4px">Slight Rounded (4px)</option>
                                    <option value="8px">Standard (8px)</option>
                                    <option value="12px">Modern Rounded (12px)</option>
                                    <option value="16px">Extra Rounded (16px)</option>
                                    <option value="9999px">Pill / Full (9999px)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Section: Cards --}}
                @if($activeSection === 'cards')
                    <div class="space-y-4">
                        <h4 class="text-sm font-bold text-slate-800">Card Components</h4>
                        
                        <div class="space-y-3">
                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Card Background</label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" wire:model.live="settings.card_bg" class="w-9 h-9 rounded-lg border border-slate-300 p-0.5">
                                    <input type="text" wire:model.live="settings.card_bg" class="flex-1 px-3 py-1.5 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-lg">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Card Border Line</label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" wire:model.live="settings.card_border" class="w-9 h-9 rounded-lg border border-slate-300 p-0.5">
                                    <input type="text" wire:model.live="settings.card_border" class="flex-1 px-3 py-1.5 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-lg">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Card Corner Radius</label>
                                <select wire:model.live="settings.card_border_radius" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg">
                                    <option value="0px">Square (0px)</option>
                                    <option value="8px">Compact (8px)</option>
                                    <option value="12px">Subtle (12px)</option>
                                    <option value="16px">Rounded Modern (16px)</option>
                                    <option value="24px">Elevated Pill (24px)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Section: Typography --}}
                @if($activeSection === 'typography')
                    <div class="space-y-4">
                        <h4 class="text-sm font-bold text-slate-800">Typography & Web Fonts</h4>
                        
                        <div class="space-y-3">
                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Body Font Family</label>
                                <select wire:model.live="settings.font_family_body" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg">
                                    <option value="Plus Jakarta Sans, sans-serif">Plus Jakarta Sans (Default)</option>
                                    <option value="Inter, sans-serif">Inter</option>
                                    <option value="Roboto, sans-serif">Roboto</option>
                                    <option value="Open Sans, sans-serif">Open Sans</option>
                                    <option value="Outfit, sans-serif">Outfit</option>
                                    <option value="Montserrat, sans-serif">Montserrat</option>
                                    <option value="system-ui, -apple-system, sans-serif">System Native Sans</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Heading Font Family</label>
                                <select wire:model.live="settings.font_family_heading" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg">
                                    <option value="Plus Jakarta Sans, sans-serif">Plus Jakarta Sans (Default)</option>
                                    <option value="Outfit, sans-serif">Outfit</option>
                                    <option value="Inter, sans-serif">Inter</option>
                                    <option value="Montserrat, sans-serif">Montserrat</option>
                                    <option value="Merriweather, serif">Merriweather (Editorial Serif)</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Base Font Size</label>
                                <select wire:model.live="settings.font_size_base" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg">
                                    <option value="14px">Compact (14px)</option>
                                    <option value="15px">Medium (15px)</option>
                                    <option value="16px">Standard (16px)</option>
                                    <option value="18px">Large (18px)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right: Interactive Live Preview --}}
        <div class="lg:col-span-7 space-y-4">
            {{-- Preview Controls Toolbar --}}
            <div class="flex items-center justify-between bg-white px-4 py-2.5 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Live Theme Preview</span>
                </span>

                <div class="flex items-center space-x-1.5 bg-slate-100 p-1 rounded-lg">
                    <button type="button" wire:click="$set('previewDevice', 'desktop')"
                            class="px-2.5 py-1 rounded text-xs font-medium {{ $previewDevice === 'desktop' ? 'bg-white shadow-xs text-blue-600 font-bold' : 'text-slate-600 hover:text-slate-900' }}">
                        Desktop (1440)
                    </button>
                    <button type="button" wire:click="$set('previewDevice', 'tablet')"
                            class="px-2.5 py-1 rounded text-xs font-medium {{ $previewDevice === 'tablet' ? 'bg-white shadow-xs text-blue-600 font-bold' : 'text-slate-600 hover:text-slate-900' }}">
                        Tablet (768)
                    </button>
                    <button type="button" wire:click="$set('previewDevice', 'mobile')"
                            class="px-2.5 py-1 rounded text-xs font-medium {{ $previewDevice === 'mobile' ? 'bg-white shadow-xs text-blue-600 font-bold' : 'text-slate-600 hover:text-slate-900' }}">
                        Mobile (375)
                    </button>
                </div>
            </div>

            {{-- Preview Canvas Container with Scoped Styles --}}
            <div class="flex justify-center bg-slate-200/70 p-4 sm:p-6 rounded-2xl border border-slate-300 overflow-x-auto min-h-[640px]">
                <div class="transition-all duration-300 {{ $previewDevice === 'mobile' ? 'w-[375px]' : ($previewDevice === 'tablet' ? 'w-[768px]' : 'w-full') }}">
                    
                    {{-- Scoped CSS variables applied strictly to preview --}}
                    <div id="theme-live-preview-box"
                         class="rounded-2xl border shadow-xl overflow-hidden flex flex-col"
                         style="
                            --preview-primary: {{ $settings['primary_color'] ?? '#2563eb' }};
                            --preview-secondary: {{ $settings['secondary_color'] ?? '#1e293b' }};
                            --preview-accent: {{ $settings['accent_color'] ?? '#0d9488' }};
                            --preview-bg-main: {{ $settings['bg_main'] ?? '#ffffff' }};
                            --preview-bg-card: {{ $settings['card_bg'] ?? '#ffffff' }};
                            --preview-card-border: {{ $settings['card_border'] ?? '#e2e8f0' }};
                            --preview-card-radius: {{ $settings['card_border_radius'] ?? '16px' }};
                            --preview-btn-radius: {{ $settings['btn_border_radius'] ?? '12px' }};
                            --preview-btn-primary-bg: {{ $settings['btn_primary_bg'] ?? '#2563eb' }};
                            --preview-btn-primary-text: {{ $settings['btn_primary_text'] ?? '#ffffff' }};
                            --preview-header-bg: {{ $settings['header_bg'] ?? '#ffffff' }};
                            --preview-header-nav: {{ $settings['header_nav_color'] ?? '#475569' }};
                            --preview-footer-bg: {{ $settings['footer_bg'] ?? '#020617' }};
                            --preview-footer-text: {{ $settings['footer_text_color'] ?? '#94a3b8' }};
                            --preview-text-heading: {{ $settings['text_heading'] ?? '#0f172a' }};
                            --preview-text-body: {{ $settings['text_body'] ?? '#334155' }};
                            background-color: var(--preview-bg-main);
                         ">

                        {{-- Mock Header --}}
                        <div class="px-5 py-4 border-b flex items-center justify-between"
                             style="background-color: var(--preview-header-bg); border-color: var(--preview-card-border);">
                            <div class="flex items-center space-x-2.5">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold"
                                     style="background-color: var(--preview-primary);">
                                    🎓
                                </div>
                                <span class="font-bold text-sm tracking-tight" style="color: var(--preview-text-heading);">
                                    SEO Academy
                                </span>
                            </div>

                            <div class="hidden sm:flex items-center space-x-4 text-xs font-medium"
                                 style="color: var(--preview-header-nav);">
                                <span>Courses</span>
                                <span>Services</span>
                                <span>Blog</span>
                                <span>About</span>
                            </div>

                            <div>
                                <span class="px-3 py-1.5 text-xs font-semibold shadow-xs"
                                      style="background-color: var(--preview-btn-primary-bg); color: var(--preview-btn-primary-text); border-radius: var(--preview-btn-radius);">
                                    Enroll Now
                                </span>
                            </div>
                        </div>

                        {{-- Mock Hero Banner --}}
                        <div class="p-8 text-center space-y-4" style="background-color: var(--preview-bg-main);">
                            <span class="inline-block px-3 py-1 text-[11px] font-bold uppercase tracking-wider rounded-full"
                                  style="background-color: rgba(37,99,235,0.1); color: var(--preview-primary);">
                                Certified SEO Training
                            </span>
                            
                            <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight"
                                style="color: var(--preview-text-heading);">
                                Master Technical Search Optimization
                            </h2>

                            <p class="text-xs sm:text-sm max-w-md mx-auto"
                               style="color: var(--preview-text-body);">
                                Accelerate your career with real-world website audits, live instructor feedback, and cryptographic certificates.
                            </p>

                            <div class="flex justify-center gap-2 pt-2">
                                <span class="px-4 py-2 text-xs font-semibold shadow-xs"
                                      style="background-color: var(--preview-btn-primary-bg); color: var(--preview-btn-primary-text); border-radius: var(--preview-btn-radius);">
                                    Explore Courses
                                </span>
                                <span class="px-4 py-2 text-xs font-semibold border shadow-2xs"
                                      style="border-color: var(--preview-card-border); color: var(--preview-text-body); border-radius: var(--preview-btn-radius);">
                                    Admissions Info
                                </span>
                            </div>
                        </div>

                        {{-- Mock Cards Section --}}
                        <div class="p-6 bg-slate-50/50 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="p-4 shadow-sm border"
                                 style="background-color: var(--preview-bg-card); border-color: var(--preview-card-border); border-radius: var(--preview-card-radius);">
                                <span class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--preview-accent);">Featured Course</span>
                                <h4 class="font-bold text-sm mt-1" style="color: var(--preview-text-heading);">Technical SEO Architecture</h4>
                                <p class="text-xs mt-1" style="color: var(--preview-text-body);">Server logs, robots directives, and rendering pipelines.</p>
                                <div class="mt-3 flex items-center justify-between text-xs pt-2 border-t" style="border-color: var(--preview-card-border);">
                                    <span class="font-bold" style="color: var(--preview-primary);">@currency(499)</span>
                                    <span class="text-[11px] font-semibold" style="color: var(--preview-primary);">View Syllabus &rarr;</span>
                                </div>
                            </div>

                            <div class="p-4 shadow-sm border"
                                 style="background-color: var(--preview-bg-card); border-color: var(--preview-card-border); border-radius: var(--preview-card-radius);">
                                <span class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--preview-accent);">Consulting Service</span>
                                <h4 class="font-bold text-sm mt-1" style="color: var(--preview-text-heading);">Enterprise Site Audits</h4>
                                <p class="text-xs mt-1" style="color: var(--preview-text-body);">Deep algorithmic crawl and indexability diagnostics.</p>
                                <div class="mt-3 flex items-center justify-between text-xs pt-2 border-t" style="border-color: var(--preview-card-border);">
                                    <span class="font-bold" style="color: var(--preview-primary);">Custom</span>
                                    <span class="text-[11px] font-semibold" style="color: var(--preview-primary);">Inquire Now &rarr;</span>
                                </div>
                            </div>
                        </div>

                        {{-- Mock Footer --}}
                        <div class="p-6 text-xs flex items-center justify-between"
                             style="background-color: var(--preview-footer-bg); color: var(--preview-footer-text);">
                            <div>
                                <span class="font-bold text-white block">SEO Academy</span>
                                <span class="text-[10px] opacity-75">&copy; {{ date('Y') }} All rights reserved.</span>
                            </div>
                            <div class="flex space-x-3 text-[11px]">
                                <span>Curriculum</span>
                                <span>Admissions</span>
                                <span>Privacy</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
