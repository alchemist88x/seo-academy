<div class="space-y-6">
    <x-page-header
        title="Public Website Chatbot & Admissions Assistant"
        subtitle="Manage visitor assistance, FAQ knowledge base, course recommendations, admissions lead generation, and optional AI integration."
    >
        <x-slot:actions>
            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="{{ route('admin.website.index') }}" class="btn btn-secondary btn-sm">
                    &larr; CMS Content
                </a>
                <a href="{{ route('admin.website.appearance') }}" class="btn btn-secondary btn-sm">
                    Appearance
                </a>
                <a href="{{ route('admin.website.banners') }}" class="btn btn-secondary btn-sm">
                    Banners
                </a>
                <a href="{{ route('admin.chat-conversations') }}" class="btn btn-primary btn-sm inline-flex items-center gap-1.5 shadow-xs">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <span>Live Chat Inbox</span>
                </a>
                <a href="{{ url('/') }}" target="_blank" class="btn btn-secondary btn-sm inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    <span>Test on Site</span>
                </a>
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

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-1">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Inquiries</span>
            <div class="text-2xl font-extrabold text-slate-900">{{ number_format($stats['total_interactions']) }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-1">
            <span class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Questions Answered</span>
            <div class="text-2xl font-extrabold text-emerald-700">{{ number_format($stats['answered_count']) }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-1">
            <span class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Admissions Handoffs</span>
            <div class="text-2xl font-extrabold text-amber-700">{{ number_format($stats['handoff_count']) }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-1">
            <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wider">Leads Captured</span>
            <div class="text-2xl font-extrabold text-indigo-700">{{ number_format($stats['leads_count'] ?? 0) }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-1">
            <span class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Knowledge FAQs</span>
            <div class="text-2xl font-extrabold text-blue-700">{{ number_format($stats['faq_count']) }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-2xs space-y-1">
            <span class="text-xs font-semibold text-purple-600 uppercase tracking-wider">Tokens & Est. Cost</span>
            <div class="text-2xl font-extrabold text-purple-700">{{ number_format($stats['total_tokens'] ?? 0) }}</div>
            <div class="text-2xs text-slate-400 font-mono">~${{ number_format($stats['estimated_cost'] ?? 0, 4) }} USD</div>
        </div>
    </div>

    {{-- Navigation Tabs --}}
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex space-x-3 sm:space-x-6 overflow-x-auto text-xs font-semibold">
            <button type="button" wire:click="$set('activeTab', 'general')"
                    class="py-3 border-b-2 whitespace-nowrap {{ $activeTab === 'general' ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Appearance & Pages
            </button>
            <button type="button" wire:click="$set('activeTab', 'faqs')"
                    class="py-3 border-b-2 whitespace-nowrap {{ $activeTab === 'faqs' ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                FAQ Knowledge Base ({{ $faqs->count() }})
            </button>
            <button type="button" wire:click="$set('activeTab', 'suggestions')"
                    class="py-3 border-b-2 whitespace-nowrap {{ $activeTab === 'suggestions' ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Suggested Questions
            </button>
            <button type="button" wire:click="$set('activeTab', 'ai')"
                    class="py-3 border-b-2 whitespace-nowrap {{ $activeTab === 'ai' ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                AI Engine & Limits
            </button>
            <button type="button" wire:click="$set('activeTab', 'sources')"
                    class="py-3 border-b-2 whitespace-nowrap {{ $activeTab === 'sources' ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Linked Knowledge Sources
            </button>
            <button type="button" wire:click="$set('activeTab', 'logs')"
                    class="py-3 border-b-2 whitespace-nowrap {{ $activeTab === 'logs' ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Audit & Lead Logs
            </button>
        </nav>
    </div>

    {{-- Tab 1: General & Appearance --}}
    @if($activeTab === 'general')
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-6 max-w-3xl">
            <form wire:submit.prevent="saveGeneralSettings" class="space-y-6">
                
                {{-- Master Enabled Toggle --}}
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <div>
                        <span class="font-bold text-sm text-slate-900 block">Public Website Chatbot</span>
                        <span class="text-xs text-slate-500">Enable or disable the floating assistant on public marketing pages.</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="isEnabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Assistant Display Name *</label>
                        <input type="text" wire:model="name" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                        @error('name') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Brand Color *</label>
                        <div class="flex items-center space-x-2">
                            <input type="color" wire:model="brandColor" class="w-9 h-9 rounded-lg p-0.5 border border-slate-300">
                            <input type="text" wire:model="brandColor" class="w-full px-3 py-2 text-xs font-mono uppercase bg-slate-50 border border-slate-300 rounded-xl">
                        </div>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-slate-700">Welcome Message *</label>
                    <textarea wire:model="welcomeMessage" rows="2" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl"></textarea>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-slate-700">Initial Guidance / Prompt</label>
                    <textarea wire:model="initialGreeting" rows="2" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Screen Position</label>
                        <select wire:model="position" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                            <option value="bottom_right">Bottom Right (Standard)</option>
                            <option value="bottom_left">Bottom Left</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Launcher Icon</label>
                        <select wire:model="launcherIcon" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                            <option value="message-circle">Chat Bubble</option>
                            <option value="bot">Robot Mascot</option>
                            <option value="sparkle">AI Sparkle</option>
                            <option value="help-circle">Question Help</option>
                        </select>
                    </div>
                </div>

                {{-- Page Visibility Settings --}}
                <div class="space-y-2 pt-2 border-t border-slate-200">
                    <label class="block text-xs font-bold text-slate-800">Public Page Visibility</label>
                    <p class="text-xs text-slate-500">Configure which public website sections display the floating chatbot widget.</p>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 pt-2">
                        @php
                            $availablePages = [
                                'all' => 'All Public Pages',
                                'home' => 'Homepage',
                                'courses' => 'Course Catalog',
                                'services' => 'Services Index',
                                'blog' => 'Blog & Articles',
                                'contact' => 'Contact Page',
                                'about' => 'About & Pages',
                            ];
                        @endphp
                        @foreach($availablePages as $code => $title)
                            <label class="flex items-center space-x-2 p-2 rounded-lg bg-slate-50 border border-slate-200 cursor-pointer text-xs">
                                <input type="checkbox"
                                       value="{{ $code }}"
                                       wire:model="visibilityPages"
                                       class="rounded text-blue-600 focus:ring-blue-500 w-3.5 h-3.5">
                                <span class="font-medium text-slate-700">{{ $title }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Operating Mode & Human Support Configuration --}}
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-bold text-xs text-slate-900 block">Default Chatbot Operating Mode</span>
                            <span class="text-[11px] text-slate-500">Configure how the assistant functions for new visitor sessions. AI remains entirely optional.</span>
                        </div>
                        <select wire:model="defaultOperatingMode" class="text-xs bg-white border border-slate-300 rounded-xl px-3 py-1.5 font-medium">
                            <option value="auto">Auto (Intelligent AI/Human Routing - Recommended)</option>
                            <option value="ai_enabled">AI-Enabled (AI Generative + Grounded Knowledge)</option>
                            <option value="fallback_only">Deterministic Fallback Only (100% Non-AI)</option>
                            <option value="human_support">Human Support First (Staff Handled)</option>
                        </select>
                    </div>

                    <div class="pt-2 border-t border-slate-200/80 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-bold text-xs text-slate-800 block">Human Support Desk Available</span>
                                <span class="text-[10px] text-slate-500">Allows visitors to request live assistance from staff.</span>
                            </div>
                            <input type="checkbox" wire:model="humanSupportEnabled" class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-[11px] font-semibold text-slate-700">Support Offline Notice</label>
                            <input type="text" wire:model="supportOfflineMessage" placeholder="Leave empty for default message..." class="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-xl">
                        </div>
                    </div>
                </div>

                {{-- Lead Capture in Chat --}}
                <div class="p-4 bg-blue-50/70 border border-blue-200 rounded-xl space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-bold text-xs text-blue-900 block">Consent-Based Lead Capture Flow</span>
                            <span class="text-[11px] text-blue-700">Offers in-chat enquiry submission when visitors show admissions interest.</span>
                        </div>
                        <input type="checkbox" wire:model="leadCaptureEnabled" class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Admissions Contact CTA Label</label>
                        <input type="text" wire:model="contactSupportCta" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                    </div>

                    <div class="space-y-2 pt-5">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" wire:model="desktopVisibility" class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4">
                            <span class="text-xs font-medium text-slate-800">Show on Desktop Screens</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" wire:model="mobileVisibility" class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4">
                            <span class="text-xs font-medium text-slate-800">Show on Mobile Devices</span>
                        </label>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-200 flex justify-end">
                    <button type="submit" class="btn btn-primary btn-sm shadow-sm">
                        Save Appearance Settings
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Tab 2: FAQ Knowledge Base --}}
    @if($activeTab === 'faqs')
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-sm text-slate-900">Curated FAQ Knowledge</h3>
                    <p class="text-xs text-slate-500">The assistant checks these authoritative FAQs first before course recommendations or admissions handoff.</p>
                </div>
                <button type="button" wire:click="openCreateFaqModal" class="btn btn-primary btn-sm inline-flex items-center gap-1.5 shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Add Knowledge FAQ</span>
                </button>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                @if($faqs->isEmpty())
                    <div class="p-12 text-center text-slate-500">
                        <p class="font-medium text-slate-700">No FAQs found.</p>
                        <p class="text-xs text-slate-400 mt-1">Add common student admissions questions to power instant answers in the chatbot.</p>
                        <button type="button" wire:click="openCreateFaqModal" class="btn btn-primary btn-sm mt-4">
                            Add First FAQ
                        </button>
                    </div>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach($faqs as $faq)
                            <div class="p-5 hover:bg-slate-50/60 transition flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                <div class="space-y-1.5 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ $faq->category ?: 'General' }}
                                        </span>
                                        <span class="text-xs font-bold text-slate-900">{{ $faq->question }}</span>
                                    </div>
                                    <p class="text-xs text-slate-600 leading-relaxed whitespace-pre-line">{{ $faq->answer }}</p>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <button type="button"
                                            wire:click="toggleFaqActive('{{ $faq->id }}')"
                                            class="px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $faq->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' }}">
                                        {{ $faq->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                    <button type="button" wire:click="openEditFaqModal('{{ $faq->id }}')" class="btn btn-secondary btn-xs">
                                        Edit
                                    </button>
                                    <button type="button"
                                            wire:click="deleteFaq('{{ $faq->id }}')"
                                            wire:confirm="Delete this FAQ?"
                                            class="btn btn-secondary btn-xs text-red-600 hover:text-red-700">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Tab 3: Suggested Questions --}}
    @if($activeTab === 'suggestions')
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-6 max-w-3xl">
            <div>
                <h3 class="font-bold text-sm text-slate-900">Suggested Question Chips</h3>
                <p class="text-xs text-slate-500">Quick prompts shown to visitors when opening the chatbot for one-click answers.</p>
            </div>

            {{-- Add New Question Input --}}
            <div class="flex gap-2">
                <input type="text"
                       wire:model="newSuggestedQuestion"
                       wire:keydown.enter.prevent="addSuggestedQuestion"
                       placeholder="e.g. What practical SEO projects do students complete?"
                       class="flex-1 px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                <button type="button" wire:click="addSuggestedQuestion" class="btn btn-primary btn-sm">
                    Add Prompt
                </button>
            </div>

            {{-- Existing Questions List --}}
            <div class="space-y-2">
                @foreach($suggestedQuestions as $idx => $q)
                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                        <span class="font-medium text-slate-800">{{ $q }}</span>
                        <button type="button" wire:click="removeSuggestedQuestion({{ $idx }})" class="text-slate-400 hover:text-red-600 font-bold px-2">
                            &times;
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-between items-center">
                <button type="button" wire:click="resetSuggestedQuestions" class="btn btn-secondary btn-xs text-slate-600">
                    Restore Defaults
                </button>
            </div>
        </div>
    @endif

    {{-- Tab 4: AI Engine & Limits --}}
    @if($activeTab === 'ai')
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-6 max-w-3xl">
            <div>
                <h3 class="font-bold text-sm text-slate-900">Configurable AI Model & Cost Safeguards</h3>
                <p class="text-xs text-slate-500">When enabled, queries that don't match static FAQs are answered using an LLM strictly grounded on public academy course syllabi and services.</p>
            </div>

            <div class="p-4 bg-blue-50/80 rounded-xl border border-blue-200 text-xs text-blue-900 space-y-1">
                <span class="font-bold block">🔒 Grounding & Privacy Isolation</span>
                <p>Private student records, assessments, internal marks, and administrative credentials are never accessed or sent to the AI model. API keys are encrypted at rest with AES-256 and never sent to client browsers.</p>
            </div>

            <form wire:submit.prevent="saveAiSettings" class="space-y-5">
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <div>
                        <span class="font-bold text-xs text-slate-900 block">Enable AI Generative Responses</span>
                        <span class="text-[11px] text-slate-500">Falls back to static contact handoff if disabled.</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="aiEnabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">AI Provider</label>
                        <select wire:model="aiProvider" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                            <option value="null">None (Static Grounding Only)</option>
                            <option value="openai">OpenAI Compatible (Default)</option>
                            <option value="anthropic">Anthropic Claude</option>
                            <option value="ollama">Ollama / Local LLM</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Model Name</label>
                        <input type="text" wire:model="aiModel" placeholder="gpt-4o-mini" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-slate-700">API Endpoint (Optional)</label>
                    <input type="url" wire:model="aiEndpoint" placeholder="https://api.openai.com/v1/chat/completions" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                    <span class="text-[11px] text-slate-400">Leave blank to use default OpenAI endpoint.</span>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-semibold text-slate-700">API Secret Key (Stored Encrypted)</label>
                    <input type="password" wire:model="aiApiKey" placeholder="sk-..." class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl font-mono">
                    <span class="text-[11px] text-slate-400">Entered keys are never visible in browser inspect tools.</span>
                </div>

                {{-- Connectivity Test Button --}}
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div>
                        <span class="text-xs font-bold text-slate-800 block">Test Provider Connectivity</span>
                        <span class="text-[11px] text-slate-500">Send a fast low-token ping to confirm endpoint & credentials.</span>
                    </div>

                    <div class="flex items-center gap-2">
                        @if($testPingResult)
                            <span class="text-xs font-semibold {{ $testPingSuccess ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $testPingResult }}
                            </span>
                        @endif
                        <button type="button"
                                wire:click="testProviderConnection"
                                wire:loading.attr="disabled"
                                class="btn btn-secondary btn-xs shrink-0 inline-flex items-center gap-1.5">
                            <span wire:loading.remove wire:target="testProviderConnection">Ping Provider</span>
                            <span wire:loading wire:target="testProviderConnection">Pinging...</span>
                        </button>
                    </div>
                </div>

                {{-- Cost & Usage Limits --}}
                <div class="pt-2 border-t border-slate-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Performance & Budget Safeguards</h4>
                        @if($this->isCircuitBreakerOpen)
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    <span>Circuit Breaker Open (Tripped)</span>
                                </span>
                                <button type="button" wire:click="resetCircuitBreaker" class="btn btn-secondary btn-xs text-xs">
                                    Reset Circuit Breaker
                                </button>
                            </div>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Circuit Healthy
                            </span>
                        @endif
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Request Timeout (seconds)</label>
                            <input type="number" min="5" max="60" wire:model="aiTimeout" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Circuit Breaker Cooldown (seconds)</label>
                            <input type="number" min="30" max="3600" wire:model="circuitBreakerCooldown" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Daily Request Limit (Budget Protection)</label>
                            <input type="number" min="10" max="5000" wire:model="dailyRequestLimit" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Rate Limit Per Minute (Per IP)</label>
                            <input type="number" min="5" max="60" wire:model="rateLimitPerMinute" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Conversation Retention (Days)</label>
                            <input type="number" min="1" max="365" wire:model="retentionDays" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Max Output Tokens ({{ $aiMaxTokens }})</label>
                            <input type="number" min="50" max="2000" wire:model="aiMaxTokens" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Temperature ({{ $aiTemperature }})</label>
                            <input type="range" min="0" max="1" step="0.1" wire:model="aiTemperature" class="w-full mt-2">
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-200 flex justify-end">
                    <button type="submit" class="btn btn-primary btn-sm shadow-sm">
                        Save AI Configuration
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Tab 5: Linked Knowledge Sources --}}
    @if($activeTab === 'sources')
        <div class="space-y-4">
            <div>
                <h3 class="font-bold text-sm text-slate-900">Linked Public Knowledge Sources</h3>
                <p class="text-xs text-slate-500">The assistant dynamically retrieves content from these verified public databases before generating an answer.</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-2xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700">Published Courses</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">{{ $knowledgeStats['published_courses'] }}</span>
                    </div>
                    <p class="text-[11px] text-slate-500">Only public-listed courses are visible. Draft courses are completely isolated.</p>
                </div>

                <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-2xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700">Published Services</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">{{ $knowledgeStats['published_services'] }}</span>
                    </div>
                    <p class="text-[11px] text-slate-500">Corporate coaching, audit consultancy, and agency packages.</p>
                </div>

                <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-2xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700">Published CMS Pages</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">{{ $knowledgeStats['published_pages'] }}</span>
                    </div>
                    <p class="text-[11px] text-slate-500">About academy, privacy policy, and terms of service.</p>
                </div>

                <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-2xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700">Published Blog Articles</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">{{ $knowledgeStats['published_blog_posts'] }}</span>
                    </div>
                    <p class="text-[11px] text-slate-500">Technical search tutorials, Core Web Vitals guides, and SEO insights.</p>
                </div>

                <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-2xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700">Active Curated FAQs</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">{{ $knowledgeStats['active_faqs'] }}</span>
                    </div>
                    <p class="text-[11px] text-slate-500">First-line admissions answers on certificates, refunds, and syllabus.</p>
                </div>
            </div>

            <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 text-xs text-emerald-900 space-y-1">
                <span class="font-bold block">✓ Strict Anti-Hallucination & Zero-Private-Leakage Guarantee</span>
                <p>Private classroom lessons, student assessment scores, personal grades, and internal notes are structurally excluded from the retrieval pipeline.</p>
            </div>
        </div>
    @endif

    {{-- Tab 6: Analytics & Logs --}}
    @if($activeTab === 'logs')
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" wire:click="$set('logOutcome', 'all')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $logOutcome === 'all' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                        All Outcomes ({{ $stats['total_interactions'] }})
                    </button>
                    <button type="button" wire:click="$set('logOutcome', 'lead_converted')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $logOutcome === 'lead_converted' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                        Leads ({{ $stats['leads_count'] ?? 0 }})
                    </button>
                    <button type="button" wire:click="$set('logOutcome', 'handoff')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $logOutcome === 'handoff' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                        Handoffs ({{ $stats['handoff_count'] }})
                    </button>
                    <button type="button" wire:click="$set('logOutcome', 'answered')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $logOutcome === 'answered' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                        Answered ({{ $stats['answered_count'] }})
                    </button>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <input type="text"
                           wire:model.live.debounce.300ms="logSearch"
                           placeholder="Search inquiries..."
                           class="w-full sm:w-56 px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl">
                    <button type="button"
                            wire:click="pruneOldLogs"
                            wire:confirm="Prune logs older than {{ $retentionDays }} days?"
                            class="btn btn-secondary btn-xs shrink-0 text-slate-600">
                        Prune ({{ $retentionDays }}d)
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                @if($logs->isEmpty())
                    <div class="p-12 text-center text-slate-500 text-xs">
                        No chatbot conversation logs recorded matching current filters.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="px-5 py-3.5">Time</th>
                                    <th class="px-5 py-3.5">Visitor Question</th>
                                    <th class="px-5 py-3.5">Assistant Response</th>
                                    <th class="px-5 py-3.5">Source</th>
                                    <th class="px-5 py-3.5">Outcome</th>
                                    <th class="px-5 py-3.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($logs as $log)
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="px-5 py-4 whitespace-nowrap text-slate-400 font-mono text-[11px]">
                                            {{ $log->created_at->format('M d, H:i') }}
                                        </td>
                                        <td class="px-5 py-4 font-medium text-slate-900 max-w-xs truncate">
                                            {{ $log->question }}
                                        </td>
                                        <td class="px-5 py-4 text-slate-600 max-w-md truncate">
                                            {{ $log->answer }}
                                        </td>
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200">
                                                {{ str_replace('_', ' ', $log->source) }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            @if($log->is_lead_converted)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                    Lead Converted
                                                </span>
                                            @elseif($log->is_handoff)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                    Admissions Handoff
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    Answered
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 whitespace-nowrap text-right">
                                            <button type="button"
                                                    wire:click="deleteLog('{{ $log->id }}')"
                                                    wire:confirm="Delete this conversation record?"
                                                    class="text-red-500 hover:text-red-700 font-semibold text-xs">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-slate-200">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Create / Edit FAQ Modal --}}
    @if($showFaqModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/80">
                    <h3 class="font-bold text-slate-900 text-base">
                        {{ $editingFaqId ? 'Edit Knowledge FAQ' : 'Create Knowledge FAQ' }}
                    </h3>
                    <button type="button" wire:click="$set('showFaqModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">
                        &times;
                    </button>
                </div>

                <form wire:submit.prevent="saveFaq" class="p-6 space-y-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Question *</label>
                        <input type="text" wire:model="faqQuestion" placeholder="e.g. Do I receive a verifiable certificate?" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                        @error('faqQuestion') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700">Answer *</label>
                        <textarea wire:model="faqAnswer" rows="4" placeholder="Comprehensive explanation provided by the assistant..." class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl"></textarea>
                        @error('faqAnswer') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Category</label>
                            <input type="text" wire:model="faqCategory" placeholder="Admissions, Syllabus, Certification" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-slate-700">Display Order</label>
                            <input type="number" min="0" wire:model="faqDisplayOrder" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl">
                        </div>
                    </div>

                    <div class="pt-2 flex items-center justify-between">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" wire:model="faqIsActive" class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4">
                            <span class="text-xs font-medium text-slate-800">FAQ is Active</span>
                        </label>

                        <div class="flex items-center space-x-2">
                            <button type="button" wire:click="$set('showFaqModal', false)" class="btn btn-secondary btn-sm">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm shadow-sm">
                                Save FAQ
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
