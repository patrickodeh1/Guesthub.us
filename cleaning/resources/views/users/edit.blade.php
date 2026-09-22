<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Edit User
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Update user information and profile settings.
                </p>
            </div>
            <x-button variant="secondary" href="{{ route('users.index') }}">
                Back to Users
            </x-button>
        </div>
    </x-slot>

    {{-- Success Message --}}
    <x-flash.ok :message="session('success')" />

    @php
        $authUser = auth()->user();
        $canManageStatus = false;
        if ($authUser && $authUser->id !== $user->id) {
            if ($authUser->hasRole('admin') && !$user->hasRole('admin')) {
                $canManageStatus = true;
            } elseif ($authUser->hasRole('owner') && !$authUser->hasRole('admin') && $user->hasRole('housekeeper')) {
                $canManageStatus = true;
            }
        }
    @endphp

    @if ($canManageStatus)
        <x-card class="mb-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Employment Status</h3>
            @if ($user->is_active)
                <div class="flex items-center gap-3 bg-emerald-50 dark:bg-emerald-950/40 p-4 rounded-xl border border-emerald-200 dark:border-emerald-800">
                    <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-600 dark:text-emerald-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-emerald-800 dark:text-emerald-200">Account is Active</p>
                        <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">This cleaner can log in and accept assignments.</p>
                    </div>
                </div>
            @else
                <div class="p-5 rounded-2xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-200 dark:bg-red-900 text-red-900 dark:text-red-100">
                                    Account Terminated / Deactivated
                                </span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                                Deactivated on <strong>{{ $user->terminated_at?->format('F j, Y \a\t g:i A') ?? 'N/A' }}</strong>
                                @if($user->terminator) by <strong>{{ $user->terminator->name }}</strong> @endif
                            </p>
                            @if($user->termination_reason)
                                <div class="text-xs mt-3 italic bg-white/70 dark:bg-black/30 p-3 rounded-xl border border-red-200 dark:border-red-800/50">
                                    "<strong>Reason:</strong> {{ $user->termination_reason }}"
                                </div>
                            @endif
                        </div>
                        <div>
                            <form method="post" action="{{ route('users.reactivate', $user) }}" onsubmit="return confirm('Are you sure you want to reactivate {{ $user->name }}?');">
                                @csrf
                                <x-button type="submit" class="!bg-emerald-600 hover:!bg-emerald-700 !text-white text-xs w-full sm:w-auto">
                                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Reactivate Account
                                </x-button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </x-card>
    @endif

    <x-card>
        <form x-data="userEditForm()" method="post" action="{{ route('users.update', $user) }}"
            enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Left column: Profile Photo --}}
                <div class="lg:col-span-1">
                    <x-form.label value="Profile Photo" />
                    <div class="mt-1 border-2 border-dashed rounded-2xl p-6 flex flex-col items-center justify-center text-center bg-gray-50/40 dark:bg-gray-800/40">
                        @php
                            $currentPhotoUrl = $user->profile_photo_url;
                        @endphp

                        <template x-if="!previewUrl">
                            <div class="w-full">
                                <img src="{{ $currentPhotoUrl }}" alt="Current photo"
                                    class="rounded-full object-cover h-40 w-40 mx-auto shadow-lg border-4 border-white dark:border-gray-700" />
                                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    Current profile photo
                                </p>
                            </div>
                        </template>

                        <template x-if="previewUrl">
                            <div class="w-full">
                                <img :src="previewUrl" alt="Preview"
                                    class="rounded-full object-cover h-40 w-40 mx-auto shadow-lg border-4 border-white dark:border-gray-700" />
                                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    New photo preview
                                </p>
                            </div>
                        </template>

                        <input type="file" name="profile_photo" class="hidden" x-ref="file" @change="preview($event)"
                            accept="image/*" />

                        <div class="mt-4 flex flex-col items-center gap-2">
                            <x-button type="button" variant="secondary" @click="$refs.file.click()">
                                Choose New Photo
                            </x-button>

                            @if ($user->profile_photo_path)
                                <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
                                    <x-form.checkbox name="remove_profile_photo" value="1" />
                                    Remove photo
                                </label>
                            @endif
                        </div>

                        @error('profile_photo')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Right column: Form fields --}}
                <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Name --}}
                    <div class="md:col-span-2">
                        <x-form.label value="Full Name" />
                        <x-form.input name="name" class="w-full" required :value="old('name', $user->name)"
                            placeholder="e.g. John Doe" />
                        <x-form.error :messages="$errors->get('name')" />
                    </div>

                    {{-- Email --}}
                    <div class="md:col-span-2">
                        <x-form.label value="Email Address" />
                        <x-form.input name="email" type="email" class="w-full" required :value="old('email', $user->email)"
                            placeholder="e.g. john@example.com" />
                        <x-form.error :messages="$errors->get('email')" />
                    </div>

                    {{-- Phone Number --}}
                    <div>
                        <x-form.label value="Phone Number (optional)" />
                        <x-form.input name="phone_number" type="tel" class="w-full" :value="old('phone_number', $user->phone_number)"
                            placeholder="e.g. +1 234 567 8900" />
                        <x-form.error :messages="$errors->get('phone_number')" />
                    </div>

                    {{-- Master Owner (Primary association) --}}
                    @if (auth()->user()->hasAnyRole(['admin', 'owner', 'company']))
                        @php
                            $userRole = $user->roles->first()?->name;
                            $showOwnerSelection = ($userRole === 'housekeeper');
                        @endphp
                        
                        <div x-show="selectedRole === 'housekeeper' || selectedRole === 'company'" class="md:col-span-2 space-y-4">
                            <div>
                                <x-form.label value="Primary Owner / Company" />
                                <x-form.select name="owner_id" class="w-full">
                                    <option value="">— Direct (Managed by you) —</option>
                                    @foreach ($owners ?? [] as $owner)
                                        <option value="{{ $owner->id }}" @selected(old('owner_id', $user->owner_id) == $owner->id)>
                                            {{ $owner->name }} ({{ ucfirst($owner->roles->first()?->name) }})
                                        </option>
                                    @endforeach
                                </x-form.select>
                            </div>

                            {{-- Multiple Owner Assignment --}}
                            <div>
                                <x-form.label value="Also attach to these Owners (Optional)" />
                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
                                    @foreach ($owners ?? [] as $owner)
                                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer hover:text-indigo-600 transition-colors">
                                            <input type="checkbox" name="owner_ids[]" value="{{ $owner->id }}" 
                                                   class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500"
                                                   @checked(in_array($owner->id, old('owner_ids', $user->managedOwners->pluck('id')->toArray())))>
                                            {{ $owner->name }}
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-2 text-xs text-gray-500">
                                    Housekeepers and Company users will be able to manage properties/sessions for all selected owners.
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Role (admin can change any role, owner can assign housekeeper to their housekeepers) --}}
                    @php
                        $authUser = auth()->user();
                        $canChangeRole = false;
                        $roleOptions = [];

                        if ($authUser->hasRole('admin') && $authUser->id !== $user->id) {
                            $canChangeRole = true;
                            $roleOptions = ['admin', 'owner', 'company', 'housekeeper'];
                        } elseif ($authUser->hasRole('company') && !$authUser->hasRole('admin') && $authUser->id !== $user->id) {
                            // Company can assign owner and housekeeper roles
                            $isDirectlyOwned = $user->owner_id === $authUser->id;
                            if ($isDirectlyOwned) {
                                $canChangeRole = true;
                                $roleOptions = ['owner', 'housekeeper'];
                            }
                        } elseif ($authUser->hasRole('owner') && !$authUser->hasRole('admin') && !$authUser->hasRole('company') && $authUser->id !== $user->id) {
                            // Owner can only assign housekeeper role
                            if ($user->owner_id === $authUser->id) {
                                $canChangeRole = true;
                                $roleOptions = ['housekeeper'];
                            }
                        }
                    @endphp

                    @if ($canChangeRole)
                        <div>
                            <x-form.label value="Role" />
                            <x-form.select name="role" class="w-full" x-model="selectedRole">
                                <option value="">— Keep Current Role —</option>
                                @foreach ($roleOptions as $roleOption)
                                    <option value="{{ $roleOption }}" @selected(old('role', $user->roles->first()?->name) === $roleOption)>
                                        {{ ucfirst($roleOption) }}
                                    </option>
                                @endforeach
                            </x-form.select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Current: <span class="font-medium">{{ $user->roles->first()?->name ?? 'No role' }}</span>
                                @if ($authUser->hasRole('owner') && !$authUser->hasRole('admin'))
                                    <br>You can only assign the housekeeper role.
                                @endif
                            </p>
                            <x-form.error :messages="$errors->get('role')" />
                        </div>
                    @else
                        <div>
                            <x-form.label value="Role" />
                            <x-form.input type="text" class="w-full bg-gray-100 dark:bg-gray-700"
                                :value="$user->roles->first()?->name ?? 'No role'" disabled />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                @if ($authUser->id === $user->id)
                                    You cannot change your own role.
                                @else
                                    You do not have permission to change this user's role.
                                @endif
                            </p>
                        </div>
                    @endif

                    {{-- Password (optional) --}}
                    <div>
                        <x-form.label value="New Password (optional)" />
                        <x-form.input name="password" type="password" class="w-full"
                            placeholder="Leave blank to keep current" autocomplete="new-password" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Leave blank if you don't want to change the password.
                        </p>
                        <x-form.error :messages="$errors->get('password')" />
                    </div>

                    {{-- Password Confirmation --}}
                    <div>
                        <x-form.label value="Confirm New Password" />
                        <x-form.input name="password_confirmation" type="password" class="w-full"
                            placeholder="Re-enter new password" autocomplete="new-password" />
                        <x-form.error :messages="$errors->get('password_confirmation')" />
                    </div>

                    {{-- Property Assignment --}}
                    @if(isset($properties) && $properties->count() > 0)
                        <div class="md:col-span-2 pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Assigned Properties</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                                Select which properties this user can access and manage.
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
                                @foreach ($properties as $property)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer hover:text-indigo-600 transition-colors p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                        <input type="checkbox" name="property_ids[]" value="{{ $property->id }}"
                                               class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500"
                                               @checked(in_array($property->id, old('property_ids', $user->properties->pluck('id')->toArray())))>
                                        <span>{{ $property->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Preferences --}}
                    @php
                        $preferences = $user->preferences ?? [];
                        $autoSaveEnabled = $preferences['auto_save_enabled'] ?? 1;
                        $autoSaveDelay = $preferences['auto_save_delay'] ?? 400;
                        $notifySessionStarted = $preferences['notify_session_started'] ?? 1;
                        $notifySessionCompleted = $preferences['notify_session_completed'] ?? 1;
                        $notifyAssignments = $preferences['notify_assignments'] ?? 1;
                        $requiredInstructionViews = $preferences['required_instruction_views'] ?? 3;
                    @endphp
                    
                    <div class="md:col-span-2 pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">User Preferences</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Auto-save Settings --}}
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Auto-save Settings</h4>
                                <div class="space-y-4">
                                    <label class="flex items-center">
                                        <input type="hidden" name="preferences[auto_save_enabled]" value="0">
                                        <x-form.checkbox name="preferences[auto_save_enabled]" value="1"
                                            :checked="old('preferences.auto_save_enabled', $autoSaveEnabled) == 1" />
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            Enable auto-save for forms
                                        </span>
                                    </label>
                                    
                                    <div class="ml-8 mt-2">
                                        <x-form.label for="auto_save_delay" value="Auto-save Delay (ms)" class="text-xs" />
                                        <x-form.input id="auto_save_delay" name="preferences[auto_save_delay]" type="number" min="100" max="5000" step="100"
                                            :value="old('preferences.auto_save_delay', $autoSaveDelay)" class="w-full mt-1" />
                                    </div>
                                </div>
                            </div>

                            {{-- Notification Preferences --}}
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Notification Preferences</h4>
                                <div class="space-y-3">
                                    <label class="flex items-center">
                                        <input type="hidden" name="preferences[notify_session_started]" value="0">
                                        <x-form.checkbox name="preferences[notify_session_started]" value="1"
                                            :checked="old('preferences.notify_session_started', $notifySessionStarted) == 1" />
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            Notify when a session starts
                                        </span>
                                    </label>

                                    <label class="flex items-center">
                                        <input type="hidden" name="preferences[notify_session_completed]" value="0">
                                        <x-form.checkbox name="preferences[notify_session_completed]" value="1"
                                            :checked="old('preferences.notify_session_completed', $notifySessionCompleted) == 1" />
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            Notify when a session completes
                                        </span>
                                    </label>

                                    <label class="flex items-center">
                                        <input type="hidden" name="preferences[notify_assignments]" value="0">
                                        <x-form.checkbox name="preferences[notify_assignments]" value="1"
                                            :checked="old('preferences.notify_assignments', $notifyAssignments) == 1" />
                                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            Notify on new assignments
                                        </span>
                                    </label>
                                </div>
                            </div>
                            
                            @if ($user->hasRole('housekeeper'))
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Training & Instructions</h4>
                                <div class="space-y-4">
                                    <div class="mt-2">
                                        <x-form.label for="required_instruction_views" value="Required Instruction Views" class="text-xs" />
                                        <x-form.input id="required_instruction_views" name="preferences[required_instruction_views]" type="number" min="1" max="100" step="1"
                                            :value="old('preferences.required_instruction_views', $requiredInstructionViews)" class="w-full mt-1" />
                                        <p class="text-xs text-gray-500 mt-1">Number of times a cleaner must view an instruction before they can complete it without viewing.</p>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if ($user->hasRole('housekeeper') && isset($familiarities) && $familiarities->count() > 0)
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Instruction Familiarity Progress</h3>
                    
                    <form method="POST" action="{{ route('users.familiarity.reset_all', $user) }}" onsubmit="return confirm('Are you sure you want to reset familiarity for all tasks? The cleaner will have to view instructions again.');">
                        @csrf
                        <x-button type="submit" variant="secondary" size="sm" class="!bg-red-50 hover:!bg-red-100 !text-red-700 dark:!bg-red-900/30 dark:!text-red-400">
                            Reset All Familiarity
                        </x-button>
                    </form>
                </div>
                
                <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-md border border-gray-200 dark:border-gray-700">
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($familiarities as $fam)
                            @php
                                $required = $requiredInstructionViews;
                                $isFamiliar = $fam->views_completed >= $required;
                                $progress = min(100, ($fam->views_completed / $required) * 100);
                            @endphp
                            <li class="p-4 sm:px-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 min-w-0 pr-4">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-medium text-purple-600 dark:text-purple-400 truncate">
                                                {{ $fam->task->name ?? 'Unknown Task' }}
                                            </p>
                                            <div class="ml-2 flex-shrink-0 flex">
                                                @if($isFamiliar)
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                        Familiar
                                                    </span>
                                                @else
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                        Learning
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="mt-2 flex items-center justify-between">
                                            <div class="sm:flex">
                                                <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                                                    <span>Views: {{ $fam->views_completed }} / {{ $required }}</span>
                                                    <span class="mx-2">&bull;</span>
                                                    <span>Last Viewed: {{ $fam->last_viewed_at ? $fam->last_viewed_at->format('M j, Y') : 'Never' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-700">
                                            <div class="bg-purple-600 h-1.5 rounded-full" style="width: {{ $progress }}%"></div>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-shrink-0">
                                        <form method="POST" action="{{ route('users.familiarity.reset_task', ['user' => $user, 'task' => $fam->task_id]) }}" onsubmit="return confirm('Reset familiarity for this task?');">
                                            @csrf
                                            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">
                                                Reset
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif

            <div class="mt-8 flex flex-wrap justify-between items-center gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                <div>
                    @if ($canManageStatus)
                        @if ($user->is_active)
                                <x-button type="button" variant="secondary" @click="$dispatch('open-deactivate-modal')"
                                    class="!bg-amber-600 hover:!bg-amber-700 !text-white">
                                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    Deactivate Account
                                </x-button>
                        @endif
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <x-button type="submit" class="bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500">
                        Update User
                    </x-button>
                    <x-button variant="secondary" href="{{ route('users.index') }}">
                        Cancel
                    </x-button>
                </div>
            </div>
        </form>
    </x-card>

    @if ($canManageStatus && $user->is_active)
        <div x-data="{ openDeactivateModal: false }" @open-deactivate-modal.window="openDeactivateModal = true">
            <div x-show="openDeactivateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="openDeactivateModal" @click="openDeactivateModal = false" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div x-show="openDeactivateModal" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6 border border-gray-100 dark:border-gray-700">
                        <form method="post" action="{{ route('users.deactivate', $user) }}">
                            @csrf
                            <div class="flex items-center gap-3 text-amber-600 dark:text-amber-400 mb-3">
                                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Deactivate Cleaner Account</h3>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                Are you sure you want to deactivate <strong>{{ $user->name }}</strong>? This cleaner will be blocked from logging in and removed from all future assignment dropdowns. All historical cleaning records and reports will be preserved.
                            </p>
                            <div class="mb-5">
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                                    Reason for Termination <span class="text-red-500">*</span>
                                </label>
                                <textarea name="termination_reason" required rows="3"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm focus:ring-indigo-500 focus:border-indigo-500 p-3"
                                    placeholder="Specify the reason for deactivation (e.g. Left company, performance, policy breach)..."></textarea>
                            </div>
                            <div class="flex justify-end gap-3">
                                <x-button type="button" variant="secondary" @click="openDeactivateModal = false">Cancel</x-button>
                                <x-button type="submit" class="!bg-red-600 hover:!bg-red-700 !text-white">Confirm Deactivation</x-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Alpine.js helpers --}}
    <script>
        function userEditForm() {
            return {
                previewUrl: null,
                selectedRole: @json(old('role', $user->roles->first()?->name ?? '')),

                preview(event) {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    this.previewUrl = URL.createObjectURL(file);
                },
            }
        }
    </script>
</x-app-layout>

