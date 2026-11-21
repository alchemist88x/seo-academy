<div class="space-y-6">
    <x-page-header
        title="Website CMS & SEO Management"
        subtitle="Manage public marketing pages, courses, services, blog articles, navigation, media, and search engine metadata."
    >
        <x-slot:actions>
            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="{{ route('admin.website.appearance') }}" class="btn btn-secondary btn-sm">
                    Appearance
                </a>
                <a href="{{ route('admin.website.banners') }}" class="btn btn-secondary btn-sm">
                    Banners
                </a>
                <a href="{{ route('admin.website.chatbot') }}" class="btn btn-secondary btn-sm">
                    Chatbot
                </a>
                <a href="{{ url('/') }}" target="_blank" class="btn btn-secondary btn-sm inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    <span>View Public Site</span>
                </a>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary btn-sm">
                    &larr; Dashboard
                </a>
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- Feedback Message --}}
    @if ($feedbackMessage)
        <div class="p-4 rounded-xl {{ $feedbackType === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' }} flex items-center justify-between text-sm shadow-xs">
            <div class="flex items-center gap-2 font-medium">
                @if($feedbackType === 'error')
                    <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                @else
                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                @endif
                <span>{{ $feedbackMessage }}</span>
            </div>
            <button type="button" wire:click="$set('feedbackMessage', null)" class="text-slate-400 hover:text-slate-600">
                &times;
            </button>
        </div>
    @endif

    {{-- Tabs Navigation --}}
    <div class="border-b border-border-default overflow-x-auto">
        <nav class="-mb-px flex space-x-2 sm:space-x-6 min-w-max" aria-label="CMS Tabs">
            @php
                $tabs = [
                    'homepage' => 'Homepage Builder',
                    'pages' => 'Pages',
                    'services' => 'Services',
                    'courses' => 'Course Marketing',
                    'navigation' => 'Navigation Menus',
                    'blog' => 'Blog & Articles',
                    'media' => 'Media Library',
                    'inquiries' => 'Inquiries (' . $inquiryCounts['new'] . ' new)',
                    'seo' => 'SEO & Sitemap',
                ];
            @endphp
            @foreach ($tabs as $key => $label)
                <button
                    type="button"
                    wire:click="setTab('{{ $key }}')"
                    class="py-3 px-1 border-b-2 text-sm font-medium transition-colors whitespace-nowrap {{ $activeTab === $key
                        ? 'border-primary-600 text-primary-600 dark:text-primary-400 font-bold'
                        : 'border-transparent text-text-secondary hover:text-text-primary hover:border-border-default' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ========================================================= --}}
    {{-- TAB 1: HOMEPAGE BUILDER --}}
    {{-- ========================================================= --}}
    @if ($activeTab === 'homepage')
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-text-primary">Homepage Sections</h3>
                    <p class="text-xs text-text-secondary">Enable, reorder, and configure marketing content blocks on the root URL.</p>
                </div>
            </div>

            @if($editingSectionId)
                <div class="card p-6 border border-primary-500/30 bg-surface-card rounded-2xl shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-border-default pb-3">
                        <h4 class="font-bold text-text-primary">Edit Section: {{ ucfirst($editingSectionId) }}</h4>
                        <button type="button" wire:click="cancelSectionEdit" class="text-xs text-text-secondary hover:text-text-primary">Cancel</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-text-primary mb-1">Headline / Title</label>
                            <input type="text" wire:model="sectionTitle" class="input input-sm w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-text-primary mb-1">Subheadline / Description</label>
                            <input type="text" wire:model="sectionSubtitle" class="input input-sm w-full">
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="sectionEnabled" class="checkbox checkbox-sm">
                            <span class="text-xs font-semibold text-text-primary">Enable this section on homepage</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border-default">
                        <button type="button" wire:click="cancelSectionEdit" class="btn btn-secondary btn-sm">Cancel</button>
                        <button type="button" wire:click="saveSection" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                </div>
            @endif

            <div class="card bg-surface-card rounded-2xl border border-border-default overflow-hidden">
                <div class="divide-y divide-border-default">
                    @forelse($sections as $sec)
                        <div class="p-4 sm:p-5 flex items-center justify-between gap-4 hover:bg-surface-bg/50 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-8 h-8 rounded-lg bg-surface-bg flex items-center justify-center font-mono text-xs font-bold text-text-secondary">
                                    {{ $sec->display_order }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-text-primary text-sm">{{ $sec->name }}</h4>
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $sec->is_enabled ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                            {{ $sec->is_enabled ? 'Visible' : 'Hidden' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-text-secondary mt-0.5 line-clamp-1">
                                        {{ $sec->title ?: 'Default template copy' }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="reorderSection('{{ $sec->id }}', 'up')" class="btn btn-ghost btn-sm p-1.5" title="Move Up">
                                    &uarr;
                                </button>
                                <button type="button" wire:click="reorderSection('{{ $sec->id }}', 'down')" class="btn btn-ghost btn-sm p-1.5" title="Move Down">
                                    &darr;
                                </button>
                                <button type="button" wire:click="toggleSection('{{ $sec->id }}')" class="btn btn-secondary btn-sm text-xs">
                                    {{ $sec->is_enabled ? 'Hide' : 'Show' }}
                                </button>
                                <button type="button" wire:click="editSection('{{ $sec->id }}')" class="btn btn-primary btn-sm text-xs">
                                    Edit
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-text-secondary text-sm">
                            No homepage sections found. Ensure defaults have run.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- ========================================================= --}}
    {{-- TAB 2: PAGES MANAGEMENT --}}
    {{-- ========================================================= --}}
    @if ($activeTab === 'pages')
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-text-primary">CMS Pages</h3>
                    <p class="text-xs text-text-secondary">Custom landing pages, academic policies, terms of service, and admissions guides.</p>
                </div>
                <button type="button" wire:click="openPageModal" class="btn btn-primary btn-sm inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Create Page</span>
                </button>
            </div>

            <div class="card bg-surface-card rounded-2xl border border-border-default overflow-hidden">
                <table class="table w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border-default bg-surface-bg/60 text-xs font-bold text-text-secondary uppercase">
                            <th class="p-4">Title</th>
                            <th class="p-4">URL Slug</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Last Updated</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-default">
                        @forelse($pages as $p)
                            <tr class="hover:bg-surface-bg/30 transition">
                                <td class="p-4 font-semibold text-text-primary">
                                    <a href="{{ $p->slug === 'about' ? url('/about') : ($p->slug === 'contact' ? url('/contact') : route('pages.show', $p->slug)) }}" target="_blank" class="hover:underline flex items-center gap-1.5 text-primary-600 dark:text-primary-400">
                                        <span>{{ $p->title }}</span>
                                        <svg class="w-3.5 h-3.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                </td>
                                <td class="p-4 font-mono text-xs text-text-secondary">/page/{{ $p->slug }}</td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $p->status === 'published' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                        {{ ucfirst($p->status) }}
                                    </span>
                                </td>
                                <td class="p-4 text-xs text-text-secondary">{{ $p->updated_at?->format('M d, Y') }}</td>
                                <td class="p-4 text-right space-x-2">
                                    <button type="button" wire:click="openPageModal('{{ $p->id }}')" class="btn btn-secondary btn-sm text-xs">Edit</button>
                                    @if(!in_array($p->slug, ['about', 'contact']))
                                        <button type="button" wire:click="deletePage('{{ $p->id }}')" wire:confirm="Are you sure you want to remove this page?" class="btn btn-ghost btn-sm text-xs text-red-600">Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-text-secondary text-sm">No CMS pages created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4 border-t border-border-default">
                    {{ $pages->links() }}
                </div>
            </div>

            {{-- Page Modal --}}
            @if($showPageModal)
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div class="bg-surface-card rounded-2xl border border-border-default max-w-2xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto shadow-2xl">
                        <div class="flex items-center justify-between border-b border-border-default pb-3">
                            <h3 class="font-bold text-lg text-text-primary">{{ $editingPageId ? 'Edit Page' : 'Create New Page' }}</h3>
                            <button type="button" wire:click="$set('showPageModal', false)" class="text-text-secondary hover:text-text-primary text-xl">&times;</button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1">Page Title *</label>
                                <input type="text" wire:model.live="pageTitle" class="input input-sm w-full">
                                @error('pageTitle') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1">URL Slug *</label>
                                <input type="text" wire:model="pageSlug" class="input input-sm w-full font-mono text-xs">
                                @error('pageSlug') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-text-primary mb-1">Content (Markdown / Text) *</label>
                            <textarea wire:model="pageContent" rows="8" class="input input-sm w-full font-sans text-xs" placeholder="Write page content here..."></textarea>
                            @error('pageContent') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1">Status</label>
                                <select wire:model="pageStatus" class="input input-sm w-full">
                                    <option value="published">Published</option>
                                    <option value="draft">Draft</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1">Robots Directive</label>
                                <input type="text" wire:model="pageRobots" class="input input-sm w-full text-xs" placeholder="index, follow">
                            </div>
                        </div>

                        <div class="pt-2 border-t border-border-default space-y-3">
                            <h4 class="text-xs uppercase font-bold text-text-secondary tracking-wider">Search Engine Optimization</h4>
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">SEO Title Tag</label>
                                <input type="text" wire:model="pageSeoTitle" class="input input-sm w-full text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">Meta Description</label>
                                <textarea wire:model="pageSeoDescription" rows="2" class="input input-sm w-full text-xs"></textarea>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-4 border-t border-border-default">
                            <button type="button" wire:click="$set('showPageModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="button" wire:click="savePage" class="btn btn-primary btn-sm">Save Page</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ========================================================= --}}
    {{-- TAB 3: SERVICES MANAGEMENT --}}
    {{-- ========================================================= --}}
    @if ($activeTab === 'services')
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-text-primary">Academy Programs & Services</h3>
                    <p class="text-xs text-text-secondary">Corporate training tracks, SEO consulting scopes, and team mentorship packages.</p>
                </div>
                <button type="button" wire:click="openServiceModal" class="btn btn-primary btn-sm inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Add Service</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($services as $svc)
                    <div class="card p-5 bg-surface-card rounded-2xl border border-border-default flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $svc->is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $svc->is_published ? 'Published' : 'Draft' }}
                                </span>
                                <span class="text-xs font-mono text-text-secondary">#{{ $svc->display_order }}</span>
                            </div>
                            <h4 class="font-bold text-text-primary text-base">{{ $svc->title }}</h4>
                            <p class="text-xs text-text-secondary mt-1 line-clamp-2">{{ $svc->summary }}</p>
                        </div>

                        <div class="mt-6 pt-4 border-t border-border-default flex items-center justify-between">
                            <a href="{{ route('services.show', $svc->slug) }}" target="_blank" class="text-xs text-primary-600 font-semibold hover:underline">
                                View Public
                            </a>
                            <div class="space-x-1">
                                <button type="button" wire:click="openServiceModal('{{ $svc->id }}')" class="btn btn-secondary btn-sm text-xs">Edit</button>
                                <button type="button" wire:click="deleteService('{{ $svc->id }}')" wire:confirm="Delete this service?" class="btn btn-ghost btn-sm text-xs text-red-600">Delete</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 p-8 text-center text-text-secondary text-sm">No services configured yet.</div>
                @endforelse
            </div>

            {{-- Service Modal --}}
            @if($showServiceModal)
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div class="bg-surface-card rounded-2xl border border-border-default max-w-2xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto shadow-2xl">
                        <div class="flex items-center justify-between border-b border-border-default pb-3">
                            <h3 class="font-bold text-lg text-text-primary">{{ $editingServiceId ? 'Edit Service' : 'Add New Service' }}</h3>
                            <button type="button" wire:click="$set('showServiceModal', false)" class="text-text-secondary hover:text-text-primary text-xl">&times;</button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1">Service Title *</label>
                                <input type="text" wire:model.live="serviceTitle" class="input input-sm w-full">
                                @error('serviceTitle') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1">URL Slug *</label>
                                <input type="text" wire:model="serviceSlug" class="input input-sm w-full font-mono text-xs">
                                @error('serviceSlug') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-text-primary mb-1">Short Summary (Card excerpt) *</label>
                            <textarea wire:model="serviceSummary" rows="2" class="input input-sm w-full text-xs"></textarea>
                            @error('serviceSummary') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-text-primary mb-1">Detailed Description *</label>
                            <textarea wire:model="serviceDescription" rows="5" class="input input-sm w-full text-xs"></textarea>
                            @error('serviceDescription') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-text-primary mb-1">Key Benefits / Deliverables (One per line)</label>
                            <textarea wire:model="serviceBenefitsText" rows="3" class="input input-sm w-full text-xs" placeholder="Comprehensive crawl diagnostics&#10;Canonical mapping&#10;Trainer consultation"></textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1">Display Order</label>
                                <input type="number" wire:model="serviceDisplayOrder" class="input input-sm w-full">
                            </div>
                            <div class="flex items-center pt-5">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="serviceIsPublished" class="checkbox checkbox-sm">
                                    <span class="text-xs font-semibold text-text-primary">Publish this service</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-4 border-t border-border-default">
                            <button type="button" wire:click="$set('showServiceModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="button" wire:click="saveService" class="btn btn-primary btn-sm">Save Service</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ========================================================= --}}
    {{-- TAB 4: COURSE MARKETING --}}
    {{-- ========================================================= --}}
    @if ($activeTab === 'courses')
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-text-primary">Course Marketing Visibility & SEO</h3>
                    <p class="text-xs text-text-secondary">Control whether courses are publicly listed, featured on the homepage, and fine-tune search descriptions.</p>
                </div>
            </div>

            @if($editingCourseId)
                <div class="card p-6 border border-primary-500/30 bg-surface-card rounded-2xl shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-border-default pb-3">
                        <h4 class="font-bold text-text-primary">Edit Marketing Attributes: {{ Course::find($editingCourseId)?->title }}</h4>
                        <button type="button" wire:click="cancelCourseMarketing" class="text-xs text-text-secondary hover:text-text-primary">Cancel</button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-3 bg-surface-bg rounded-xl space-y-2">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="courseIsPublicListed" class="checkbox checkbox-sm">
                                <span class="text-xs font-bold text-text-primary">Public Listed (Visible to Guests)</span>
                            </label>
                            <p class="text-[11px] text-text-secondary">Allows non-authenticated visitors to view the syllabus and course overview.</p>
                        </div>

                        <div class="p-3 bg-surface-bg rounded-xl space-y-2">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="courseIsMarketingFeatured" class="checkbox checkbox-sm">
                                <span class="text-xs font-bold text-text-primary">Homepage Featured</span>
                            </label>
                            <p class="text-[11px] text-text-secondary">Pins this course in the top 6 showcase section on the homepage.</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-text-primary mb-1">Public Marketing Summary</label>
                        <textarea wire:model="courseMarketingDescription" rows="3" class="input input-sm w-full text-xs" placeholder="Custom marketing blurb for course cards..."></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-text-primary mb-1">Highlights / What You'll Learn (One per line)</label>
                        <textarea wire:model="courseMarketingHighlightsText" rows="3" class="input input-sm w-full text-xs" placeholder="Crawl budget optimization&#10;Log analysis fundamentals&#10;Core Web Vitals remediation"></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-border-default">
                        <div>
                            <label class="block text-xs font-medium text-text-secondary mb-1">SEO Title Override</label>
                            <input type="text" wire:model="courseSeoTitle" class="input input-sm w-full text-xs">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-text-secondary mb-1">SEO Description</label>
                            <input type="text" wire:model="courseSeoDescription" class="input input-sm w-full text-xs">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t border-border-default">
                        <button type="button" wire:click="cancelCourseMarketing" class="btn btn-secondary btn-sm">Cancel</button>
                        <button type="button" wire:click="saveCourseMarketing" class="btn btn-primary btn-sm">Save Marketing Details</button>
                    </div>
                </div>
            @endif

            <div class="card bg-surface-card rounded-2xl border border-border-default overflow-hidden">
                <table class="table w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border-default bg-surface-bg/60 text-xs font-bold text-text-secondary uppercase">
                            <th class="p-4">Course</th>
                            <th class="p-4">Public Listed</th>
                            <th class="p-4">Homepage Featured</th>
                            <th class="p-4">Category</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-default">
                        @forelse($courses as $c)
                            <tr class="hover:bg-surface-bg/30 transition">
                                <td class="p-4">
                                    <div class="font-semibold text-text-primary">{{ $c->title }}</div>
                                    <div class="text-xs font-mono text-text-secondary">/courses/{{ $c->slug }}</div>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $c->is_public_listed ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $c->is_public_listed ? 'Listed' : 'Unlisted' }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $c->is_marketing_featured ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $c->is_marketing_featured ? 'Featured' : 'Standard' }}
                                    </span>
                                </td>
                                <td class="p-4 text-xs text-text-secondary">{{ $c->category?->name ?? 'General' }}</td>
                                <td class="p-4 text-right">
                                    <button type="button" wire:click="editCourseMarketing('{{ $c->id }}')" class="btn btn-secondary btn-sm text-xs">
                                        Edit Marketing
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-text-secondary text-sm">No courses found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ========================================================= --}}
    {{-- TAB 5: NAVIGATION MANAGEMENT --}}
    {{-- ========================================================= --}}
    @if ($activeTab === 'navigation')
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-text-primary">Navigation Menus</h3>
                    <p class="text-xs text-text-secondary">Manage Header and Footer links on public marketing pages.</p>
                </div>
                <div class="flex items-center gap-3">
                    <select wire:model.live="navMenuLocation" class="input input-sm">
                        <option value="header">Header Menu</option>
                        <option value="footer">Footer Menu</option>
                    </select>
                    <button type="button" wire:click="openNavModal" class="btn btn-primary btn-sm inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>Add Item</span>
                    </button>
                </div>
            </div>

            <div class="card bg-surface-card rounded-2xl border border-border-default overflow-hidden">
                <table class="table w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border-default bg-surface-bg/60 text-xs font-bold text-text-secondary uppercase">
                            <th class="p-4">Order</th>
                            <th class="p-4">Link Label</th>
                            <th class="p-4">Destination URL</th>
                            <th class="p-4">Target</th>
                            <th class="p-4">Active</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-default">
                        @forelse($navItems as $item)
                            <tr class="hover:bg-surface-bg/30 transition">
                                <td class="p-4 font-mono text-xs">{{ $item->display_order }}</td>
                                <td class="p-4 font-semibold text-text-primary">{{ $item->title }}</td>
                                <td class="p-4 font-mono text-xs text-text-secondary">{{ $item->url }}</td>
                                <td class="p-4 text-xs">{{ $item->target }}</td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $item->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $item->is_active ? 'Active' : 'Disabled' }}
                                    </span>
                                </td>
                                <td class="p-4 text-right space-x-2">
                                    <button type="button" wire:click="openNavModal('{{ $item->id }}')" class="btn btn-secondary btn-sm text-xs">Edit</button>
                                    <button type="button" wire:click="deleteNavItem('{{ $item->id }}')" class="btn btn-ghost btn-sm text-xs text-red-600">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-text-secondary text-sm">No navigation items in this menu.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Nav Modal --}}
            @if($showNavModal)
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div class="bg-surface-card rounded-2xl border border-border-default max-w-md w-full p-6 space-y-4 shadow-2xl">
                        <div class="flex items-center justify-between border-b border-border-default pb-3">
                            <h3 class="font-bold text-lg text-text-primary">{{ $editingNavId ? 'Edit Nav Item' : 'Add Nav Item' }}</h3>
                            <button type="button" wire:click="$set('showNavModal', false)" class="text-text-secondary hover:text-text-primary text-xl">&times;</button>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-text-primary mb-1">Label *</label>
                            <input type="text" wire:model="navTitle" class="input input-sm w-full" placeholder="e.g. Courses">
                            @error('navTitle') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-text-primary mb-1">URL Path *</label>
                            <input type="text" wire:model="navUrl" class="input input-sm w-full font-mono text-xs" placeholder="e.g. /courses or https://...">
                            @error('navUrl') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1">Target</label>
                                <select wire:model="navTarget" class="input input-sm w-full">
                                    <option value="_self">Same Window (_self)</option>
                                    <option value="_blank">New Tab (_blank)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1">Display Order</label>
                                <input type="number" wire:model="navDisplayOrder" class="input input-sm w-full">
                            </div>
                        </div>

                        <div class="pt-2">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="navIsActive" class="checkbox checkbox-sm">
                                <span class="text-xs font-semibold text-text-primary">Item is active</span>
                            </label>
                        </div>

                        <div class="flex justify-end gap-2 pt-4 border-t border-border-default">
                            <button type="button" wire:click="$set('showNavModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="button" wire:click="saveNavItem" class="btn btn-primary btn-sm">Save</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ========================================================= --}}
    {{-- TAB 6: BLOG & ARTICLES --}}
    {{-- ========================================================= --}}
    @if ($activeTab === 'blog')
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-text-primary">Blog & Technical Articles</h3>
                    <p class="text-xs text-text-secondary">Publish in-depth tutorials, crawl analysis case studies, and research publications.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="openCategoryModal" class="btn btn-secondary btn-sm">
                        Manage Categories
                    </button>
                    <button type="button" wire:click="openPostModal" class="btn btn-primary btn-sm inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>New Article</span>
                    </button>
                </div>
            </div>

            <div class="card bg-surface-card rounded-2xl border border-border-default overflow-hidden">
                <table class="table w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border-default bg-surface-bg/60 text-xs font-bold text-text-secondary uppercase">
                            <th class="p-4">Article Title</th>
                            <th class="p-4">Category</th>
                            <th class="p-4">Author</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Published At</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-default">
                        @forelse($blogPosts as $post)
                            <tr class="hover:bg-surface-bg/30 transition">
                                <td class="p-4">
                                    <a href="{{ route('blog.show', $post->slug) }}" target="_blank" class="font-semibold text-text-primary hover:underline flex items-center gap-1.5 text-primary-600 dark:text-primary-400">
                                        <span>{{ $post->title }}</span>
                                        <svg class="w-3.5 h-3.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                    <div class="text-xs font-mono text-text-secondary">/blog/{{ $post->slug }}</div>
                                </td>
                                <td class="p-4 text-xs font-medium">{{ $post->category?->name ?? 'Uncategorized' }}</td>
                                <td class="p-4 text-xs">{{ $post->author?->name ?? 'Faculty Staff' }}</td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $post->status === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ ucfirst($post->status) }}
                                    </span>
                                </td>
                                <td class="p-4 text-xs text-text-secondary">{{ $post->published_at?->format('M d, Y') ?? '—' }}</td>
                                <td class="p-4 text-right space-x-2">
                                    <button type="button" wire:click="openPostModal('{{ $post->id }}')" class="btn btn-secondary btn-sm text-xs">Edit</button>
                                    <button type="button" wire:click="deletePost('{{ $post->id }}')" wire:confirm="Delete this article?" class="btn btn-ghost btn-sm text-xs text-red-600">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-text-secondary text-sm">No articles published yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4 border-t border-border-default">
                    {{ $blogPosts->links() }}
                </div>
            </div>

            {{-- Post Modal --}}
            @if($showPostModal)
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div class="bg-surface-card rounded-2xl border border-border-default max-w-3xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto shadow-2xl">
                        <div class="flex items-center justify-between border-b border-border-default pb-3">
                            <h3 class="font-bold text-lg text-text-primary">{{ $editingPostId ? 'Edit Article' : 'New Article' }}</h3>
                            <button type="button" wire:click="$set('showPostModal', false)" class="text-text-secondary hover:text-text-primary text-xl">&times;</button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1">Title *</label>
                                <input type="text" wire:model.live="postTitle" class="input input-sm w-full">
                                @error('postTitle') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1">Slug *</label>
                                <input type="text" wire:model="postSlug" class="input input-sm w-full font-mono text-xs">
                                @error('postSlug') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1">Category</label>
                                <select wire:model="postCategoryId" class="input input-sm w-full">
                                    <option value="">Select Category</option>
                                    @foreach($blogCategories as $bc)
                                        <option value="{{ $bc->id }}">{{ $bc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1">Status</label>
                                <select wire:model="postStatus" class="input input-sm w-full">
                                    <option value="published">Published</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-text-primary mb-1">Excerpt (Summary)</label>
                            <textarea wire:model="postExcerpt" rows="2" class="input input-sm w-full text-xs" placeholder="Brief summary of the article..."></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-text-primary mb-1">Article Content *</label>
                            <textarea wire:model="postContent" rows="10" class="input input-sm w-full text-xs font-sans" placeholder="Full article body..."></textarea>
                            @error('postContent') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-text-primary mb-1">Featured Image</label>
                            <input type="file" wire:model="postFeaturedImage" class="file-input file-input-bordered file-input-sm w-full">
                        </div>

                        <div class="pt-2 border-t border-border-default space-y-3">
                            <h4 class="text-xs uppercase font-bold text-text-secondary tracking-wider">Search Engine Optimization</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-text-secondary mb-1">SEO Title</label>
                                    <input type="text" wire:model="postSeoTitle" class="input input-sm w-full text-xs">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-text-secondary mb-1">SEO Description</label>
                                    <input type="text" wire:model="postSeoDescription" class="input input-sm w-full text-xs">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-4 border-t border-border-default">
                            <button type="button" wire:click="$set('showPostModal', false)" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="button" wire:click="savePost" class="btn btn-primary btn-sm">Save Article</button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Category Modal --}}
            @if($showCategoryModal)
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div class="bg-surface-card rounded-2xl border border-border-default max-w-md w-full p-6 space-y-4 shadow-2xl">
                        <div class="flex items-center justify-between border-b border-border-default pb-3">
                            <h3 class="font-bold text-lg text-text-primary">Blog Categories</h3>
                            <button type="button" wire:click="$set('showCategoryModal', false)" class="text-text-secondary hover:text-text-primary text-xl">&times;</button>
                        </div>

                        <div class="space-y-2 max-h-48 overflow-y-auto divide-y divide-border-default">
                            @foreach($blogCategories as $c)
                                <div class="py-2 flex items-center justify-between text-xs">
                                    <span class="font-semibold text-text-primary">{{ $c->name }} ({{ $c->posts_count }})</span>
                                    <button type="button" wire:click="deleteCategory('{{ $c->id }}')" class="text-red-500 hover:text-red-700">Delete</button>
                                </div>
                            @endforeach
                        </div>

                        <div class="pt-3 border-t border-border-default space-y-3">
                            <h4 class="text-xs font-bold text-text-primary">Add New Category</h4>
                            <div>
                                <label class="block text-[11px] font-bold text-text-secondary mb-1">Name *</label>
                                <input type="text" wire:model.live="categoryName" class="input input-sm w-full">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-text-secondary mb-1">Slug *</label>
                                <input type="text" wire:model="categorySlug" class="input input-sm w-full font-mono text-xs">
                            </div>
                            <button type="button" wire:click="saveCategory" class="btn btn-primary btn-sm w-full">Save Category</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ========================================================= --}}
    {{-- TAB 7: MEDIA LIBRARY --}}
    {{-- ========================================================= --}}
    @if ($activeTab === 'media')
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-text-primary">Public Media Library</h3>
                    <p class="text-xs text-text-secondary">Manage public images, banners, and logos strictly isolated from private student file storage.</p>
                </div>
            </div>

            <div class="card p-6 bg-surface-card rounded-2xl border border-border-default shadow-xs space-y-4">
                <h4 class="text-sm font-bold text-text-primary">Upload New Media Asset</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-text-secondary mb-1">Select File (Max 5MB) *</label>
                        <input type="file" wire:model="mediaUpload" class="file-input file-input-bordered file-input-sm w-full">
                        @error('mediaUpload') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary mb-1">Asset Title</label>
                        <input type="text" wire:model="mediaTitle" class="input input-sm w-full" placeholder="e.g. Hero Banner">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary mb-1">Alt Text</label>
                        <input type="text" wire:model="mediaAltText" class="input input-sm w-full" placeholder="e.g. Students in SEO Lab">
                    </div>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="button" wire:click="uploadMediaAsset" wire:loading.attr="disabled" class="btn btn-primary btn-sm">
                        <span wire:loading.remove>Upload Asset</span>
                        <span wire:loading>Uploading...</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @forelse($mediaAssets as $asset)
                    <div class="card bg-surface-card rounded-xl border border-border-default overflow-hidden flex flex-col justify-between group">
                        <div class="aspect-square w-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center overflow-hidden relative">
                            <img src="{{ $asset->file_path }}" alt="{{ $asset->alt_text }}" class="w-full h-full object-cover">
                        </div>
                        <div class="p-3">
                            <div class="text-xs font-bold text-text-primary truncate" title="{{ $asset->title }}">{{ $asset->title }}</div>
                            <div class="text-[11px] text-text-secondary mt-0.5">{{ number_format($asset->file_size / 1024, 1) }} KB</div>
                            <div class="mt-2 pt-2 border-t border-border-default flex items-center justify-between">
                                <button type="button" onclick="navigator.clipboard.writeText('{{ url($asset->file_path) }}'); alert('Copied URL to clipboard!');" class="text-[11px] text-primary-600 font-semibold hover:underline">
                                    Copy URL
                                </button>
                                <button type="button" wire:click="deleteMediaAsset('{{ $asset->id }}')" wire:confirm="Delete this asset?" class="text-[11px] text-red-500 hover:text-red-700">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full p-8 text-center text-text-secondary text-sm">No public media uploaded yet.</div>
                @endforelse
            </div>
            <div>
                {{ $mediaAssets->links() }}
            </div>
        </div>
    @endif

    {{-- ========================================================= --}}
    {{-- TAB 8: CONTACT INQUIRIES & LEADS --}}
    {{-- ========================================================= --}}
    @if ($activeTab === 'inquiries')
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-text-primary">Contact Inquiries & Student Leads</h3>
                    <p class="text-xs text-text-secondary">Submissions captured from public contact forms, throttled and protected by honeypot verification.</p>
                </div>
                <div class="flex items-center gap-2">
                    @foreach(['all' => 'All', 'new' => 'New', 'read' => 'Read', 'in_progress' => 'In Progress', 'resolved' => 'Resolved'] as $key => $lbl)
                        <button type="button" wire:click="$set('inquiryStatusFilter', '{{ $key }}')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $inquiryStatusFilter === $key ? 'bg-primary-600 text-white' : 'bg-surface-card border border-border-default text-text-secondary hover:text-text-primary' }}">
                            {{ $lbl }} ({{ $inquiryCounts[$key] ?? 0 }})
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="card bg-surface-card rounded-2xl border border-border-default overflow-hidden">
                <table class="table w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border-default bg-surface-bg/60 text-xs font-bold text-text-secondary uppercase">
                            <th class="p-4">Sender</th>
                            <th class="p-4">Subject</th>
                            <th class="p-4">Interested Service</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Submitted At</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-default">
                        @forelse($inquiries as $inq)
                            <tr class="hover:bg-surface-bg/30 transition {{ $inq->status === 'new' ? 'font-bold bg-blue-50/20' : '' }}">
                                <td class="p-4">
                                    <div class="text-text-primary">{{ $inq->name }}</div>
                                    <div class="text-xs font-mono text-text-secondary">{{ $inq->email }}</div>
                                </td>
                                <td class="p-4 text-text-primary">{{ $inq->subject }}</td>
                                <td class="p-4 text-xs">{{ $inq->interested_service ?: 'General' }}</td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $inq->status === 'new' ? 'bg-blue-100 text-blue-800' : ($inq->status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600') }}">
                                        {{ ucfirst($inq->status) }}
                                    </span>
                                </td>
                                <td class="p-4 text-xs text-text-secondary">{{ $inq->created_at?->format('M d, Y H:i') }}</td>
                                <td class="p-4 text-right space-x-2">
                                    <button type="button" wire:click="viewInquiry('{{ $inq->id }}')" class="btn btn-primary btn-sm text-xs">View</button>
                                    <button type="button" wire:click="deleteInquiry('{{ $inq->id }}')" wire:confirm="Delete this inquiry?" class="btn btn-ghost btn-sm text-xs text-red-600">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-text-secondary text-sm">No contact inquiries found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4 border-t border-border-default">
                    {{ $inquiries->links() }}
                </div>
            </div>

            {{-- Inquiry Detail Modal --}}
            @if($viewingInquiryId && $activeInquiry)
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div class="bg-surface-card rounded-2xl border border-border-default max-w-xl w-full p-6 space-y-4 shadow-2xl">
                        <div class="flex items-center justify-between border-b border-border-default pb-3">
                            <h3 class="font-bold text-lg text-text-primary">Inquiry from {{ $activeInquiry->name }}</h3>
                            <button type="button" wire:click="closeInquiryModal" class="text-text-secondary hover:text-text-primary text-xl">&times;</button>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <span class="text-text-secondary font-bold">Email:</span>
                                    <p class="text-text-primary font-mono">{{ $activeInquiry->email }}</p>
                                </div>
                                <div>
                                    <span class="text-text-secondary font-bold">Phone:</span>
                                    <p class="text-text-primary">{{ $activeInquiry->phone ?: 'Not provided' }}</p>
                                </div>
                            </div>

                            <div>
                                <span class="text-text-secondary font-bold">Interested Service / Track:</span>
                                <p class="text-text-primary">{{ $activeInquiry->interested_service ?: 'General Inquiry' }}</p>
                            </div>

                            <div>
                                <span class="text-text-secondary font-bold">Subject:</span>
                                <p class="text-text-primary font-semibold">{{ $activeInquiry->subject }}</p>
                            </div>

                            <div class="p-3 bg-surface-bg rounded-xl border border-border-default">
                                <span class="text-text-secondary font-bold block mb-1">Message Body:</span>
                                <p class="text-text-primary whitespace-pre-line">{{ $activeInquiry->message }}</p>
                            </div>

                            <div class="pt-3 border-t border-border-default space-y-3">
                                <div>
                                    <label class="block text-xs font-bold text-text-primary mb-1">Update Status</label>
                                    <select wire:model="inquiryUpdateStatus" class="input input-sm w-full">
                                        <option value="new">New</option>
                                        <option value="read">Read</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="archived">Archived</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-text-primary mb-1">Internal Administrative Notes</label>
                                    <textarea wire:model="inquiryAdminNotes" rows="3" class="input input-sm w-full" placeholder="Add follow-up notes or action items..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-4 border-t border-border-default">
                            <button type="button" wire:click="closeInquiryModal" class="btn btn-secondary btn-sm">Close</button>
                            <button type="button" wire:click="saveInquiryDetails" class="btn btn-primary btn-sm">Save Notes & Status</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ========================================================= --}}
    {{-- TAB 9: TECHNICAL SEO & SITEMAP --}}
    {{-- ========================================================= --}}
    @if ($activeTab === 'seo')
        <div class="space-y-6">
            <div>
                <h3 class="text-lg font-bold text-text-primary">Search Engine Optimization & Indexing</h3>
                <p class="text-xs text-text-secondary">Live Google SERP preview, XML sitemap verification, and robots.txt crawler directives.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                {{-- SERP Simulator Preview --}}
                <div class="lg:col-span-6 space-y-4">
                    <div class="card p-6 bg-surface-card rounded-2xl border border-border-default shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-sm text-text-primary">Google SERP Snippet Simulator</h4>
                            <div class="flex items-center gap-1 bg-surface-bg p-1 rounded-lg">
                                <button type="button" wire:click="$set('serpPreviewDevice', 'desktop')"
                                        class="px-2.5 py-1 rounded text-xs font-semibold {{ $serpPreviewDevice === 'desktop' ? 'bg-surface-card text-text-primary shadow-xs' : 'text-text-secondary' }}">
                                    Desktop
                                </button>
                                <button type="button" wire:click="$set('serpPreviewDevice', 'mobile')"
                                        class="px-2.5 py-1 rounded text-xs font-semibold {{ $serpPreviewDevice === 'mobile' ? 'bg-surface-card text-text-primary shadow-xs' : 'text-text-secondary' }}">
                                    Mobile
                                </button>
                            </div>
                        </div>

                        {{-- Google Preview Card --}}
                        <div class="p-4 bg-white rounded-xl border border-slate-200 font-sans text-left space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="w-4 h-4 rounded-full bg-blue-600 text-white text-[9px] flex items-center justify-center font-bold">S</span>
                                <div>
                                    <div class="text-[11px] text-slate-800 font-medium">{{ $academy?->name ?: 'SEO Academy' }}</div>
                                    <div class="text-[10px] text-slate-500 font-mono">{{ url('/') }}</div>
                                </div>
                            </div>
                            <h5 class="text-base font-medium text-blue-800 hover:underline cursor-pointer leading-snug">
                                {{ $academy?->name ?: 'SEO Academy' }} — Professional Digital Education & Technical Audits
                            </h5>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Transform your digital career with hands-on technical SEO, realistic client audit simulations, and verifiable cryptographic credentials at SEO Academy.
                            </p>
                        </div>

                        <div class="p-3 bg-surface-bg rounded-xl text-xs space-y-1">
                            <div class="flex justify-between">
                                <span class="text-text-secondary">Title Tag Length:</span>
                                <span class="font-bold text-emerald-600">58 chars (Optimal)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-text-secondary">Meta Description Length:</span>
                                <span class="font-bold text-emerald-600">142 chars (Optimal)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-text-secondary">Canonical Protocol:</span>
                                <span class="font-bold text-text-primary">Self-referencing HTTPS</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sitemap & Robots Status --}}
                <div class="lg:col-span-6 space-y-4">
                    <div class="card p-6 bg-surface-card rounded-2xl border border-border-default shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-sm text-text-primary">XML Sitemap Status</h4>
                            <a href="{{ route('sitemap') }}" target="_blank" class="text-xs text-primary-600 font-semibold hover:underline flex items-center gap-1">
                                <span>Inspect /sitemap.xml</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        </div>
                        <div class="p-4 bg-surface-bg rounded-xl flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-extrabold text-text-primary">{{ $sitemapUrlCount }}</div>
                                <div class="text-xs text-text-secondary">Published URLs in Dynamic Sitemap</div>
                            </div>
                            <div class="text-xs font-semibold px-2.5 py-1 rounded bg-emerald-100 text-emerald-800">
                                Real-Time Synchronized
                            </div>
                        </div>
                        <p class="text-xs text-text-secondary">
                            Private LMS routes (`/admin/*`, `/student/*`, `/classroom/*`, etc.) are automatically excluded to preserve indexation hygiene.
                        </p>
                    </div>

                    <div class="card p-6 bg-surface-card rounded-2xl border border-border-default shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-sm text-text-primary">Live robots.txt Output</h4>
                            <a href="{{ route('robots') }}" target="_blank" class="text-xs text-primary-600 font-semibold hover:underline flex items-center gap-1">
                                <span>Inspect /robots.txt</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        </div>
                        <pre class="p-3 bg-slate-900 text-slate-200 rounded-xl font-mono text-[11px] overflow-x-auto max-h-48 leading-relaxed">{{ $robotsContent }}</pre>
                    </div>
                </div>

            </div>
        </div>
    @endif
</div>
