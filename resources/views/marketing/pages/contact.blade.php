<x-layouts.marketing :title="$page?->seo_title ?: 'Contact Us'" :description="$page?->seo_description" :seoData="$seoData" :jsonLd="$jsonLd">
    <div class="bg-gradient-to-b from-slate-100 to-white py-16 border-b border-slate-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <span class="text-xs font-bold text-blue-600 tracking-wider uppercase">Direct Inquiries</span>
            <h1 class="text-3xl sm:text-5xl font-extrabold text-slate-900 tracking-tight mt-3">
                {{ $page?->title ?: 'Get in Touch with Our Faculty' }}
            </h1>
            <p class="text-lg text-slate-600 mt-4 max-w-2xl mx-auto leading-relaxed">
                Have questions about our training tracks, corporate workshop arrangements, or enrollment requirements? Send us a message and our academic team will reply promptly.
            </p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
            
            {{-- Contact Information Column --}}
            <div class="lg:col-span-5 space-y-8">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 mb-3">Academic Admissions & Consulting</h2>
                    <p class="text-slate-600 text-sm leading-relaxed">
                        Whether you are seeking enrollment advice, verification assistance, or an enterprise team consultation, our team is here to assist.
                    </p>
                </div>

                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs uppercase font-bold text-slate-400">Direct Email</div>
                            <div class="text-sm font-semibold text-slate-900 mt-0.5">admissions@seoacademy.internal</div>
                            <div class="text-xs text-slate-500">Inquiries answered within 24 business hours.</div>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs uppercase font-bold text-slate-400">Campus & Verification Desk</div>
                            <div class="text-sm font-semibold text-slate-900 mt-0.5">SEO Academy Digital Campus</div>
                            <div class="text-xs text-slate-500">Centralized single-academy administrative headquarters.</div>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs uppercase font-bold text-slate-400">Advising Hours</div>
                            <div class="text-sm font-semibold text-slate-900 mt-0.5">Monday – Friday: 9:00 AM – 6:00 PM</div>
                            <div class="text-xs text-slate-500">Online classroom accessible 24/7.</div>
                        </div>
                    </div>
                </div>

                {{-- Trust Card --}}
                <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200">
                    <h3 class="text-sm font-bold text-slate-900">Spam-Protected & Secure</h3>
                    <p class="text-xs text-slate-600 mt-1">
                        All submissions are routed directly into our administrator review desk with rate limiting and automated honeypot spam filtering.
                    </p>
                </div>
            </div>

            {{-- Form Column --}}
            <div class="lg:col-span-7">
                <div class="bg-white p-8 sm:p-10 rounded-2xl border border-slate-200 shadow-sm">
                    
                    {{-- Success Message --}}
                    @if(session('success'))
                        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-start gap-3">
                            <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div>
                                <span class="font-bold">Submission Confirmed:</span> {{ session('success') }}
                            </div>
                        </div>
                    @endif

                    {{-- Validation Errors --}}
                    @if($errors->any())
                        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
                            <p class="font-bold mb-1">Please correct the following errors:</p>
                            <ul class="list-disc pl-5 space-y-1 text-xs">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('contact.submit') }}" class="space-y-6">
                        @csrf
                        
                        {{-- Honeypot Spam Prevention (must remain empty) --}}
                        <input type="text" name="website_hp" style="display:none !important;" tabindex="-1" autocomplete="off">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                       class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition">
                            </div>

                            <div>
                                <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                       class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label for="phone" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                    Phone Number (Optional)
                                </label>
                                <div class="flex gap-2">
                                    <select name="phone_country_code" id="phone_country_code"
                                            class="w-36 px-2.5 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs transition bg-white">
                                        @foreach($countries ?? [] as $country)
                                            <option value="{{ $country['code'] }}" {{ (old('phone_country_code', 'IN') === $country['code']) ? 'selected' : '' }}>
                                                {{ $country['flag'] }} {{ $country['code'] }} ({{ $country['dial_code'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="tel" name="phone" id="phone" value="{{ old('phone') }}"
                                           placeholder="e.g. 9876543210"
                                           class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition">
                                </div>
                            </div>

                            <div>
                                <label for="interested_service" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                    Area of Interest
                                </label>
                                <select name="interested_service" id="interested_service"
                                        class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition bg-white">
                                    <option value="">General Academic Inquiry</option>
                                    @foreach($services as $svc)
                                        <option value="{{ $svc->title }}" {{ request('service') === $svc->title || old('interested_service') === $svc->title ? 'selected' : '' }}>
                                            {{ $svc->title }}
                                        </option>
                                    @endforeach
                                    <option value="Course Enrollment Assistance">Course Enrollment Assistance</option>
                                    <option value="Enterprise Team Training">Enterprise Team Training</option>
                                    <option value="Certificate Verification Question">Certificate Verification Question</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="subject" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                Subject <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required
                                   class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition"
                                   placeholder="e.g. Inquiring about Technical SEO Track enrollment">
                        </div>

                        <div>
                            <label for="message" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                Message <span class="text-red-500">*</span>
                            </label>
                            <textarea name="message" id="message" rows="5" required
                                      class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition"
                                      placeholder="Please describe how our academy can assist you...">{{ old('message') }}</textarea>
                        </div>

                        {{-- Explicit Admissions Contact Consent Checkbox (not pre-selected) --}}
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" name="consent" id="consent" value="1" required {{ old('consent') ? 'checked' : '' }}
                                       class="mt-1 w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-xs text-slate-700 leading-relaxed font-medium">
                                    I agree that SEO Academy may contact me about courses and admissions using the contact details I have provided. <span class="text-red-500">*</span>
                                </span>
                            </label>
                        </div>

                        <div>
                            <button type="submit" 
                                    class="w-full sm:w-auto px-8 py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md transition duration-150 flex items-center justify-center gap-2">
                                <span>Send Message</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-layouts.marketing>
