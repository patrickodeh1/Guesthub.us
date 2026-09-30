<x-admin-layout title="Property Notifications">
    <div class="page-header">
        <div>
            <p class="eyebrow">{{ $property->name }}</p>
            <h1 class="page-title">Notifications</h1>
            <p class="page-subtitle">Manage cleaning event notifications and recipients for this property. Global guest lifecycle notifications remain in <a class="font-semibold text-[var(--theme-primary)] underline" href="{{ route('admin.settings.notifications.edit') }}">Settings → Notifications</a>.</p>
        </div>
        <a href="{{ route('admin.properties.edit', $property) }}" class="btn-secondary">Back to property</a>
    </div>

    <div class="grid gap-6 xl:grid-cols-2" x-data="propertyNotifications()" x-init="init()">
        <section class="card card-pad">
            <h2 class="section-title">Cleaning event notifications</h2>
            <p class="section-copy">Global settings determine whether each event is enabled site-wide; these switches configure this property.</p>
            <div class="mt-5 grid gap-3">
                <template x-for="setting in settingsList" :key="setting.key">
                    <label class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 p-3">
                        <span class="text-sm font-medium text-slate-800" x-text="setting.label"></span>
                        <input type="checkbox" :checked="settings[setting.key]" @change="updateSetting(setting.key, $event.target.checked)">
                    </label>
                </template>
            </div>
        </section>

        <section class="card card-pad">
            <h2 class="section-title">Recipients</h2>
            <p class="section-copy">Add phone numbers that should receive this property’s cleaning notifications.</p>
            <form class="mt-5 grid gap-3 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="addRecipient">
                <input class="input mt-0" x-model="newName" placeholder="Name">
                <input class="input mt-0" x-model="newPhone" required placeholder="Phone number">
                <button class="btn-primary" type="submit" :disabled="saving">Add</button>
            </form>
            <p class="mt-3 text-sm text-emerald-700" x-show="message" x-text="message"></p>
            <p class="mt-3 text-sm text-red-700" x-show="error" x-text="error"></p>
            <ul class="mt-4 divide-y divide-slate-100">
                <template x-for="recipient in recipients" :key="recipient.id">
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900" x-text="recipient.recipient_name || 'Unnamed recipient'"></p>
                            <p class="text-sm text-slate-500" x-text="recipient.phone_number"></p>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 text-xs text-slate-600">
                                Active
                                <input type="checkbox" :checked="recipient.active" @change="updateRecipient(recipient, $event.target.checked)">
                            </label>
                            <button type="button" class="text-sm text-red-700 underline" @click="removeRecipient(recipient)">Remove</button>
                        </div>
                    </li>
                </template>
            </ul>
            <p class="mt-3 text-sm text-slate-500" x-show="loaded && recipients.length === 0">No recipients added yet.</p>
        </section>

        <section class="card card-pad xl:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="section-title">Notification history</h2>
                    <p class="section-copy">Recent delivery records for this property.</p>
                </div>
                <button type="button" class="btn-secondary" @click="loadHistory">Refresh history</button>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b text-xs uppercase text-slate-500"><th class="py-2 pr-4">Type</th><th class="py-2 pr-4">Recipient</th><th class="py-2 pr-4">Status</th><th class="py-2">Sent</th></tr></thead>
                    <tbody>
                        <template x-for="log in logs" :key="log.id">
                            <tr class="border-b border-slate-100"><td class="py-2 pr-4" x-text="log.notification_type"></td><td class="py-2 pr-4" x-text="log.recipient_phone"></td><td class="py-2 pr-4" x-text="log.delivery_status"></td><td class="py-2" x-text="log.sent_at ? new Date(log.sent_at).toLocaleString() : '—'"></td></tr>
                        </template>
                    </tbody>
                </table>
                <p class="py-4 text-sm text-slate-500" x-show="historyLoaded && logs.length === 0">No notifications sent yet.</p>
            </div>
        </section>
    </div>

    <script>
        function propertyNotifications() {
            return {
                loaded: false,
                saving: false,
                settings: {},
                recipients: [],
                logs: [],
                historyLoaded: false,
                newName: '',
                newPhone: '',
                message: '',
                error: '',
                settingsList: [
                    { key: 'notify_cleaning_started', label: 'Cleaning started' },
                    { key: 'notify_cleaning_finished', label: 'Cleaning finished' },
                    { key: 'notify_photo_started', label: 'Photo started' },
                    { key: 'notify_task_notes', label: 'Task notes' },
                ],
                urls: {
                    settings: @js(route('properties.notifications.settings', $property)),
                    update: @js(route('properties.notifications.update-settings', $property)),
                    recipients: @js(route('properties.notifications.add-recipient', $property)),
                    history: @js(route('properties.notifications.history', $property)),
                    updateRecipient: @js(route('properties.notifications.update-recipient', [$property, ':id'])),
                    deleteRecipient: @js(route('properties.notifications.delete-recipient', [$property, ':id'])),
                },
                async request(url, method = 'GET', body = null) {
                    const response = await fetch(url, {
                        method,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        ...(body ? { body: JSON.stringify(body) } : {}),
                    });
                    const data = await response.json();
                    if (!response.ok || data.success === false) throw new Error(data.message || 'Unable to update notifications.');
                    return data;
                },
                async init() {
                    try {
                        const data = await this.request(this.urls.settings);
                        this.settings = data.settings;
                        this.recipients = data.recipients;
                        this.loaded = true;
                        await this.loadHistory();
                    } catch (error) {
                        this.error = error.message;
                    }
                },
                async updateSetting(key, value) {
                    this.error = '';
                    try {
                        await this.request(this.urls.update, 'PUT', { [key]: value });
                        this.settings[key] = value;
                        this.message = 'Property notification setting saved.';
                    } catch (error) {
                        this.error = error.message;
                    }
                },
                async addRecipient() {
                    this.saving = true;
                    this.error = '';
                    try {
                        const data = await this.request(this.urls.recipients, 'POST', { recipient_name: this.newName, phone_number: this.newPhone });
                        this.recipients.push(data.recipient);
                        this.newName = '';
                        this.newPhone = '';
                        this.message = 'Recipient added.';
                    } catch (error) {
                        this.error = error.message;
                    } finally {
                        this.saving = false;
                    }
                },
                async updateRecipient(recipient, active) {
                    this.error = '';
                    try {
                        await this.request(this.urls.updateRecipient.replace(':id', recipient.id), 'PUT', { active });
                        recipient.active = active;
                        this.message = 'Recipient updated.';
                    } catch (error) {
                        this.error = error.message;
                    }
                },
                async removeRecipient(recipient) {
                    if (!confirm('Remove this notification recipient?')) return;
                    this.error = '';
                    try {
                        await this.request(this.urls.deleteRecipient.replace(':id', recipient.id), 'DELETE');
                        this.recipients = this.recipients.filter(item => item.id !== recipient.id);
                        this.message = 'Recipient removed.';
                    } catch (error) {
                        this.error = error.message;
                    }
                },
                async loadHistory() {
                    try {
                        const data = await this.request(this.urls.history);
                        this.logs = data.logs.data || [];
                        this.historyLoaded = true;
                    } catch (error) {
                        this.error = error.message;
                    }
                },
            };
        }
    </script>
</x-admin-layout>
