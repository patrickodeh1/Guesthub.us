<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Application Settings
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Customize your application logo, site name, and theme color.
                </p>
            </div>
        </div>
    </x-slot>

    @php
        $logoPath = $settings['application_logo_path'] ?? null;
        $iconPath = $settings['application_icon_path'] ?? null;
        $customFaviconPath = $settings['favicon_path'] ?? null;

        $hasLogo = $logoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath);
        $hasIcon = $iconPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($iconPath);
        
        $activeFaviconPath = null;
        $hasFavicon = false;
        $isUsingFallbackFavicon = false;

        if ($customFaviconPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($customFaviconPath)) {
            $hasFavicon = true;
            $activeFaviconPath = $customFaviconPath;
        } elseif ($hasIcon) {
            $hasFavicon = true;
            $activeFaviconPath = $iconPath;
            $isUsingFallbackFavicon = true;
        }
    @endphp

    {{-- Success Message --}}
    <x-flash.ok :message="session('success')" />

    {{-- Floating Save Status Indicator --}}
    <div x-data="{ show: false, status: 'saving' }"
         x-show="show"
         x-cloak
         x-on:settings-saving.window="show = true; status = 'saving'"
         x-on:settings-saved.window="show = true; status = 'saved'; setTimeout(() => show = false, 2000)"
         x-on:settings-error.window="show = true; status = 'error'; setTimeout(() => show = false, 3000)"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-6 right-6 z-50">
        <div class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <template x-if="status === 'saving'">
                <div class="flex items-center gap-2">
                    <svg class="animate-spin h-5 w-5 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Saving...</span>
                </div>
            </template>
            <template x-if="status === 'saved'">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Saved</span>
                </div>
            </template>
            <template x-if="status === 'error'">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Error saving</span>
                </div>
            </template>
        </div>
    </div>

    <form x-data="settingsForm()"
          @submit.prevent
          enctype="multipart/form-data"
          class="space-y-6">
        @csrf
       

        {{-- Logo Upload Section --}}
        <x-card>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    Application Logo
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Upload a custom logo for your application. Recommended size: 200x200px. Max file size: 5MB.
                </p>
            </div>

            <div class="flex flex-col lg:flex-row gap-6">
                {{-- Logo Preview/Upload Area --}}
                <div class="lg:w-1/2">
                    <div
                        class="border-2 border-dashed rounded-2xl p-8 flex flex-col items-center justify-center text-center cursor-pointer transition-all duration-200 hover:border-opacity-70"
                        :class="dragOver ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-900/20' :
                            'border-gray-300 dark:border-gray-600 bg-gray-50/40 dark:bg-gray-800/40'"
                        @click="$refs.logoFile.click()" @dragover.prevent="dragOver = true"
                        @dragleave.prevent="dragOver = false" @drop.prevent="handleDrop($event)">
                        <template x-if="!previewUrl && !currentLogo">
                            <div class="text-gray-500 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-20 w-20 mb-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <p class="mt-3 text-sm font-medium">Drag &amp; drop or click to upload</p>
                                <p class="mt-1 text-xs text-gray-400">
                                    PNG, JPG, WebP, SVG — up to 5MB
                                </p>
                            </div>
                        </template>

                        <template x-if="previewUrl || currentLogo">
                            <div class="w-full">
                                <img :src="previewUrl || currentLogo" alt="Logo Preview"
                                    class="rounded-lg object-contain h-48 w-48 mx-auto shadow-lg border-4 border-white dark:border-gray-700" />
                                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400" x-show="!previewUrl">
                                    Click or drop a new file to replace the logo.
                                </p>
                                <template x-if="previewUrl">
                                    <div class="mt-4 flex items-center justify-center gap-3" @click.stop>
                                        <x-button type="button" variant="primary" size="sm" @click.stop="uploadLogo()">
                                            Upload
                                        </x-button>
                                        <x-button type="button" variant="secondary" size="sm" @click.stop="cancelLogoUpload()">
                                            Cancel
                                        </x-button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <input type="file" name="application_logo" x-ref="logoFile" class="hidden"
                            @change="preview($event)" accept="image/*" />
                    </div>

                    @error('application_logo')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Current Logo Info --}}
                <div class="lg:w-1/2 flex flex-col justify-center">
                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">
                            Current Logo
                        </h4>
                        @if ($hasLogo)
                            <div class="mb-4">
                                <img src="{{ url('file/' . ltrim($logoPath, '/')) }}"
                                    alt="Current Logo" class="h-24 w-24 object-contain rounded-lg shadow-md" />
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Your logo is currently being used throughout the application.
                            </p>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                No custom logo uploaded. The default logo is being used.
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Logo Alignment Option --}}
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <x-form.label value="Logo Alignment" />
                <p class="mt-1 mb-3 text-sm text-gray-500 dark:text-gray-400">
                    Choose how the logo should be aligned in the sidebar header.
                </p>
                <div class="flex gap-4">
                    <label class="flex items-center">
                        <input type="radio" name="logo_alignment" value="left" x-model="logoAlignment"
                            {{ old('logo_alignment', $settings['logo_alignment'] ?? 'center') === 'left' ? 'checked' : '' }}
                            @change="saveSettings()"
                            class="mr-2 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Left</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="logo_alignment" value="center" x-model="logoAlignment"
                            {{ old('logo_alignment', $settings['logo_alignment'] ?? 'center') === 'center' ? 'checked' : '' }}
                            @change="saveSettings()"
                            class="mr-2 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Center</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="logo_alignment" value="right" x-model="logoAlignment"
                            {{ old('logo_alignment', $settings['logo_alignment'] ?? 'center') === 'right' ? 'checked' : '' }}
                            @change="saveSettings()"
                            class="mr-2 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Right</span>
                    </label>
                </div>
            </div>
        </x-card>

        {{-- Icon Upload Section --}}
        <x-card>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    Application Icon
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Upload a square icon (e.g., 512x512) for when the sidebar is minimized or on mobile. Max file size: 2MB.
                </p>
            </div>

            <div class="flex flex-col lg:flex-row gap-6">
                {{-- Icon Preview/Upload Area --}}
                <div class="lg:w-1/2">
                    <div
                        class="border-2 border-dashed rounded-2xl p-8 flex flex-col items-center justify-center text-center cursor-pointer transition-all duration-200 hover:border-opacity-70"
                        :class="iconDragOver ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-900/20' :
                            'border-gray-300 dark:border-gray-600 bg-gray-50/40 dark:bg-gray-800/40'"
                        @click="$refs.iconFile.click()" @dragover.prevent="iconDragOver = true"
                        @dragleave.prevent="iconDragOver = false" @drop.prevent="handleIconDrop($event)">
                        <template x-if="!iconPreviewUrl && !currentIcon">
                            <div class="text-gray-500 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-16 w-16 mb-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <p class="mt-3 text-sm font-medium">Drag &amp; drop or click to upload</p>
                                <p class="mt-1 text-xs text-gray-400">
                                    PNG, JPG, SVG — up to 2MB
                                </p>
                            </div>
                        </template>

                        <template x-if="iconPreviewUrl || currentIcon">
                            <div class="w-full">
                                <img :src="iconPreviewUrl || currentIcon" alt="Icon Preview"
                                    class="rounded-lg object-contain h-24 w-24 mx-auto shadow-lg border-4 border-white dark:border-gray-700" />
                                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400" x-show="!iconPreviewUrl">
                                    Click or drop a new file to replace the icon.
                                </p>
                                <template x-if="iconPreviewUrl">
                                    <div class="mt-4 flex items-center justify-center gap-3" @click.stop>
                                        <x-button type="button" variant="primary" size="sm" @click.stop="uploadIcon()">
                                            Upload
                                        </x-button>
                                        <x-button type="button" variant="secondary" size="sm" @click.stop="cancelIconUpload()">
                                            Cancel
                                        </x-button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <input type="file" name="application_icon" x-ref="iconFile" class="hidden"
                            @change="previewIcon($event)" accept=".png,.svg,.jpg,.jpeg,image/png,image/svg+xml,image/jpeg" />
                    </div>

                    @error('application_icon')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Current Icon Info --}}
                <div class="lg:w-1/2 flex flex-col justify-center">
                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">
                            Current Icon
                        </h4>
                        @if ($hasIcon)
                            <div class="mb-4 flex items-center gap-4">
                                <img src="{{ url('file/' . ltrim($iconPath, '/')) }}"
                                    alt="Current Icon" class="h-20 w-20 p-2 object-contain bg-white dark:bg-gray-900 rounded-lg shadow-md" />
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Displays when sidebar collapses.
                            </p>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                No custom icon uploaded. The default layout is being used.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Favicon Upload Section --}}
        <x-card>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    Favicon
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Upload a custom favicon for your application. Recommended size: 32x32px or 16x16px. Max file size: 1MB. Supported formats: ICO, PNG, SVG, JPG.
                </p>
            </div>

            <div class="flex flex-col lg:flex-row gap-6">
                {{-- Favicon Preview/Upload Area --}}
                <div class="lg:w-1/2">
                    <div
                        class="border-2 border-dashed rounded-2xl p-8 flex flex-col items-center justify-center text-center cursor-pointer transition-all duration-200 hover:border-opacity-70"
                        :class="faviconDragOver ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-900/20' :
                            'border-gray-300 dark:border-gray-600 bg-gray-50/40 dark:bg-gray-800/40'"
                        @click="$refs.faviconFile.click()" @dragover.prevent="faviconDragOver = true"
                        @dragleave.prevent="faviconDragOver = false" @drop.prevent="handleFaviconDrop($event)">
                        <template x-if="!faviconPreviewUrl && !currentFavicon">
                            <div class="text-gray-500 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-16 w-16 mb-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <p class="mt-3 text-sm font-medium">Drag &amp; drop or click to upload</p>
                                <p class="mt-1 text-xs text-gray-400">
                                    ICO, PNG, SVG, JPG — up to 1MB
                                </p>
                            </div>
                        </template>

                        <template x-if="faviconPreviewUrl || currentFavicon">
                            <div class="w-full">
                                <img :src="faviconPreviewUrl || currentFavicon" alt="Favicon Preview"
                                    class="rounded-lg object-contain h-16 w-16 mx-auto shadow-lg border-4 border-white dark:border-gray-700" />
                                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400" x-show="!faviconPreviewUrl">
                                    Click or drop a new file to replace the favicon.
                                </p>
                                <template x-if="faviconPreviewUrl">
                                    <div class="mt-4 flex items-center justify-center gap-3" @click.stop>
                                        <x-button type="button" variant="primary" size="sm" @click.stop="uploadFavicon()">
                                            Upload
                                        </x-button>
                                        <x-button type="button" variant="secondary" size="sm" @click.stop="cancelFaviconUpload()">
                                            Cancel
                                        </x-button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <input type="file" name="favicon" x-ref="faviconFile" class="hidden"
                            @change="previewFavicon($event)" accept=".ico,.png,.svg,.jpg,.jpeg,image/x-icon,image/png,image/svg+xml,image/jpeg" />
                    </div>

                    @error('favicon')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Current Favicon Info --}}
                <div class="lg:w-1/2 flex flex-col justify-center">
                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">
                            Current Favicon
                        </h4>
                        @if ($hasFavicon)
                            <div class="mb-4 flex items-center gap-4">
                                <img src="{{ url('file/' . ltrim($activeFaviconPath, '/')) }}"
                                    alt="Current Favicon" class="h-16 w-16 object-contain rounded-lg shadow-md" />
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Your favicon is currently being used in browser tabs.
                                    </p>
                                    @if ($isUsingFallbackFavicon)
                                        <p class="text-xs text-indigo-500 mt-1 font-medium">
                                            (Using Application Icon as fallback)
                                        </p>
                                    @endif
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                        Format: {{ pathinfo($activeFaviconPath, PATHINFO_EXTENSION) }}
                                    </p>
                                </div>
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                No custom favicon uploaded. The default favicon is being used.
                            </p>
                        @endif

                        {{-- Browser Preview --}}
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-xs font-medium text-gray-900 dark:text-gray-100 mb-2">Browser Preview</p>
                            <div class="flex items-center gap-2 p-2 bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700">
                                @if ($hasFavicon)
                                    <img src="{{ url('file/' . ltrim($activeFaviconPath, '/')) }}"
                                        alt="Favicon" class="h-4 w-4" />
                                @else
                                    <div class="h-4 w-4 bg-gray-300 dark:bg-gray-600 rounded"></div>
                                @endif
                                <span class="text-xs text-gray-600 dark:text-gray-400">{{ $siteName ?? config('app.name', 'HK Checklist') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Site Name Section --}}
        <x-card>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    Site Name
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Change the name of your application as it appears throughout the system.
                </p>
            </div>

            <div class="max-w-md">
                <x-form.label value="Application Name" />
                <x-form.input name="site_name" type="text" class="w-full" required
                    :value="old('site_name', $settings['site_name'])" placeholder="Enter site name"
                    x-model="siteName"
                    @input.debounce.400ms="saveSettings()"
                    autofocus />
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    This name will appear in page titles, headers, and other UI elements.
                </p>
                @error('site_name')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </x-card>

        {{-- Theme Color Section --}}
        <x-card>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    Theme Color
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Choose a primary color theme for your application. This color will be used for buttons, links, and
                    other interactive elements.
                </p>
            </div>

            <div class="space-y-6">
                {{-- Color Presets --}}
                <div>
                    <x-form.label value="Choose a Color" />
                    <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-3 mt-3">
                        @php
                            $presetColors = [
                                '#842eb8' => 'Purple',
                                '#6366f1' => 'Indigo',
                                '#3b82f6' => 'Blue',
                                '#10b981' => 'Green',
                                '#f59e0b' => 'Amber',
                                '#ef4444' => 'Red',
                                '#ec4899' => 'Pink',
                                '#8b5cf6' => 'Violet',
                                '#06b6d4' => 'Cyan',
                                '#14b8a6' => 'Teal',
                            ];
                        @endphp
                        @foreach ($presetColors as $color => $name)
                            <label
                                class="relative cursor-pointer group flex flex-col items-center justify-center p-3 rounded-lg border-2 transition-all hover:scale-105 hover:shadow-md"
                                :class="selectedColor === '{{ $color }}' || (!selectedColor && '{{ $settings['theme_color'] }}' === '{{ $color }}')
                                    ? 'border-gray-900 dark:border-gray-100 shadow-lg'
                                    : 'border-gray-200 dark:border-gray-700'">
                                <input type="radio" name="theme_color" value="{{ $color }}" x-model="selectedColor"
                                    class="sr-only"
                                    @change="updateCustomColor('{{ $color }}'); saveSettings()"
                                    @checked($settings['theme_color'] === $color) />
                                <div class="w-10 h-10 rounded-full mb-2 shadow-sm"
                                    style="background-color: {{ $color }};"></div>
                                <span class="text-xs text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-gray-100"
                                    x-show="selectedColor === '{{ $color }}' || (!selectedColor && '{{ $settings['theme_color'] }}' === '{{ $color }}')">
                                    ✓
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Custom Color Picker --}}
                <div>
                    <x-form.label value="Or Choose a Custom Color" />
                    <div class="flex items-center gap-4 mt-3">
                        <div class="flex-1">
                            <input type="color" x-model="customColor" @input="updateCustomColor(customColor); saveSettings()"
                                class="w-full h-12 rounded-lg cursor-pointer border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800"
                                value="{{ $settings['theme_color'] }}" />
                        </div>
                        <div class="flex-1">
                            <x-form.input type="text" x-model="customColor" @input.debounce.400ms="updateCustomColor(customColor); saveSettings()"
                                pattern="^#[0-9A-Fa-f]{6}$" placeholder="#842eb8"
                                class="w-full font-mono" />
                        </div>
                        <input type="hidden" name="theme_color" x-model="selectedColor" />
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Enter a hex color code (e.g., #842eb8) or use the color picker above.
                    </p>
                    @error('theme_color')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Color Preview --}}
                <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Preview
                    </h4>
                    <div class="flex flex-wrap gap-3">
                        <button type="button"
                            class="px-4 py-2 rounded-lg text-white font-medium transition-colors"
                            :style="`background-color: ${selectedColor || '{{ $settings['theme_color'] }}'}`">
                            Primary Button
                        </button>
                        <a href="#" type="button"
                            class="px-4 py-2 rounded-lg font-medium transition-colors border-2"
                            :style="`color: ${selectedColor || '{{ $settings['theme_color'] }}'}; border-color: ${selectedColor || '{{ $settings['theme_color'] }}'}`">
                            Secondary Button
                        </a>
                        <div class="px-4 py-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                            <span class="font-medium"
                                :style="`color: ${selectedColor || '{{ $settings['theme_color'] }}'}`">
                                Colored Text
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Button Variants Section --}}
        <x-card>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    Button Variant Colors
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Customize the colors for different button variants used throughout the application.
                </p>
            </div>

            <div class="space-y-6">
                @php
                    $buttonVariants = [
                        'primary' => ['label' => 'Primary', 'default' => '#842eb8', 'description' => 'Main action buttons'],
                        'success' => ['label' => 'Success', 'default' => '#10b981', 'description' => 'Success/confirmation actions'],
                        'danger' => ['label' => 'Danger', 'default' => '#ef4444', 'description' => 'Delete/destructive actions'],
                        'warning' => ['label' => 'Warning', 'default' => '#f59e0b', 'description' => 'Warning/caution actions'],
                        'info' => ['label' => 'Info', 'default' => '#06b6d4', 'description' => 'Informational actions'],
                    ];
                @endphp

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach ($buttonVariants as $key => $variant)
                        @php
                            $settingKey = 'button_' . $key . '_color';
                            $inputId = $settingKey . '_input';
                            $currentValue = $settings[$settingKey] ?? $variant['default'];
                            $oldValue = old($settingKey, $currentValue);
                        @endphp
                        <div class="space-y-3">
                            <div>
                                <x-form.label :value="$variant['label'] . ' Button'" />
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $variant['description'] }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex-1">
                                    <input type="color"
                                        name="{{ $settingKey }}"
                                        value="{{ $oldValue }}"
                                        class="w-full h-10 rounded-lg cursor-pointer border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800"
                                        data-input-id="{{ $inputId }}"
                                        x-on:input="document.getElementById($el.dataset.inputId).value = $el.value; window.settingsFormInstance?.saveSettings()" />
                                </div>
                                <div class="flex-1">
                                    <x-form.input
                                        type="text"
                                        id="{{ $inputId }}"
                                        name="{{ $settingKey }}"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        :value="$oldValue"
                                        placeholder="{{ $variant['default'] }}"
                                        class="w-full font-mono"
                                        data-color-input="{{ $settingKey }}"
                                        x-on:input.debounce.400ms="document.querySelector('input[type=color][name=' + $el.dataset.colorInput + ']').value = $el.value; window.settingsFormInstance?.saveSettings()" />
                                </div>
                            </div>
                            {{-- Preview --}}
                            <div class="mt-2">
                                <button type="button"
                                    class="px-4 py-2 rounded-md text-white font-medium text-sm transition-colors"
                                    style="background-color: {{ $oldValue }};"
                                    onmouseover="this.style.opacity='0.9'"
                                    onmouseout="this.style.opacity='1'">
                                    {{ $variant['label'] }} Button
                                </button>
                            </div>
                            @error($settingKey)
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>

                {{-- All Variants Preview --}}
                <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6 mt-6">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        All Button Variants Preview
                    </h4>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($buttonVariants as $key => $variant)
                            @php
                                $previewKey = 'button_' . $key . '_color';
                                $previewValue = old($previewKey, $settings[$previewKey] ?? $variant['default']);
                            @endphp
                            <button type="button"
                                class="px-4 py-2 rounded-md text-white font-medium transition-colors"
                                style="background-color: {{ $previewValue }};"
                                onmouseover="this.style.opacity='0.9'"
                                onmouseout="this.style.opacity='1'">
                                {{ $variant['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Global Notification Settings --}}
        <x-card>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    Global Notification Settings
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Master toggles for progress notifications. When disabled here, notifications of that type will be suppressed for <strong>all</strong> properties regardless of per-property settings.
                </p>
            </div>
            <div class="space-y-4">
                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer">
                    <input type="checkbox" name="notify_cleaning_started_global" value="1"
                        {{ old('notify_cleaning_started_global', $settings['notify_cleaning_started_global'] ?? true) ? 'checked' : '' }}
                        @change="saveSettings(true)"
                        class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Cleaning Started Notifications</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Notify recipients when a cleaning session begins</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer">
                    <input type="checkbox" name="notify_cleaning_finished_global" value="1"
                        {{ old('notify_cleaning_finished_global', $settings['notify_cleaning_finished_global'] ?? true) ? 'checked' : '' }}
                        @change="saveSettings(true)"
                        class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Cleaning Finished Notifications</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Notify recipients when a cleaning session is completed</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer">
                    <input type="checkbox" name="notify_photo_started_global" value="1"
                        {{ old('notify_photo_started_global', $settings['notify_photo_started_global'] ?? true) ? 'checked' : '' }}
                        @change="saveSettings(true)"
                        class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Photo Started Notifications</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Notify recipients when property photos begin being captured</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer">
                    <input type="checkbox" name="notify_task_notes_global" value="1"
                        {{ old('notify_task_notes_global', $settings['notify_task_notes_global'] ?? true) ? 'checked' : '' }}
                        @change="saveSettings(true)"
                        class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Task Note Notifications</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Notify recipients when a cleaner adds a note to a task</p>
                    </div>
                </label>
            </div>
        </x-card>

        {{-- Training & Enforcement Settings --}}
        <x-card>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    Training & Enforcement
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Control whether cleaners must view task instructions before completing tasks. This prevents cleaners from blindly checking off items without reading or watching training content.
                </p>
            </div>
            <div class="space-y-5">
                <label class="flex items-start gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer">
                    <input type="checkbox" name="mandatory_instruction_viewing" value="1"
                        {{ old('mandatory_instruction_viewing', $settings['mandatory_instruction_viewing'] ?? false) ? 'checked' : '' }}
                        @change="saveSettings(true)"
                        class="w-5 h-5 mt-0.5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    <div>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Require cleaners to view instructions before completing tasks</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">When enabled, cleaners must view task instructions/videos the required number of times before they can check off a task. This applies to all tasks that have instructions attached.</p>
                    </div>
                </label>

                <div class="ml-11 max-w-xs">
                    <x-form.label value="Default Required Instruction Views" />
                    <x-form.input type="number" name="global_required_instruction_views" min="1" max="100" step="1"
                        :value="old('global_required_instruction_views', $settings['global_required_instruction_views'] ?? 3)"
                        class="w-full mt-1"
                        x-on:input.debounce.600ms="saveSettings()" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Number of times a cleaner must view instructions before they can complete a task without viewing. Default is 3. Can be overridden per-user in User Settings → Training & Instructions.
                    </p>
                </div>

                <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-lg p-4 ml-11">
                    <div class="flex gap-2">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="text-xs text-amber-800 dark:text-amber-200">
                            <strong>Per-User Override:</strong> You can set a different required views count for individual cleaners who keep forgetting. Go to <strong>Users → Edit User → Training & Instructions</strong> to override the global default for a specific cleaner.
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

    </form>

    {{-- Alpine.js helpers --}}
    <script>
        function settingsForm() {
            const instance = {
                previewUrl: null,
                dragOver: false,
                faviconPreviewUrl: null,
                faviconDragOver: false,
                iconPreviewUrl: null,
                iconDragOver: false,
                selectedColor: '{{ $settings['theme_color'] }}',
                customColor: '{{ $settings['theme_color'] }}',
                siteName: '{{ $settings['site_name'] }}',
                logoAlignment: '{{ $settings['logo_alignment'] ?? 'center' }}',
                currentLogo: @json($hasLogo ? url('file/' . ltrim($logoPath, '/')) : null),
                currentFavicon: @json($hasFavicon ? url('file/' . ltrim($activeFaviconPath, '/')) : null),
                currentIcon: @json($hasIcon ? url('file/' . ltrim($iconPath, '/')) : null),
                saveTimeout: null,

                init() {
                    // Make instance available globally for inline handlers
                    window.settingsFormInstance = this;
                },

                showStatus(status) {
                    window.dispatchEvent(new CustomEvent(`settings-${status}`));
                },

                async saveSettings(immediate = false, uploadLogo = false, uploadFavicon = false, uploadIcon = false) {
                    // Clear existing timeout
                    if (this.saveTimeout) {
                        clearTimeout(this.saveTimeout);
                    }

                    const save = async () => {
                        this.showStatus('saving');

                        const formData = new FormData();
                        formData.append('_token', document.querySelector('input[name="_token"]').value);
         
                        formData.append('site_name', this.siteName || document.querySelector('input[name="site_name"]')?.value || '{{ $settings['site_name'] }}');
                        formData.append('theme_color', this.selectedColor || this.customColor);
                        formData.append('logo_alignment', this.logoAlignment || document.querySelector('input[name="logo_alignment"]:checked')?.value || 'center');

                        // Add button colors
                        document.querySelectorAll('input[name^="button_"][type="text"]').forEach(input => {
                            if (input.value) {
                                formData.append(input.name, input.value);
                            }
                        });

                        // Add color picker values
                        document.querySelectorAll('input[type="color"][name^="button_"]').forEach(input => {
                            if (input.value) {
                                formData.append(input.name, input.value);
                            }
                        });

                        // Add global notification checkboxes
                        ['notify_cleaning_started_global', 'notify_cleaning_finished_global', 'notify_photo_started_global', 'notify_task_notes_global', 'mandatory_instruction_viewing'].forEach(name => {
                            const cb = document.querySelector(`input[name="${name}"]`);
                            if (cb) {
                                formData.append(name, cb.checked ? '1' : '0');
                            }
                        });

                        // Add training enforcement number input
                        const reqViews = document.querySelector('input[name="global_required_instruction_views"]');
                        if (reqViews && reqViews.value) {
                            formData.append('global_required_instruction_views', reqViews.value);
                        }

                        // Add file uploads only if explicitly requested or if they exist and we're doing a general save
                        if (uploadLogo && this.$refs.logoFile?.files?.[0]) {
                            formData.append('application_logo', this.$refs.logoFile.files[0]);
                        }
                        if (uploadIcon && this.$refs.iconFile.files[0]) {
                            formData.append('application_icon', this.$refs.iconFile.files[0]);
                        }

                        if (uploadFavicon && this.$refs.faviconFile.files[0]) {
                            formData.append('favicon', this.$refs.faviconFile.files[0]);
                        }

                        try {
                            const response = await fetch('{{ route('settings.update') }}', {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (response.ok) {
                                this.showStatus('saved');
                                // Update current logo/favicon if files were uploaded
                                if (uploadLogo && this.$refs.logoFile?.files?.[0]) {
                                    this.currentLogo = this.previewUrl;
                                    // Clear the file input after successful upload
                                    this.$refs.logoFile.value = '';
                                    this.previewUrl = null;
                                }
                                if (uploadFavicon && this.$refs.faviconFile?.files?.[0]) {
                                    this.currentFavicon = this.faviconPreviewUrl;
                                    // Clear the file input after successful upload
                                    this.$refs.faviconFile.value = '';
                                    this.faviconPreviewUrl = null;
                                }
                                if (uploadIcon && this.$refs.iconFile?.files?.[0]) {
                                    this.currentIcon = this.iconPreviewUrl;
                                    this.$refs.iconFile.value = '';
                                    this.iconPreviewUrl = null;
                                }
                            } else {
                                this.showStatus('error');
                            }
                        } catch (error) {
                            console.error('Error saving settings:', error);
                            this.showStatus('error');
                        }
                    };

                    if (immediate) {
                        await save();
                    } else {
                        this.saveTimeout = setTimeout(save, 400);
                    }
                },

                preview(event) {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    this.previewUrl = URL.createObjectURL(file);
                },

                handleDrop(evt) {
                    this.dragOver = false;
                    const file = evt.dataTransfer.files?.[0];
                    if (!file) return;

                    this.$refs.logoFile.files = evt.dataTransfer.files;
                    this.preview({
                        target: {
                            files: this.$refs.logoFile.files
                        }
                    });
                },

                async uploadLogo() {
                    if (!this.$refs.logoFile?.files?.[0]) return;
                    await this.saveSettings(true, true, false, false);
                },

                cancelLogoUpload() {
                    this.previewUrl = null;
                    if (this.$refs.logoFile) {
                        this.$refs.logoFile.value = '';
                    }
                },

                // Icon Upload Handlers
                handleIconDrop(e) {
                    this.iconDragOver = false;
                    if (e.dataTransfer.files.length) {
                        this.$refs.iconFile.files = e.dataTransfer.files;
                        this.previewIcon({ target: this.$refs.iconFile });
                    }
                },

                previewIcon(event) {
                    const file = event.target.files[0];
                    if (file) {
                        if (file.size > 2 * 1024 * 1024) {
                            alert('Icon file size must be less than 2MB');
                            this.cancelIconUpload();
                            return;
                        }
                        this.iconPreviewUrl = URL.createObjectURL(file);
                    }
                },

                uploadIcon() {
                    this.saveSettings(true, false, false, true);
                },

                cancelIconUpload() {
                    this.iconPreviewUrl = null;
                    this.$refs.iconFile.value = '';
                },

                previewFavicon(event) {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    this.faviconPreviewUrl = URL.createObjectURL(file);
                },

                handleFaviconDrop(evt) {
                    this.faviconDragOver = false;
                    const file = evt.dataTransfer.files?.[0];
                    if (!file) return;

                    this.$refs.faviconFile.files = evt.dataTransfer.files;
                    this.previewFavicon({
                        target: {
                            files: this.$refs.faviconFile.files
                        }
                    });
                },

                async uploadFavicon() {
                    if (!this.$refs.faviconFile?.files?.[0]) return;
                    await this.saveSettings(true, false, true, false);
                },

                cancelFaviconUpload() {
                    this.faviconPreviewUrl = null;
                    if (this.$refs.faviconFile) {
                        this.$refs.faviconFile.value = '';
                    }
                },

                updateCustomColor(color) {
                    this.selectedColor = color;
                    this.customColor = color;
                }
            };

            return instance;
        }
    </script>
</x-app-layout>

