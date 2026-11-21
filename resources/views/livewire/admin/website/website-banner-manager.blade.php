<div class="space-y-6">
    <x-page-header
        title="Promotional Banners & Hero Images"
        subtitle="Manage marketing hero banners, placement targeting, responsive graphics, scheduling, and conversion CTAs."
    >
        <x-slot:actions>
            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="{{ route('admin.website.index') }}" class="btn btn-secondary btn-sm">
                    &larr; CMS Content
                </a>
                <a href="{{ route('admin.website.appearance') }}" class="btn btn-secondary btn-sm">
                    Appearance
                </a>
                <a href="{{ route('admin.website.chatbot') }}" class="btn btn-secondary btn-sm">
                    Chatbot
                </a>
                <button type="button"
                        wire:click="openCreateModal"
                        class="btn btn-primary btn-sm inline-flex items-center gap-1.5 shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Create Banner</span>
                </button>
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- Feedback Message --}}
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

    {{-- Filter Toolbar --}}
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
        <div class="flex items-center gap-2 overflow-x-auto w-full sm:w-auto">
            @php
                $placements = [
                    'all' => 'All Placements',
                    'homepage_hero' => 'Homepage Hero',
                    'homepage_promo' => 'Homepage Promo',
                    'courses_promo' => 'Courses Promo',
                    'services_promo' => 'Services Promo',
                    'blog_promo' => 'Blog Promo',
                ];
            @endphp
            @foreach($placements as $key => $label)
                <button type="button"
                        wire:click="$set('filterPlacement', '{{ $key }}')"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition {{ $filterPlacement === $key ? 'bg-blue-600 text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="w-full sm:w-64">
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search banners..."
                   class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 transition">
        </div>
    </div>

    {{-- Banners List Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        @if($banners->isEmpty())
            <div class="p-12 text-center text-slate-500">
                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p class="font-medium text-slate-700">No promotional banners found.</p>
                <p class="text-xs text-slate-400 mt-1">Create your first hero banner to showcase announcements, special discounts, or highlighted courses.</p>
                <button type="button" wire:click="openCreateModal" class="btn btn-primary btn-sm mt-4">
                    Create Banner
                </button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3.5">Banner Preview</th>
                            <th class="px-5 py-3.5">Placement & Heading</th>
                            <th class="px-5 py-3.5">CTAs</th>
                            <th class="px-5 py-3.5">Schedule & Status</th>
                            <th class="px-5 py-3.5 text-center">Order</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($banners as $banner)
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-5 py-4 w-40">
                                    <div class="w-32 h-16 rounded-xl overflow-hidden bg-slate-800 relative flex items-center justify-center text-white border border-slate-200 shadow-2xs">
                                        @if($banner->desktop_image_path)
                                            <img src="{{ asset($banner->desktop_image_path) }}" alt="{{ $banner->heading }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="text-[10px] text-center p-2 font-bold uppercase tracking-wider opacity-80"
                                                 style="background-color: {{ $banner->overlay_color ?: '#000000' }};">
                                                {{ Str::limit($banner->heading, 25) }}
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-900 text-sm">{{ $banner->name }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200">
                                                {{ str_replace('_', ' ', $banner->placement) }}
                                            </span>
                                        </div>
                                        <p class="text-slate-600 line-clamp-1 font-medium">{{ $banner->heading }}</p>
                                        @if($banner->subtitle)
                                            <p class="text-slate-400 text-[11px] line-clamp-1">{{ $banner->subtitle }}</p>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="space-y-1">
                                        @if($banner->primary_cta_label)
                                            <div class="text-[11px] flex items-center gap-1.5 text-slate-700">
                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                                                <span class="font-semibold">{{ $banner->primary_cta_label }}:</span>
                                                <code class="text-[10px] text-slate-500 font-mono">{{ $banner->primary_cta_url }}</code>
                                            </div>
                                        @endif
                                        @if($banner->secondary_cta_label)
                                            <div class="text-[11px] flex items-center gap-1.5 text-slate-500">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                                <span>{{ $banner->secondary_cta_label }}:</span>
                                                <code class="text-[10px] font-mono">{{ $banner->secondary_cta_url }}</code>
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="space-y-1.5">
                                        <button type="button"
                                                wire:click="toggleActive('{{ $banner->id }}')"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold transition {{ $banner->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $banner->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                            <span>{{ $banner->is_active ? 'Active' : 'Inactive' }}</span>
                                        </button>

                                        @if($banner->starts_at || $banner->ends_at)
                                            <div class="text-[10px] text-slate-400">
                                                @if($banner->starts_at)
                                                    <div>From: {{ $banner->starts_at->format('M d, Y H:i') }}</div>
                                                @endif
                                                @if($banner->ends_at)
                                                    <div>To: {{ $banner->ends_at->format('M d, Y H:i') }}</div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-5 py-4 text-center">
                                    <div class="inline-flex items-center space-x-1">
                                        <button type="button" wire:click="moveUp('{{ $banner->id }}')" class="p-1 hover:bg-slate-100 rounded text-slate-500" title="Move Up">
                                            &uarr;
                                        </button>
                                        <span class="font-mono text-slate-400 text-xs">{{ $banner->display_order }}</span>
                                        <button type="button" wire:click="moveDown('{{ $banner->id }}')" class="p-1 hover:bg-slate-100 rounded text-slate-500" title="Move Down">
                                            &darr;
                                        </button>
                                    </div>
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" wire:click="openEditModal('{{ $banner->id }}')" class="btn btn-secondary btn-xs">
                                            Edit
                                        </button>
                                        <button type="button" wire:click="duplicateBanner('{{ $banner->id }}')" class="btn btn-secondary btn-xs text-slate-600" title="Duplicate">
                                            Copy
                                        </button>
                                        <button type="button"
                                                wire:click="deleteBanner('{{ $banner->id }}')"
                                                wire:confirm="Delete this banner permanently?"
                                                class="btn btn-secondary btn-xs text-red-600 hover:text-red-700">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Create / Edit Banner Modal --}}
    @if($showBannerModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-3xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                
                {{-- Modal Header --}}
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/80">
                    <h3 class="font-bold text-slate-900 text-base">
                        {{ $editingBannerId ? 'Edit Promotional Banner' : 'Create Promotional Banner' }}
                    </h3>
                    <button type="button" wire:click="$set('showBannerModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">
                        &times;
                    </button>
                </div>

                {{-- Modal Form Body --}}
                <form wire:submit.prevent="saveBanner" class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
                    
                    {{-- Row 1: Name & Placement --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Internal Name *</label>
                            <input type="text" wire:model="name" placeholder="e.g. Q4 Admission Kickoff" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                            @error('name') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Placement Slot *</label>
                            <select wire:model="placement" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                                <option value="homepage_hero">Homepage Hero (Primary Carousel)</option>
                                <option value="homepage_promo">Homepage Mid-Page Promo</option>
                                <option value="courses_promo">Courses Catalogue Promo</option>
                                <option value="services_promo">Services Page Promo</option>
                                <option value="blog_promo">Blog & Insights Promo</option>
                            </select>
                            @error('placement') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Row 2: Heading & Subtitle --}}
                    <div class="space-y-3">
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Main Heading *</label>
                            <input type="text" wire:model="heading" placeholder="e.g. Hands-On Technical SEO Certification" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                            @error('heading') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Badge / Subtitle</label>
                                <input type="text" wire:model="subtitle" placeholder="e.g. Winter 2026 Cohort" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-slate-700">Banner Height</label>
                                <select wire:model="bannerHeight" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                                    <option value="compact">Compact (220px)</option>
                                    <option value="medium">Medium (400px - Recommended)</option>
                                    <option value="tall">Tall Hero (540px)</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Description Text</label>
                            <textarea wire:model="description" rows="2" placeholder="Brief supporting message or value proposition..." class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl"></textarea>
                        </div>
                    </div>

                    {{-- Row 3: Media Uploads --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-200">
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Desktop Image (1920x800 recommended)</label>
                            <input type="file" wire:model="desktopImage" accept="image/*" class="w-full text-xs">
                            @if($desktopImage)
                                <img src="{{ $desktopImage->temporaryUrl() }}" class="mt-2 h-20 w-auto rounded-lg object-cover">
                            @elseif($currentDesktopImagePath)
                                <img src="{{ asset($currentDesktopImagePath) }}" class="mt-2 h-20 w-auto rounded-lg object-cover">
                            @endif
                            @error('desktopImage') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Mobile Image (800x800 recommended)</label>
                            <input type="file" wire:model="mobileImage" accept="image/*" class="w-full text-xs">
                            @if($mobileImage)
                                <img src="{{ $mobileImage->temporaryUrl() }}" class="mt-2 h-20 w-auto rounded-lg object-cover">
                            @elseif($currentMobileImagePath)
                                <img src="{{ asset($currentMobileImagePath) }}" class="mt-2 h-20 w-auto rounded-lg object-cover">
                            @endif
                            @error('mobileImage') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Row 4: CTAs --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-200">
                        <div class="space-y-3">
                            <h5 class="text-xs font-bold text-slate-800">Primary Call to Action</h5>
                            <input type="text" wire:model="primaryCtaLabel" placeholder="Button Label (e.g. Enroll Now)" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg">
                            <input type="text" wire:model="primaryCtaUrl" placeholder="Target URL (e.g. /courses/technical-seo)" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg">
                            @error('primaryCtaUrl') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-3">
                            <h5 class="text-xs font-bold text-slate-800">Secondary Call to Action</h5>
                            <input type="text" wire:model="secondaryCtaLabel" placeholder="Button Label (e.g. Contact Admissions)" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg">
                            <input type="text" wire:model="secondaryCtaUrl" placeholder="Target URL (e.g. /contact)" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg">
                            @error('secondaryCtaUrl') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Row 5: Styling, Alignment & Overlay --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2 border-t border-slate-200">
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Alignment</label>
                            <select wire:model="textAlignment" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg">
                                <option value="left">Left</option>
                                <option value="center">Center</option>
                                <option value="right">Right</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Text Color</label>
                            <div class="flex items-center space-x-1.5">
                                <input type="color" wire:model="textColor" class="w-8 h-8 rounded p-0.5 border border-slate-300">
                                <input type="text" wire:model="textColor" class="w-full px-2 py-1 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-lg">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Overlay Color</label>
                            <div class="flex items-center space-x-1.5">
                                <input type="color" wire:model="overlayColor" class="w-8 h-8 rounded p-0.5 border border-slate-300">
                                <input type="text" wire:model="overlayColor" class="w-full px-2 py-1 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-lg">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Overlay Opacity ({{ $overlayOpacity }}%)</label>
                            <input type="range" min="0" max="100" wire:model.live="overlayOpacity" class="w-full mt-2">
                        </div>
                    </div>

                    {{-- Row 6: Schedule & Active --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-200">
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Schedule Starts At</label>
                            <input type="datetime-local" wire:model="startsAt" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Schedule Ends At</label>
                            <input type="datetime-local" wire:model="endsAt" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg">
                        </div>

                        <div class="space-y-1 flex items-center pt-5">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" wire:model="isActive" class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4">
                                <span class="text-xs font-bold text-slate-800">Banner Is Active</span>
                            </label>
                        </div>
                    </div>

                    {{-- Modal Footer Actions --}}
                    <div class="pt-4 border-t border-slate-200 flex items-center justify-end space-x-2.5">
                        <button type="button" wire:click="$set('showBannerModal', false)" class="btn btn-secondary btn-sm">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm shadow-sm">
                            {{ $editingBannerId ? 'Update Banner' : 'Create Banner' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
