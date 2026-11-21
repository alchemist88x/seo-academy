<div id="seo-chatbot-root"
     x-data="{ isExpanded: @entangle('isOpen') }"
     x-on:keydown.escape.window="if (isExpanded) $wire.closeChat()"
     class="fixed z-50 transition-all duration-300 {{ ($settings?->position ?? 'bottom_right') === 'bottom_left' ? 'left-4 sm:left-6' : 'right-4 sm:right-6' }} bottom-4 sm:bottom-6 {{ !($settings?->desktop_visibility ?? true) ? 'md:hidden' : '' }} {{ !($settings?->mobile_visibility ?? true) ? 'max-sm:hidden' : '' }} {{ ! $this->isVisible() ? 'hidden' : '' }}">

    {{-- Chat Window Modal --}}
    @if($isOpen)
        <div class="relative flex flex-col w-[calc(100vw-2rem)] sm:w-[420px] md:w-[460px] h-[600px] max-h-[85vh] bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in slide-in-from-bottom-5 duration-200 mb-3"
             style="box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);"
             role="dialog"
             aria-modal="true"
             aria-label="SEO Academy Admissions Assistant">
            
            {{-- Header --}}
            <div class="px-5 py-4 text-white flex items-center justify-between shadow-xs select-none"
                 style="background-color: {{ $settings?->brand_color ?: '#2563eb' }};">
                <div class="flex items-center space-x-3">
                    <div class="relative w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/30 font-bold text-lg">
                        @if($settings?->avatar_path)
                            <img src="{{ asset($settings->avatar_path) }}" alt="{{ $settings->name }}" class="w-full h-full object-cover rounded-xl">
                        @else
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                        @endif
                        <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-400 border-2 border-white rounded-full"></span>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <h3 class="font-bold text-base tracking-tight leading-tight text-white">
                                {{ $settings?->name ?: 'SEO Academy Assistant' }}
                            </h3>
                            @if($visitorName)
                                <span class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-white/20 text-white tracking-wide">
                                    {{ $visitorName }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-blue-100 flex items-center space-x-1.5 mt-0.5">
                            @if($isWaitingForSupport)
                                <span class="w-2 h-2 rounded-full bg-emerald-400 inline-block animate-pulse"></span>
                                <span class="font-medium text-emerald-200">Support Advisor Active</span>
                            @else
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span>
                                <span>Admissions & Course Discovery Guide</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-1">
                    {{-- Connect with Human Support --}}
                    @if($settings?->human_support_enabled && ! $isWaitingForSupport)
                        <button wire:click="requestHumanSupport"
                                type="button"
                                title="Talk to Human Support"
                                class="p-2 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition text-xs font-semibold flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </button>
                    @endif

                    {{-- Open Admissions Callback Form --}}
                    @if($settings?->lead_capture_enabled)
                        <button wire:click="openLeadForm"
                                type="button"
                                title="Talk to Admissions"
                                class="p-2 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition text-xs font-semibold flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </button>
                    @endif

                    {{-- Reset / Clear conversation --}}
                    <button wire:click="clearConversation"
                            type="button"
                            title="Reset Conversation"
                            class="p-2 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>

                    {{-- Close Button --}}
                    <button wire:click="closeChat"
                            type="button"
                            aria-label="Close Assistant"
                            class="p-2 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Conversation Body --}}
            <div class="flex-1 p-4 overflow-y-auto space-y-4 bg-slate-50/70"
                 @if($isWaitingForSupport) wire:poll.4s="pollNewMessages" @endif
                 x-data="{ scrollToBottom() { this.$el.scrollTop = this.$el.scrollHeight } }"
                 x-init="scrollToBottom()"
                 x-effect="$wire.conversation; setTimeout(() => scrollToBottom(), 50)">
                
                @foreach($conversation as $msg)
                    @if($msg['sender'] === 'user')
                        {{-- User Message --}}
                        <div class="flex justify-end">
                            <div class="max-w-[85%] rounded-2xl rounded-tr-xs px-4 py-2.5 text-white shadow-xs text-sm"
                                 style="background-color: {{ $settings?->brand_color ?: '#2563eb' }};">
                                <p class="leading-relaxed whitespace-pre-line">{{ $msg['text'] }}</p>
                                <span class="text-[10px] text-white/70 block text-right mt-1">{{ $msg['time'] }}</span>
                            </div>
                        </div>
                    @elseif($msg['sender'] === 'admin' || $msg['sender'] === 'support_staff')
                        {{-- Human Staff Reply Message --}}
                        <div class="flex items-start space-x-2.5">
                            <div class="w-8 h-8 rounded-xl shrink-0 flex items-center justify-center text-white text-xs font-bold shadow-xs bg-emerald-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div class="max-w-[88%] space-y-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-emerald-800">{{ $msg['sender_name'] ?? 'Support Team' }}</span>
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-semibold bg-emerald-100 text-emerald-800">Support Agent</span>
                                </div>
                                <div class="bg-white rounded-2xl rounded-tl-xs px-4 py-3 text-slate-800 shadow-xs border border-emerald-200 text-sm">
                                    <div class="prose prose-sm max-w-none text-slate-800 leading-relaxed whitespace-pre-line">
                                        {{ $msg['text'] }}
                                    </div>
                                    <span class="text-[10px] text-slate-400 block mt-1.5">{{ $msg['time'] }}</span>
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Bot Message --}}
                        <div class="flex items-start space-x-2.5">
                            <div class="w-8 h-8 rounded-xl shrink-0 flex items-center justify-center text-white text-xs font-bold shadow-xs"
                                 style="background-color: {{ $settings?->brand_color ?: '#2563eb' }};">
                                🎓
                            </div>
                            <div class="max-w-[88%] space-y-3">
                                <div class="bg-white rounded-2xl rounded-tl-xs px-4 py-3 text-slate-800 shadow-xs border border-slate-200/80 text-sm">
                                    <div class="prose prose-sm max-w-none text-slate-800 leading-relaxed whitespace-pre-line">
                                        {{ $msg['text'] }}
                                    </div>
                                    <span class="text-[10px] text-slate-400 block mt-1.5">{{ $msg['time'] }}</span>
                                </div>

                                {{-- Name Prompt Input Field (if first time and prompt active) --}}
                                @if(!empty($msg['is_name_prompt']) && ! $hasIdentified)
                                    <div class="p-3 bg-white border border-blue-200 rounded-xl shadow-xs space-y-2">
                                        <div class="flex items-center gap-2">
                                            <input type="text"
                                                   wire:model="nameInput"
                                                   wire:keydown.enter="submitVisitorName"
                                                   placeholder="Your name (e.g. Panda)..."
                                                   class="flex-1 px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900 placeholder:text-slate-400 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-blue-500">
                                            <button type="button"
                                                    wire:click="submitVisitorName"
                                                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-xs transition shadow-xs">
                                                Continue
                                            </button>
                                            <button type="button"
                                                    wire:click="skipVisitorName"
                                                    class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-medium rounded-lg text-xs transition">
                                                Skip
                                            </button>
                                        </div>
                                    </div>
                                @endif

                                {{-- Recommended Course Cards --}}
                                @if(!empty($msg['course_cards']))
                                    <div class="space-y-2.5 pt-1">
                                        @foreach($msg['course_cards'] as $course)
                                            <div class="bg-white rounded-xl border border-slate-200 p-3 shadow-xs hover:border-blue-400 hover:shadow-md transition flex flex-col justify-between">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div>
                                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200">
                                                            {{ $course['difficulty'] }}
                                                        </span>
                                                        <h4 class="font-bold text-slate-900 text-xs sm:text-sm mt-1">
                                                            {{ $course['title'] }}
                                                        </h4>
                                                    </div>
                                                    <span class="font-extrabold text-blue-600 text-xs shrink-0 bg-blue-50/80 px-2 py-0.5 rounded-md">
                                                        {{ $course['price'] }}
                                                    </span>
                                                </div>

                                                <p class="text-xs text-slate-600 mt-1.5 line-clamp-2 leading-relaxed">
                                                    {{ $course['description'] }}
                                                </p>

                                                <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                                                    <span class="text-slate-400 text-[11px]">{{ $course['duration'] }}</span>
                                                    <a href="{{ $course['url'] }}"
                                                       class="font-semibold text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
                                                        <span>View Syllabus</span>
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                        </svg>
                                                    </a>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Links if present --}}
                                @if(!empty($msg['links']))
                                    <div class="flex flex-wrap gap-1.5 pt-1">
                                        @foreach($msg['links'] as $link)
                                            <a href="{{ $link['url'] }}"
                                               class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 hover:bg-blue-100 hover:text-blue-900 text-xs font-medium transition shadow-2xs">
                                                <span>{{ $link['label'] }}</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                                </svg>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Lead Capture Prompt Banner --}}
                                @if(!empty($msg['show_lead_capture']) && ($settings?->lead_capture_enabled ?? true))
                                    <div class="p-3 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl text-xs text-blue-900 flex items-center justify-between gap-3">
                                        <div>
                                            <span class="font-bold block text-blue-900">Are you interested in joining one of our courses?</span>
                                            <p class="text-blue-700 text-[11px] mt-0.5">Our admissions team can contact you with more details.</p>
                                        </div>
                                        <button type="button"
                                                wire:click="openLeadForm"
                                                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-xs shadow-xs shrink-0 transition">
                                            Request Callback
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach

                {{-- Error State & Retry --}}
                @if($errorMessage)
                    <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-800 flex items-center justify-between gap-2">
                        <span>{{ $errorMessage }}</span>
                        <button type="button" wire:click="retryLastMessage" class="px-2.5 py-1 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition">
                            Retry
                        </button>
                    </div>
                @endif

                {{-- Typing / Loading Indicator --}}
                <div wire:loading wire:target="sendMessage, selectSuggested, retryLastMessage, submitVisitorName" class="flex items-center space-x-2 text-slate-400 text-xs pl-10">
                    <div class="flex space-x-1">
                        <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0s;"></div>
                        <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0.15s;"></div>
                        <div class="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0.3s;"></div>
                    </div>
                    <span>Consulting academy admissions desk...</span>
                </div>

                {{-- Quick Actions / Suggested Questions --}}
                @if(!empty($suggestedQuestions))
                    <div class="pt-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2 px-1">Quick Options</p>
                        <div class="flex flex-col gap-1.5">
                            @foreach($suggestedQuestions as $suggestion)
                                <button type="button"
                                        wire:click="selectSuggested(@js($suggestion))"
                                        class="text-left px-3 py-2 rounded-xl bg-white border border-slate-200 hover:border-blue-400 hover:bg-blue-50/60 text-slate-700 hover:text-blue-700 text-xs font-medium transition shadow-2xs group flex items-center justify-between">
                                    <span class="line-clamp-1">{{ $suggestion }}</span>
                                    <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-blue-600 transition shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Inline Admissions Lead Capture Card (Drawer / Modal overlay) --}}
            @if($showLeadForm)
                <div class="absolute inset-x-0 bottom-0 top-16 bg-white z-20 p-5 flex flex-col justify-between overflow-y-auto animate-in slide-in-from-bottom duration-200 border-t border-slate-200 shadow-xl">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                            <div>
                                <h4 class="font-bold text-sm text-slate-900">Admissions & Course Guidance</h4>
                                <p class="text-xs text-slate-500">Our admissions team will contact you with syllabus, fees, and schedule details.</p>
                            </div>
                            <button type="button" wire:click="closeLeadForm" class="p-1 text-slate-400 hover:text-slate-600 font-bold text-lg leading-none">
                                &times;
                            </button>
                        </div>

                        <form wire:submit="submitLead" onsubmit="event.preventDefault();" class="space-y-3">
                            {{-- Visitor Name --}}
                            <div class="space-y-1">
                                <label class="block text-[11px] font-semibold text-slate-700">Full Name *</label>
                                <input type="text"
                                       wire:model="leadName"
                                       placeholder="Your full name"
                                       class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-blue-500">
                            </div>

                            {{-- Phone with Country Selector --}}
                            <div class="space-y-1">
                                <label class="block text-[11px] font-semibold text-slate-700">Phone Number *</label>
                                <div class="flex gap-1.5">
                                    <select wire:model="leadCountryCode"
                                            class="w-32 px-2 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-blue-500 shrink-0">
                                        @foreach($supportedCountries as $country)
                                            <option value="{{ $country['code'] }}">
                                                {{ $country['flag'] }} {{ $country['code'] }} ({{ $country['dial_code'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="tel"
                                           wire:model="leadPhone"
                                           placeholder="e.g. 9876543210"
                                           class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-blue-500">
                                </div>
                                @if($leadPhoneError)
                                    <span class="text-red-500 text-[10px] block">{{ $leadPhoneError }}</span>
                                @endif
                            </div>

                            {{-- Email Address (Optional if phone provided, validated if filled) --}}
                            <div class="space-y-1">
                                <label class="block text-[11px] font-semibold text-slate-700">
                                    Email Address <span class="text-slate-400 font-normal">(Optional)</span>
                                </label>
                                <input type="email"
                                       wire:model="leadEmail"
                                       placeholder="name@example.com"
                                       class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-blue-500">
                                @if($leadEmailError)
                                    <span class="text-red-500 text-[10px] block">{{ $leadEmailError }}</span>
                                @endif
                            </div>

                            {{-- Course of Interest --}}
                            <div class="space-y-1">
                                <label class="block text-[11px] font-semibold text-slate-700">Course of Interest</label>
                                @if(!empty($availableCourses))
                                    <select wire:model="leadCourseInterest"
                                            class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-blue-500">
                                        <option value="General Admissions">General Admissions</option>
                                        @foreach($availableCourses as $courseTitle)
                                            <option value="{{ $courseTitle }}">{{ $courseTitle }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text"
                                           wire:model="leadCourseInterest"
                                           placeholder="e.g. Technical SEO Certification"
                                           class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-blue-500">
                                @endif
                            </div>

                            {{-- Explicit Admissions Consent Checkbox (NOT pre-selected) --}}
                            <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <label class="flex items-start space-x-2 cursor-pointer">
                                    <input type="checkbox"
                                           wire:model="leadConsent"
                                           class="mt-0.5 rounded text-blue-600 focus:ring-blue-500 w-3.5 h-3.5 border-slate-300">
                                    <span class="text-[11px] text-slate-700 leading-snug">
                                        I agree that SEO Academy may contact me about courses and admissions using the contact details I have provided. <span class="text-red-500">*</span>
                                    </span>
                                </label>
                                @if($leadConsentError)
                                    <span class="text-red-500 text-[10px] block mt-1.5">{{ $leadConsentError }}</span>
                                @endif
                            </div>

                            {{-- Buttons --}}
                            <div class="pt-2 flex items-center justify-end gap-2">
                                <button type="button"
                                        wire:click="closeLeadForm"
                                        class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 text-xs font-semibold transition">
                                    Cancel
                                </button>
                                <button type="submit"
                                        class="px-4 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs transition">
                                    Request Callback
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if($isWaitingForSupport)
                <div class="px-4 py-2 bg-emerald-50 border-t border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
                    <span class="flex items-center gap-1.5 font-medium">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Connected with Human Support. Live advisor is reviewing this thread.
                    </span>
                </div>
            @elseif($settings?->human_support_enabled)
                <div class="px-4 py-1.5 bg-slate-50 border-t border-slate-200 text-slate-500 text-[11px] flex items-center justify-between">
                    <span>Have specific questions?</span>
                    <button type="button" wire:click="requestHumanSupport" class="text-blue-600 hover:text-blue-800 font-semibold inline-flex items-center gap-1">
                        <span>Talk to Support</span> &rarr;
                    </button>
                </div>
            @endif

            {{-- Input Footer Form --}}
            <form wire:submit="sendMessage" onsubmit="event.preventDefault();" class="p-3 bg-white border-t border-slate-200 flex items-center space-x-2">
                <input type="text"
                       wire:model="message"
                       placeholder="Ask about courses, fees, admissions, duration..."
                       maxlength="500"
                       class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
                       autofocus>
                
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="sendMessage, selectSuggested, retryLastMessage, submitVisitorName"
                        class="p-2.5 text-white rounded-xl shadow-xs transition hover:brightness-110 active:scale-95 disabled:opacity-50 flex items-center justify-center"
                        style="background-color: {{ $settings?->brand_color ?: '#2563eb' }};"
                        aria-label="Send Message">
                    <svg class="w-5 h-5 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </form>
        </div>
    @endif

    {{-- Floating Launcher Button --}}
    <button wire:click="toggleChat"
            type="button"
            id="seo-chatbot-launcher"
            aria-label="Open Admissions Assistant"
            class="relative flex items-center justify-center w-14 h-14 rounded-full text-white shadow-xl hover:scale-105 active:scale-95 transition-all duration-200 focus:outline-hidden focus:ring-4 focus:ring-blue-300"
            style="background-color: {{ $settings?->brand_color ?: '#2563eb' }}; box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.4);">
        
        @if($isOpen)
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        @else
            @php
                $icon = $settings?->launcher_icon ?: 'message-circle';
            @endphp

            @if($icon === 'bot')
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            @elseif($icon === 'sparkle')
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
            @elseif($icon === 'help-circle')
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            @else
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
            @endif

            {{-- Online Ping Indicator --}}
            <span class="absolute top-0 right-0 flex h-3.5 w-3.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500 border-2 border-white"></span>
            </span>
        @endif
    </button>
</div>
