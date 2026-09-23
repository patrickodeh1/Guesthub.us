/**
 * Checklist Renderer - Renders checklist dynamically from API data
 * Reduces Blade code by handling all rendering in JavaScript
 */

export default function checklistRenderer(config = {}) {
    return {
        sessionId: null,
        reportUrl: null,
        sessionData: null,
        loading: false,
        error: null,
        renderedContent: '',
        dataUrl: config.dataUrl || null,
        fallbackDataUrl: null,
        photoDeleteUrl: config.photoDeleteUrl || null,
        viewOverride: null,
        _showPhotoUpload: false,
        _showPhotoExtrasPrompt: false,
        instructionsOpen: false,
        instructionsLoading: false,
        instructionsError: null,
        instructionsData: null,
        activeTaskId: null,
        activeRoomId: null,

        updateStepBtnVisibility(shouldBeVisible) {
            const stepBtn = document.querySelector('button#stepBtn');
            if (!stepBtn) return;
            const isEditReport = new URLSearchParams(window.location.search).get('edit_report') === '1' && this.sessionData?.is_admin;
            if (isEditReport) {
                stepBtn.classList.remove('hidden');
                stepBtn.innerHTML = 'Submit Session';
            } else {
                stepBtn.classList.add('hidden'); // ALWAYS HIDE for cleaners
            }
        },

        init() {
            // Get session ID from data attribute or URL
            const container = document.querySelector('[data-session-id]');
            this.sessionId = container?.dataset.sessionId ||
                window.location.pathname.match(/\/sessions\/([^\/]+)/)?.[1];
            this.reportUrl = container?.dataset.reportUrl || null;

            if (!this.sessionId) {
                console.error('Session ID not found');
                return;
            }

            // Get CSRF token
            this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            // Build data URL if not provided (fallback for backward compatibility)
            // Prefer the non-API route, but keep an API fallback to avoid breaking older/newer backends
            if (!this.dataUrl) {
                this.dataUrl = `/sessions/${this.sessionId}/data`;
            }
            this.fallbackDataUrl = `/api/sessions/${this.sessionId}/data`;

            // Forward edit_report param from the page URL to the data API URL
            // so the backend knows to set stage='rooms' instead of 'summary'
            const pageParams = new URLSearchParams(window.location.search);
            if (pageParams.get('edit_report') === '1') {
                const separator = this.dataUrl.includes('?') ? '&' : '?';
                this.dataUrl += `${separator}edit_report=1`;
                const fbSeparator = this.fallbackDataUrl.includes('?') ? '&' : '?';
                this.fallbackDataUrl += `${fbSeparator}edit_report=1`;
            }

            // Store edit_report flags for use in all API calls throughout the renderer
            this.isEditReport = pageParams.get('edit_report') === '1';
            this.editSuffix = this.isEditReport ? '?edit_report=1' : '';

            // Load initial data
            this.loadSessionData();

            // Listen for clicks on task list items to track active task/room
            document.addEventListener('click', (e) => {
                const taskEl = e.target.closest('[data-task-item]');
                if (taskEl) {
                    const taskId = taskEl.dataset.taskId;
                    const roomEl = taskEl.closest('[data-room-id]');
                    const roomId = roomEl ? roomEl.dataset.roomId : null;
                    
                    this.activeTaskId = taskId ? parseInt(taskId) : null;
                    this.activeRoomId = roomId ? parseInt(roomId) : null;
                }
            });
        },

        async loadSessionData() {
            this.loading = true;
            this.error = null;

            try {
                let response;
                try {
                    response = await window.api.get(this.dataUrl);
                } catch (e) {
                    // If the non-API route isn't available, fall back to the API route
                    const status = e?.response?.status;
                    if (status === 404 && this.fallbackDataUrl) {
                        response = await window.api.get(this.fallbackDataUrl);
                    } else {
                        throw e;
                    }
                }

                if (response.success && response.data) {
                    this.sessionData = response.data;
                    this.renderChecklist();
                } else {
                    throw new Error('Failed to load session data');
                }
            } catch (error) {
                console.error('Error loading session data:', error);

                // Provide user-friendly error messages
                const status = error.response?.status;
                let errorMessage = 'Failed to load checklist. Please try again.';

                if (status === 401 || status === 403) {
                    errorMessage = 'You do not have permission to access this checklist. Please ensure you are logged in with the correct account.';
                } else if (status === 404) {
                    errorMessage = 'This session could not be found. It may have been deleted or you may not have access.';
                } else if (status === 500) {
                    errorMessage = 'A server error occurred. Please try again or contact support if the problem persists.';
                } else if (!navigator.onLine) {
                    errorMessage = 'You appear to be offline. Please check your internet connection and try again.';
                } else if (error.response?.data?.message) {
                    errorMessage = error.response.data.message;
                } else if (error.message) {
                    errorMessage = error.message;
                }

                this.error = errorMessage;
            } finally {
                this.loading = false;
            }
        },

        async skipRoom(roomId) {
            if (!confirm('Are you sure you want to skip this room?')) return;
            try {
                const response = await window.api.post(`/sessions/${this.sessionId}/rooms/${roomId}/skip`);
                if (response.success) {
                    // Update local data
                    if (!this.sessionData.session.skipped_rooms) this.sessionData.session.skipped_rooms = [];
                    this.sessionData.session.skipped_rooms.push(roomId);
                    this.renderChecklist();
                }
            } catch (error) {
                console.error('Error skipping room:', error);
                alert('Failed to skip room.');
            }
        },

        async unskipRoom(roomId) {
            try {
                const response = await window.api.post(`/sessions/${this.sessionId}/rooms/${roomId}/unskip`);
                if (response.success) {
                    // Update local data
                    this.sessionData.session.skipped_rooms = this.sessionData.session.skipped_rooms.filter(id => id !== roomId);
                    this.renderChecklist();
                }
            } catch (error) {
                console.error('Error unskipping room:', error);
                alert('Failed to unskip room.');
            }
        },

        navigateBack(currentStage, event) {
            if (event) {
                const btn = event.currentTarget || event.target.closest('button');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Navigating...';
                }
            }

            const stages = ['pre_cleaning', 'rooms', 'during_cleaning', 'photos', 'post_cleaning', 'summary'];
            const actualStage = this.sessionData?.stage;
            const actualIndex = stages.indexOf(actualStage);
            const viewIndex = stages.indexOf(currentStage);

            if (viewIndex > 0) {
                // Navigate backward locally
                this.viewOverride = stages[viewIndex - 1];
                this.renderChecklist();
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }
        },

        getRoomMinPhotos(room) {
            const skippedRooms = this.sessionData?.session?.skipped_rooms || [];
            if (skippedRooms.includes(room.id)) return 0;
            
            const tasksArr = Array.isArray(room.tasks) ? room.tasks : Object.values(room.tasks || {});
            const hasActionable = tasksArr.some(t => t.type !== 'instructions');
            
            if (!hasActionable) return 0;
            return Number(room.min_photos ?? 2);
        },

        renderChecklist() {
            if (!this.sessionData) return;

            const stage = this.viewOverride || this.sessionData.stage;

            // Update stage indicator
            const stageElements = document.querySelectorAll('[data-stage-area]');
            const stageCurrentElements = document.querySelectorAll('[data-current-stage]');
            const reportUrlContainer = document.querySelector('[data-report-url]');
            const reportUrl = reportUrlContainer ? reportUrlContainer.dataset.reportUrl : null;
            
            const stageName = stage ? stage.replace(/_/g, ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ') : 'Unknown';
            const stageBadgeHtml = stage === 'summary' 
                ? `<span class="px-3 py-1 rounded-lg text-sm font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200">Completed</span>`
                : `<span class="px-3 py-1 rounded-lg text-sm font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200">${stageName}</span>`;

            stageElements.forEach(el => {
                el.innerHTML = stageBadgeHtml;
            });
            
            const liveReportBtn = document.getElementById('live-report-btn');
            if (liveReportBtn) {
                liveReportBtn.textContent = stage === 'summary' ? 'View Report' : 'View Live Report';
            }

            stageCurrentElements.forEach(el => {
                el.textContent = stageName;
            });

            // Store rendered HTML in Alpine reactive property
            let renderedHtml = '';
            
            if (this.viewOverride && this.viewOverride !== this.sessionData.stage) {
                renderedHtml += `
                    <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-4 mb-6 rounded-r-lg shadow-sm flex items-start justify-between gap-4">
                        <div>
                            <p class="text-blue-700 dark:text-blue-300 font-medium flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Viewing Previous Stage
                            </p>
                            <p class="text-sm text-blue-600 dark:text-blue-400 mt-1">You can check off tasks without losing your current progress.</p>
                        </div>
                        <button type="button" @click="const r = $el.closest('[x-data*=\\'checklistRenderer\\']')._x_dataStack[0]; r.viewOverride = null; r.renderChecklist(); window.scrollTo({top:0, behavior:'smooth'});" class="text-sm font-bold text-blue-700 dark:text-blue-300 hover:underline whitespace-nowrap bg-blue-100 dark:bg-blue-800/50 px-3 py-1.5 rounded-lg">Return to Active Stage</button>
                    </div>
                `;
            }

            // Reset photo prompt when re-rendering (unless we're on photos stage and user already clicked Yes)
            if (stage !== 'photos') {
                this._showPhotoUpload = false;
                this._showPhotoExtrasPrompt = false;
            }

            // Dynamically update status badges on the page
            if (this.sessionData.session?.status === 'completed' || stage === 'summary') {
                document.querySelectorAll('[data-status-badge]').forEach(el => {
                    el.innerHTML = `<span class="inline-flex items-center justify-center rounded-full bg-green-100 px-2.5 py-0.5 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                        <svg class="-ml-1 mr-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        Completed
                    </span>`;
                });
            }

            // Render based on stage
            switch (stage) {
                case 'pre_cleaning':
                    renderedHtml = this.renderPropertyTasks('pre_cleaning', 'Pre-Cleaning Tasks');
                    break;
                case 'rooms':
                case 'rooms_first_half':  // Legacy fallback
                case 'rooms_second_half': // Legacy fallback
                    renderedHtml = this.renderRooms();
                    break;
                case 'during_cleaning':
                    renderedHtml = this.renderPropertyTasks('during_cleaning', 'Mid-Cleaning Tasks');
                    break;
                case 'photos':
                    renderedHtml = this.renderPhotosRoomByRoom();
                    break;
                case 'post_cleaning':
                    renderedHtml = this.renderPropertyTasks('post_cleaning', 'End-of-Cleaning Tasks');
                    break;
                case 'inventory':
                    renderedHtml = this.renderInventory();
                    break;
                case 'summary':
                    renderedHtml = this.renderSummary();
                    break;
                default:
                    renderedHtml = '<p class="text-gray-500">Unknown stage</p>';
            }

            // Store in Alpine reactive property
            this.renderedContent = renderedHtml;

            // Use setTimeout to ensure Alpine processes the update and DOM is ready
            setTimeout(() => {
                // Re-initialize event handlers after rendering
                this.setupEventHandlers();
            }, 100);
        },

        renderPropertyTasks(phase, title) {
            this.updateStepBtnVisibility(true);
            // Ensure tasks is an array
            let tasks = this.sessionData.property_tasks[phase] || [];
            if (!Array.isArray(tasks)) {
                tasks = Object.values(tasks);
            }
            const counts = this.sessionData.counts[phase] || { total: 0, checked: 0 };



            return `
                <div class="space-y-6">
                    <div class="pb-6">
                        <!-- Back Button -->
                        <div class="mb-4">
                            <button type="button"
                                    @click="
                                        const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                        if (rendererEl && rendererEl._x_dataStack) {
                                            rendererEl._x_dataStack[0].navigateBack('${phase}', $event);
                                        }
                                    "
                                    class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Go Back
                            </button>
                        </div>
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">${title}</h2>
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                        <div class="h-full rounded-full bg-blue-600 transition-all duration-500"
                                             style="width: ${counts.total > 0 ? (counts.checked / counts.total * 100) : 0}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">${counts.checked} / ${counts.total}</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden divide-y divide-gray-100 dark:divide-gray-700/50 shadow-sm">
                            ${tasks.length > 0 ? tasks.map(task => this.renderTaskItem(task, null)).join('') : `<p class="text-center text-gray-500 dark:text-gray-400 py-8 text-sm">No ${title.toLowerCase()} defined.</p>`}
                        </div>
                    </div>
                    ${(() => {
                        return `
                        <div class="mt-8 flex justify-end">
                            <button type="button"
                                    @click="window.checklistHandler.saveProgress()"
                                    class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold text-sm shadow-sm cursor-pointer flex items-center justify-center gap-2 w-full sm:w-auto">
                                Next Stage <span>→</span>
                            </button>
                        </div>
                    `;
                    })()}
                </div>
            `;
        },

        renderRooms() {
            this.updateStepBtnVisibility(true);
            
            // Ensure rooms is an array
            let rooms = this.sessionData.rooms || [];
            if (!Array.isArray(rooms)) {
                rooms = Object.values(rooms);
            }

            // Calculate which rooms are complete and which should be enabled
            let firstIncompleteIndex = null;
            rooms.forEach((room, index) => {
                // Ensure room.tasks is an array
                const roomTasksArray = Array.isArray(room.tasks) ? room.tasks : Object.values(room.tasks || {});
                const roomTasks = roomTasksArray.filter(t => room.room_tasks.includes(t.id));
                // Exclude instruction-only tasks from counts
                const countableTasks = roomTasks.filter(t => t.type !== 'instructions');
                const checkedCount = countableTasks.filter(t => t.checklist_item?.checked).length;
                const totalCount = countableTasks.length;

                // If this room is incomplete and we haven't found the first incomplete yet
                if (firstIncompleteIndex === null && checkedCount < totalCount && totalCount > 0) {
                    firstIncompleteIndex = index;
                }
            });            return `
                <div class="space-y-8">
                    ${rooms.map((room, index) => {
                const roomTasksArray = Array.isArray(room.tasks) ? room.tasks : Object.values(room.tasks || {});
                const roomTasks = roomTasksArray.filter(t => room.room_tasks.includes(t.id));
                const countableTasks = roomTasks.filter(t => t.type !== 'instructions');
                const checkedCount = countableTasks.filter(t => t.checklist_item?.checked).length;
                const totalCount = countableTasks.length;
                const isComplete = checkedCount === totalCount && totalCount > 0;
                const skippedRooms = this.sessionData?.session?.skipped_rooms || [];
                const isSkipped = skippedRooms.includes(room.id);
                const isDisabled = isSkipped;

                return `
                            <div class="pb-6 border-b border-gray-200 dark:border-gray-700 last:border-0 ${isDisabled ? 'opacity-60' : ''}"
                                 data-room-id="${room.id}" data-room-index="${index}">
                                <div class="mb-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                            <span class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs font-bold">
                                                ${index + 1}
                                            </span>
                                            ${this.escapeHtml(room.name)}
                                        </h3>
                                        <div class="flex items-center gap-2">
                                            ${isComplete ? `
                                                <span class="text-xs px-2 py-1 rounded bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200">
                                                    ✓ Complete
                                                </span>
                                            ` : ''}
                                            ${isSkipped ? `
                                                <span class="text-[10px] px-2 py-1 rounded bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200">
                                                    Skipped
                                                </span>
                                                <button type="button" @click.stop="unskipRoom(${room.id})" class="text-[11px] text-blue-600 hover:text-blue-800 underline ml-2">Unskip</button>
                                            ` : `
                                                <button type="button" @click.stop="skipRoom(${room.id})" class="text-[11px] text-gray-500 hover:text-gray-700 underline">Skip</button>
                                            `}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 pl-8">
                                        <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                            <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-500" style="width: ${totalCount === 0 ? 0 : (checkedCount / totalCount) * 100}%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">${checkedCount} / ${totalCount}</span>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden divide-y divide-gray-100 dark:divide-gray-700/50 shadow-sm">
                                    ${roomTasks.map(task => this.renderTaskItem(task, room, isDisabled)).join('')}
                                </div>
                            </div>
                        `;
                    }).join('')}

                    ${rooms.length > 0 ? `
                        <div class="mt-8 flex justify-end">
                            <button type="button"
                                    @click="window.checklistHandler.saveProgress()"
                                    class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold text-sm shadow-sm cursor-pointer flex items-center justify-center gap-2 w-full sm:w-auto">
                                Next Stage <span>→</span>
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;
        },

        renderRoomsSubset(stageKey, title) {
            this.updateStepBtnVisibility(true);
            

            let rooms = this.sessionData[stageKey] || [];
            if (!Array.isArray(rooms)) rooms = Object.values(rooms);

            // Back button HTML
            const backBtn = `
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4">
                    <button type="button"
                            @click="
                                const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                if (rendererEl && rendererEl._x_dataStack) {
                                    rendererEl._x_dataStack[0].navigateBack('${stageKey}', $event);
                                }
                            "
                            class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Go Back
                    </button>
                </div>
            `;

            // Title header
            const header = `
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">${title}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${rooms.length} room${rooms.length !== 1 ? 's' : ''} in this section</p>
                </div>
            `;

            return `
                <div class="space-y-6">
                    ${backBtn}
                    ${header}
                    ${rooms.map((room, index) => {
                const roomTasksArray = Array.isArray(room.tasks) ? room.tasks : Object.values(room.tasks || {});
                const roomTasks = roomTasksArray.filter(t => room.room_tasks.includes(t.id));
                const countableTasks = roomTasks.filter(t => t.type !== 'instructions');
                const checkedCount = countableTasks.filter(t => t.checklist_item?.checked).length;
                const totalCount = countableTasks.length;
                const isComplete = checkedCount === totalCount && totalCount > 0;
                const skippedRooms = this.sessionData?.session?.skipped_rooms || [];
                const isSkipped = skippedRooms.includes(room.id);
                const isDisabled = isSkipped;

                return `
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 sm:p-5 ${isDisabled ? 'opacity-60' : ''}"
                         data-room-id="${room.id}" data-room-index="${index}">
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100">${room.name}</h3>
                                ${isComplete ? '<span class="text-xs px-2 py-1 rounded bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200">✓ Complete</span>' : ''}
                                ${isSkipped ? '<span class="text-xs px-2 py-1 rounded bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200">Skipped</span>' : ''}
                                <div class="ml-2">
                                    ${isSkipped ? `<button type="button" @click.stop="unskipRoom(${room.id})" class="text-xs text-blue-600 hover:text-blue-800 underline">Unskip</button>` : `<button type="button" @click.stop="skipRoom(${room.id})" class="text-xs text-gray-500 hover:text-gray-700 underline">Skip</button>`}
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="h-full rounded-full bg-blue-600 transition-all duration-500"
                                     style="width: ${totalCount > 0 ? (checkedCount / totalCount * 100) : 0}%"></div>
                            </div>
                            <div class="flex items-center justify-between mt-1.5">
                                <span class="text-[10px] sm:text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Progress</span>
                                <span class="text-xs font-bold text-blue-600 dark:text-blue-400">${checkedCount}/${totalCount}</span>
                            </div>
                        </div>
                        <div class="space-y-2">
                            ${roomTasks.map(task => this.renderTaskItem(task, room, isDisabled)).join('')}
                        </div>
                    </div>
                `;
            }).join('')}
                    ${(() => {
                        let incompleteRoomTasks = [];
                        rooms.forEach(r => {
                            const rTasksArr = Array.isArray(r.tasks) ? r.tasks : Object.values(r.tasks || {});
                            const rTasks = rTasksArr.filter(t => r.room_tasks.includes(t.id));
                            rTasks.forEach(t => {
                                if (t.type !== 'instructions' && (t.type === 'verify' || t.type === 'inventory') && !t.checklist_item?.checked) {
                                    incompleteRoomTasks.push({ roomName: r.name, taskName: t.name, taskType: t.type });
                                }
                            });
                        });
                        const hasBlocking = incompleteRoomTasks.length > 0;
                        return `
                        <div x-data="{ showWarning: false }">
                            ${hasBlocking ? `
                                <div x-show="showWarning" x-cloak class="mt-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        <div>
                                            <p class="font-semibold text-amber-800 dark:text-amber-200 text-sm">Cannot advance yet</p>
                                            <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">Complete these verify/inventory tasks before advancing:</p>
                                            <ul class="mt-2 space-y-1">
                                                ${incompleteRoomTasks.map(t => `<li class="text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 flex-shrink-0"></span><span class="font-medium">${t.roomName}</span> — ${t.taskName} <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-200 dark:bg-amber-800 text-amber-800 dark:text-amber-200">${t.taskType}</span></li>`).join('')}
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            ` : ''}
                            <div class="mt-6">
                                <button type="button"
                                        @click="${hasBlocking ? 'showWarning = true' : 'window.checklistHandler.saveProgress()'}"
                                        class="w-full px-6 py-4 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors font-semibold text-lg shadow-sm cursor-pointer flex items-center justify-center gap-2">
                                    Next Stage <span class="text-xl">→</span>
                                </button>
                            </div>
                        </div>
                    `;
                    })()}
                </div>
            `;
        },

        renderPhotosRoomByRoom() {
            this.updateStepBtnVisibility(false);
            

            let rooms = this.sessionData.rooms || [];
            if (!Array.isArray(rooms)) rooms = Object.values(rooms);

            const photoCounts = this.sessionData.photo_counts || {};
            const photosByRoom = this.sessionData.photos_by_room || {};

            // Calculate total photos uploaded
            let totalPhotos = 0;
            rooms.forEach(r => { totalPhotos += (photoCounts[r.id] || 0); });

            // Phase 1: Show mandatory room photos first (skip prompt)
            // Phase 2: After clicking "Finish Photos", show the extras prompt
            if (this._showPhotoExtrasPrompt) {
                // Show the "any extras?" prompt after mandatory photos are done
                return `
                    <div class="space-y-6">
                        <!-- Go Back to Photos -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4">
                            <button type="button"
                                    @click="
                                        const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                        if (rendererEl && rendererEl._x_dataStack) {
                                            rendererEl._x_dataStack[0]._showPhotoExtrasPrompt = false;
                                            rendererEl._x_dataStack[0]._showPhotoUpload = true;
                                            rendererEl._x_dataStack[0].renderChecklist();
                                        }
                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                    "
                                    class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Go Back to Photos
                            </button>
                        </div>

                        <!-- Photo Extras Prompt Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-8 text-center">
                            <div class="flex justify-center mb-4">
                                <div class="w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">Do you have any photos to add before the report is closed?</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">${totalPhotos > 0 ? totalPhotos + ' photo' + (totalPhotos !== 1 ? 's' : '') + ' uploaded so far.' : 'No photos uploaded yet.'}</p>
                            <div class="flex items-center justify-center gap-3">
                                <button type="button"
                                        @click="
                                            const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                            if (rendererEl && rendererEl._x_dataStack) {
                                                rendererEl._x_dataStack[0]._showPhotoExtrasPrompt = false;
                                                rendererEl._x_dataStack[0]._showPhotoUpload = true;
                                                rendererEl._x_dataStack[0].renderChecklist();
                                            }
                                        "
                                        class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold cursor-pointer">
                                    Yes, Add Photos
                                </button>
                                <button type="button"
                                        @click="window.checklistHandler.saveProgress()"
                                        class="px-6 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors font-semibold cursor-pointer">
                                    No, Continue
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Default: always show mandatory room photo uploads first
            if (!this._showPhotoUpload) {
                this._showPhotoUpload = true;
            }

            // --- Full photo upload UI below (shown after clicking "Yes, Add Photos") ---

            // Find first room without enough photos (minimum 2)
            let currentRoomIndex = 0;
            if (this._photoRoomIndex === undefined || this._photoRoomIndex === null) {
                this._photoRoomIndex = parseInt(localStorage.getItem(`session_${this.sessionId}_photo_room`) || '0');
            }
            currentRoomIndex = this._photoRoomIndex;
            if (currentRoomIndex >= rooms.length) currentRoomIndex = rooms.length - 1;

            const room = rooms[currentRoomIndex];
            if (!room) {
                return `
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-8 text-center">
                        <p class="text-gray-500 mb-6">No rooms to photograph.</p>
                        <button type="button" @click="window.checklistHandler.saveProgress()" class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-semibold">
                            Continue to Finish
                        </button>
                    </div>
                `;
            }

            const photoCount = photoCounts[room.id] || 0;
            const minPhotos = this.getRoomMinPhotos(room);
            const photos = photosByRoom[room.id] || [];



            // Progress indicator for all rooms
            const progressDots = rooms.map((r, i) => {
                const pc = photoCounts[r.id] || 0;
                const rMin = this.getRoomMinPhotos(r);
                const isActive = i === currentRoomIndex;
                const isDone = pc >= rMin;
                return `<div class="flex flex-col items-center gap-1 cursor-pointer"
                        @click="
                            const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                            if (rendererEl && rendererEl._x_dataStack) {
                                rendererEl._x_dataStack[0]._photoRoomIndex = ${i};
                                localStorage.setItem('session_' + rendererEl._x_dataStack[0].sessionId + '_photo_room', ${i});
                                rendererEl._x_dataStack[0].refresh();
                            }
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        ">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-all
                        ${isActive ? 'border-blue-600 bg-blue-600 text-white' : isDone ? 'border-green-500 bg-green-500 text-white' : 'border-gray-300 dark:border-gray-600 text-gray-400'}"
                    >${i + 1}</div>
                    <span class="text-[10px] text-gray-500 dark:text-gray-400 truncate max-w-[60px] text-center">${r.name}</span>
                </div>`;
            }).join('');

            const deleteUrlConfig = this.photoDeleteUrl ? `, { deleteUrl: '${this.photoDeleteUrl}' }` : '';

            return `
                <div class="space-y-6">
                    <!-- Back Button -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4">
                        <button type="button"
                                @click="
                                    const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                    if (rendererEl && rendererEl._x_dataStack) {
                                        rendererEl._x_dataStack[0].navigateBack('photos', $event);
                                    }
                                "
                                class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Go Back
                        </button>
                    </div>

                    <!-- Room Progress Dots -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">Room Photos</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Tap a room number below to jump to that room's photos</p>
                        <div class="flex items-center justify-center gap-3 flex-wrap">
                            ${progressDots}
                        </div>
                    </div>

                    <!-- Current Room Photo Upload -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 sm:p-5" data-room-photos data-room-id="${room.id}">
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100">${room.name}</h3>
                                <span class="text-xs font-bold ${photoCount < minPhotos ? 'text-amber-500' : 'text-gray-500 dark:text-gray-400'}" data-photo-count>${photoCount} / ${minPhotos} Photos Req.</span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Room ${currentRoomIndex + 1} of ${rooms.length} — Take photos of this room</p>
                        </div>

                        <div x-data="photoUploader(${room.id})" class="mb-6">
                            <form method="post" enctype="multipart/form-data"
                                  action="/sessions/${this.sessionId}/rooms/${room.id}/photos"
                                  data-checklist-photo-form
                                  data-room-id="${room.id}"
                                  @submit.prevent.stop="handleSubmit($event)">

                                <div class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-4 sm:p-6 mb-4
                                           border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50
                                           hover:border-blue-400 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-colors"
                                     @dragover.prevent="hover = true"
                                     @dragleave.prevent="hover = false"
                                     @drop.prevent="handleDrop($event)"
                                     :class="hover ? 'border-blue-400 bg-blue-50/50 dark:bg-blue-900/10' : ''">
                                    <svg class="h-8 w-8 sm:h-10 sm:w-10 text-gray-400 dark:text-gray-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 text-center px-2">
                                        <span class="text-blue-600 dark:text-blue-400 font-medium">Tap to take a photo</span>
                                    </p>
                                    <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-500 mt-1">Take photos — click Upload when ready</p>

                                    <!-- The file input visually covers the entire bounding box securely for mobile tapping -->
                                    <input x-ref="fileInput"
                                           type="file"
                                           name="photos[]"
                                           accept="image/*"
                                           ${window.__userCanUpload ? '' : 'capture="environment"'}
                                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                           @change="handleFiles($event)" />
                                </div>

                                <!-- Upload Button Moved Above Previews -->
                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 mb-4" x-show="previews.length > 0" x-cloak>
                                    <button type="submit"
                                            :disabled="previews.length === 0 || uploading"
                                            class="w-full sm:flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed font-medium text-sm shadow-sm">
                                        <span x-show="!uploading">Upload <span x-text="previews.length"></span> Photo<span x-show="previews.length !== 1">s</span></span>
                                        <span x-show="uploading" class="flex items-center justify-center gap-2">
                                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Uploading...
                                        </span>
                                    </button>
                                </div>
                                
                                <div x-show="uploading && uploadProgress > 0" x-cloak class="mb-4">
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="'width: ' + uploadProgress + '%'"></div>
                                    </div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 text-center" x-text="uploadProgress + '%'"></p>
                                </div>

                                <div x-show="previews.length > 0" x-cloak class="mb-4">
                                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 sm:gap-3">
                                        <template x-for="(preview, index) in previews" :key="index">
                                            <div class="relative group rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                                <img :src="preview.url" class="w-full aspect-square object-cover" :alt="'Preview ' + (index + 1)" loading="lazy" />
                                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                    <button type="button" @click.stop="removePreview(index)" class="px-2 py-1 bg-red-600 text-white rounded-lg hover:bg-red-700 text-xs font-medium">Remove</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </form>
                        </div>

                        ${photos.length > 0 ? `
                            <div data-photo-gallery class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 sm:gap-3">
                                ${photos.map(photo => {
                    const photoUrl = photo.url || '';
                    const timeZone = this.sessionData?.property?.timezone || 'America/New_York';
                    const propName = this.sessionData?.property?.name || '';
                    const rawTimeStr = photo.captured_at ? new Date(photo.captured_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', timeZone }) : '';
                    const timeStr = propName && rawTimeStr ? `${propName} - Captured: ${rawTimeStr}` : (rawTimeStr ? `Captured: ${rawTimeStr}` : '');
                    return `
                        <div class="relative group"
                             x-data="photoDeleteHandler(${photo.id}, '${this.sessionId}'${deleteUrlConfig})"
                             data-photo-id="${photo.id}">
                            <button type="button" @click="fullscreen = true" class="w-full">
                                <img src="${photoUrl}" alt="Photo" width="300" height="300" class="aspect-square w-full object-cover rounded-xl border transition hover:opacity-90" loading="lazy" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27300%27 height=%27300%27 viewBox=%270 0 300 300%27%3E%3Crect width=%27100%25%27 height=%27100%25%27 fill=%27%23f1f5f9%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 dominant-baseline=%27middle%27 text-anchor=%27middle%27 font-family=%27sans-serif%27 font-size=%2714%27 fill=%27%2364748b%27%3EFailed to Load%3C/text%3E%3C/svg%3E';" />
                                ${timeStr ? `<span class="absolute bottom-1 right-1 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">${timeStr}</span>` : ''}
                            </button>
                            <button type="button" @click.stop="handleDeletePhoto()" :disabled="deleting" class="absolute top-1 right-1 p-1 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-700" title="Delete photo">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                            <div x-show="fullscreen" x-cloak @click.self="fullscreen = false" @keydown.escape.window="fullscreen = false" class="fixed inset-0 z-50 bg-black/95 flex items-center justify-center p-2">
                                <img src="${photoUrl}" class="max-h-full max-w-full object-contain rounded-lg" alt="Fullscreen photo" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27400%27 height=%27300%27 viewBox=%270 0 400 300%27%3E%3Crect width=%27100%25%27 height=%27100%25%27 fill=%27%23334155%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 dominant-baseline=%27middle%27 text-anchor=%27middle%27 font-family=%27sans-serif%27 font-size=%2716%27 fill=%2794a3b8%27%3EFailed to Load%3C/text%3E%3C/svg%3E';" />
                                <button type="button" @click="fullscreen = false" class="absolute top-2 right-2 text-white text-2xl hover:text-gray-300 bg-black/50 rounded-full w-8 h-8 flex items-center justify-center">×</button>
                            </div>
                        </div>
                    `;
                }).join('')}
                            </div>
                        ` : `
                            <p class="text-center text-gray-500 dark:text-gray-400 py-4">No photos uploaded yet for this room.</p>
                        `}
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4">
                        <div class="flex items-center justify-between gap-3">
                            <button type="button"
                                    ${currentRoomIndex === 0 ? 'disabled' : ''}
                                    @click="
                                        const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                        if (rendererEl && rendererEl._x_dataStack) {
                                            const r = rendererEl._x_dataStack[0];
                                            r._photoRoomIndex = ${currentRoomIndex - 1};
                                            localStorage.setItem('session_' + r.sessionId + '_photo_room', r._photoRoomIndex);
                                            r.refresh();
                                        }
                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                    "
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                ← Previous Room
                            </button>

                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">${currentRoomIndex + 1} / ${rooms.length}</span>

                            ${currentRoomIndex < rooms.length - 1 ? `
                                <button type="button"
                                        ${photoCount < minPhotos ? 'disabled' : ''}
                                        @click="
                                            const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                            if (rendererEl && rendererEl._x_dataStack) {
                                                const r = rendererEl._x_dataStack[0];
                                                r._photoRoomIndex = ${currentRoomIndex + 1};
                                                localStorage.setItem('session_' + r.sessionId + '_photo_room', r._photoRoomIndex);
                                                r.refresh();
                                            }
                                            window.scrollTo({ top: 0, behavior: 'smooth' });
                                        "
                                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors ${photoCount < minPhotos ? 'opacity-50 cursor-not-allowed' : ''}">
                                    Next Room →
                                </button>
                            ` : `
                                <button type="button"
                                        ${photoCount < minPhotos ? 'disabled' : ''}
                                        @click="
                                            const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                            if (rendererEl && rendererEl._x_dataStack) {
                                                rendererEl._x_dataStack[0]._showPhotoUpload = false;
                                                rendererEl._x_dataStack[0]._showPhotoExtrasPrompt = true;
                                                rendererEl._x_dataStack[0].renderChecklist();
                                            }
                                            window.scrollTo({ top: 0, behavior: 'smooth' });
                                        "
                                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors ${photoCount < minPhotos ? 'opacity-50 cursor-not-allowed' : ''}">
                                    Finish Photos →
                                </button>
                            `}
                        </div>
                        ${photoCount < minPhotos ? `
                            <div class="mt-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                                <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">⚠️ Please upload at least ${minPhotos} photos of this room before continuing.</p>
                            </div>
                        ` : ''}

                    </div>
                </div>
            `;
        },



        renderPhotos() {
            this.updateStepBtnVisibility(false);
            
            // Ensure rooms is an array
            let rooms = this.sessionData.rooms || [];
            if (!Array.isArray(rooms)) {
                rooms = Object.values(rooms);
            }
            const photoCounts = this.sessionData.photo_counts || {};
            const photosByRoom = this.sessionData.photos_by_room || {};


            let pendingPhotos = [];
            rooms.forEach(room => {
                const count = photoCounts[room.id] || 0;
                const min = this.getRoomMinPhotos(room);
                if (count < min) {
                    pendingPhotos.push({ roomName: room.name, remaining: min - count, min: min });
                }
            });
            const hasPendingPhotos = pendingPhotos.length > 0;
            const canSubmit = !hasPendingPhotos;

            return `
                <div class="space-y-6">
                    <!-- Go Back Button -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4">
                        <button type="button"
                                @click="
                                    const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                    if (rendererEl && rendererEl._x_dataStack) {
                                        rendererEl._x_dataStack[0].navigateBack('photos', $event);
                                    }
                                "
                                class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Go Back
                        </button>
                    </div>

                    ${rooms.map(room => {
                const photoCount = photoCounts[room.id] || 0;
                const photos = photosByRoom[room.id] || [];

                return `
                            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 sm:p-5" data-room-photos data-room-id="${room.id}">
                                <div class="mb-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100">${room.name}</h3>
                                        <div class="text-right">
                                            <span class="text-xs font-bold ${photoCount < this.getRoomMinPhotos(room) ? 'text-amber-500' : 'text-gray-500 dark:text-gray-400'}" data-photo-count>${photoCount} / ${this.getRoomMinPhotos(room)} Photos Req.</span>
                                        </div>
                                    </div>
                                </div>

                                <div x-data="photoUploader(${room.id})" class="mb-6">
                                    <form method="post" enctype="multipart/form-data"
                                          action="/sessions/${this.sessionId}/rooms/${room.id}/photos"
                                          data-checklist-photo-form
                                          data-room-id="${room.id}"
                                          @submit.prevent.stop="handleSubmit($event)">

                                        <div class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-4 sm:p-6 mb-4
                                                   border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50
                                                   hover:border-blue-400 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-colors"
                                             @dragover.prevent="hover = true"
                                             @dragleave.prevent="hover = false"
                                             @drop.prevent="handleDrop($event)"
                                             :class="hover ? 'border-blue-400 bg-blue-50/50 dark:bg-blue-900/10' : ''">
                                            
                                            <!-- Absolute invisible file input to receive physical mobile taps directly -->
                                            <input x-ref="fileInput"
                                                   type="file"
                                                   name="photos[]"
                                                   accept="image/*"
                                                   ${window.__userCanUpload ? '' : 'capture="environment"'}
                                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                                   @change="handleFiles($event)" />

                                            <svg class="h-8 w-8 sm:h-10 sm:w-10 text-gray-400 dark:text-gray-500 mb-2 relative z-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 text-center px-2 relative z-0">
                                                <span class="text-blue-600 dark:text-blue-400 font-medium cursor-pointer">Tap to take a photo</span>
                                            </p>
                                            <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-500 mt-1 relative z-0">PNG, JPG, JPEG up to 5MB</p>
                                        </div>

                                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 mb-4" x-show="previews.length > 0" x-cloak>
                                            <button type="submit"
                                                    :disabled="previews.length === 0 || uploading"
                                                    class="w-full sm:flex-1 px-4 py-2.5 sm:py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed font-medium text-sm sm:text-base shadow-sm">
                                                <span x-show="!uploading">Upload <span x-text="previews.length"></span> Photo<span x-show="previews.length !== 1">s</span></span>
                                                <span x-show="uploading" class="flex items-center justify-center gap-2">
                                                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Uploading...
                                                </span>
                                            </button>
                                        </div>

                                        <div x-show="uploading && uploadProgress > 0" x-cloak class="mb-4">
                                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                                     :style="'width: ' + uploadProgress + '%'"></div>
                                            </div>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 text-center" x-text="uploadProgress + '%'"></p>
                                        </div>

                                        <div x-show="previews.length > 0" x-cloak class="mb-4">
                                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 sm:gap-3">
                                                <template x-for="(preview, index) in previews" :key="index">
                                                    <div class="relative group rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800"
                                                         x-data="{ fullscreen: false }">
                                                        <button type="button" @click="fullscreen = true" class="w-full">
                                                            <img :src="preview.url"
                                                                 class="w-full aspect-square object-cover"
                                                                 :alt="'Preview ' + (index + 1)"
                                                                 :width="preview.width || 200"
                                                                 :height="preview.height || 200"
                                                                 loading="lazy" />
                                                        </button>
                                                        <div x-show="fullscreen"
                                                             x-cloak
                                                             @click.self="fullscreen = false"
                                                             @keydown.escape.window="fullscreen = false"
                                                             class="fixed inset-0 z-50 bg-black/95 flex items-center justify-center p-2 sm:p-4">
                                                            <img :src="preview.url"
                                                                 class="max-h-full max-w-full object-contain rounded-lg"
                                                                 :alt="'Preview ' + (index + 1)"
                                                                 :width="preview.width || 1920"
                                                                 :height="preview.height || 1080" />
                                                            <button type="button"
                                                                    @click="fullscreen = false"
                                                                    class="absolute top-2 right-2 sm:top-4 sm:right-4 text-white text-2xl sm:text-3xl hover:text-gray-300 transition-colors bg-black/50 rounded-full w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center">
                                                                ×
                                                            </button>
                                                        </div>
                                                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                            <button type="button"
                                                                    @click.stop="removePreview(index)"
                                                                    class="px-2 sm:px-3 py-1 sm:py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-xs sm:text-sm font-medium">
                                                                Remove
                                                            </button>
                                                        </div>
                                                        <div class="absolute top-1 right-1 sm:top-2 sm:right-2">
                                                            <span class="text-[10px] sm:text-xs px-1.5 sm:px-2 py-0.5 sm:py-1 rounded bg-black/60 text-white" x-text="formatFileSize(preview.size)"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                ${photos.length > 0 ? `
                                    <div data-photo-gallery class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 sm:gap-3">
                                        ${photos.map(photo => {
                    const photoUrl = photo.url || '';
                    const timeZone = this.sessionData?.property?.timezone || 'America/New_York';
                    const propName = this.sessionData?.property?.name || '';
                    const rawTimeStr = photo.captured_at ? new Date(photo.captured_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', timeZone }) : '';
                    const timeStr = propName && rawTimeStr ? `${propName} - Captured: ${rawTimeStr}` : (rawTimeStr ? `Captured: ${rawTimeStr}` : '');
                    const deleteUrlConfig = this.photoDeleteUrl ? `, { deleteUrl: '${this.photoDeleteUrl}' }` : '';
                    return `
                                                <div class="relative group"
                                                     x-data="photoDeleteHandler(${photo.id}, '${this.sessionId}'${deleteUrlConfig})"
                                                     data-photo-id="${photo.id}">
                                                    <button type="button" @click="fullscreen = true" class="w-full">
                                                        <img src="${photoUrl}"
                                                             alt="Photo"
                                                             width="300"
                                                             height="300"
                                                             class="aspect-square w-full object-cover rounded-xl border transition hover:opacity-90"
                                                             loading="lazy"
                                                             onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27300%27 height=%27300%27 viewBox=%270 0 300 300%27%3E%3Crect width=%27100%25%27 height=%27100%25%27 fill=%27%23f1f5f9%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 dominant-baseline=%27middle%27 text-anchor=%27middle%27 font-family=%27sans-serif%27 font-size=%2714%27 fill=%27%2364748b%27%3EFailed to Load%3C/text%3E%3C/svg%3E';" />
                                                        ${timeStr ? `
                                                            <span class="absolute bottom-1 right-1 text-[10px] sm:text-xs px-1.5 sm:px-2 py-0.5 sm:py-1 rounded bg-black/60 text-white">
                                                                ${timeStr}
                                                            </span>
                                                        ` : ''}
                                                    </button>
                                                    <button type="button"
                                                            @click.stop="handleDeletePhoto()"
                                                            :disabled="deleting"
                                                            class="absolute top-1 right-1 sm:top-2 sm:right-2 p-1 sm:p-1.5 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-700 disabled:opacity-50"
                                                            title="Delete photo">
                                                        <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                    <div x-show="fullscreen"
                                                         x-cloak
                                                         @click.self="fullscreen = false"
                                                         @keydown.escape.window="fullscreen = false"
                                                         class="fixed inset-0 z-50 bg-black/95 flex items-center justify-center p-2 sm:p-4">
                                                        <img src="${photoUrl}"
                                                             class="max-h-full max-w-full object-contain rounded-lg"
                                                             alt="Fullscreen photo"
                                                             width="1920"
                                                             height="1080"
                                                             onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27400%27 height=%27300%27 viewBox=%270 0 400 300%27%3E%3Crect width=%27100%25%27 height=%27100%25%27 fill=%27%23334155%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 dominant-baseline=%27middle%27 text-anchor=%27middle%27 font-family=%27sans-serif%27 font-size=%2716%27 fill=%2794a3b8%27%3EFailed to Load%3C/text%3E%3C/svg%3E';" />
                                                        <button type="button"
                                                                @click="fullscreen = false"
                                                                class="absolute top-2 right-2 sm:top-4 sm:right-4 text-white text-2xl sm:text-3xl hover:text-gray-300 transition-colors bg-black/50 rounded-full w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center">
                                                            ×
                                                        </button>
                                                    </div>
                                                </div>
                                            `;
                }).join('')}
                                    </div>
                                ` : `
                                    <p class="text-center text-gray-500 dark:text-gray-400 py-8">No photos uploaded yet.</p>
                                `}
                            </div>
                        `;
            }).join('')}

                    <!-- Submit Section with Validation -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 text-center">

                        ${hasPendingPhotos ? `
                            <div class="mb-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg text-left">
                                <div class="flex items-start gap-2">
                                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    <div>
                                        <p class="font-semibold text-amber-800 dark:text-amber-200 text-sm">More photos required</p>
                                        <ul class="mt-2 space-y-1">
                                            ${pendingPhotos.map(p => `
                                                <li class="text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1.5">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 flex-shrink-0"></span>
                                                    <span class="font-medium">${p.roomName}</span> requires ${p.remaining} more photo(s) (minimum ${p.min}).
                                                </li>
                                            `).join('')}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                        <button type="button"
                                ${!canSubmit ? 'disabled' : ''}
                                @click="
                                    $el.disabled = true;
                                    $el.textContent = 'Submitting...';
                                    window.api.post('/sessions/${this.sessionId}/complete', {})
                                    .then(res => {
                                        if (res.success && res.redirect) { window.location.href = res.redirect; }
                                        else if (res.success) { window.location.reload(); }
                                        else { window.checklistHandler?.showError(res.message || 'Submission failed'); $el.disabled = false; $el.textContent = 'Submit Checklist'; }
                                    })
                                    .catch(err => {
                                        const msg = err.response?.data?.message || err.message || 'Submission failed';
                                        window.checklistHandler?.showError(msg);
                                        $el.disabled = false;
                                        $el.textContent = 'Submit Checklist';
                                    });
                                "
                                class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium ${!canSubmit ? 'opacity-50 cursor-not-allowed' : ''}">
                            Submit Checklist
                        </button>
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                            ${!canSubmit ? 'Please ensure all photo requirements are met.' : 'Please ensure all photo requirements are met. Timestamp overlay is automatic.'}
                        </p>
                    </div>
                </div>
            `;
        },

        renderSummary(showButton = false) {
            const isCompleted = this.sessionData.session?.status === 'completed';
            
            // Validate photos
            let rooms = this.sessionData.rooms || [];
            if (!Array.isArray(rooms)) {
                // If it's an object, convert to array
                rooms = Object.values(rooms);
            }
            const photoCounts = this.sessionData.photo_counts || {};
            let pendingPhotos = [];
            const skippedRooms = this.sessionData.session?.skipped_rooms || [];
            rooms.forEach(r => {
                const count = photoCounts[r.id] || 0;
                const min = this.getRoomMinPhotos(r);
                if (count < min) {
                    pendingPhotos.push({ roomName: r.name, remaining: min - count, min: min });
                }
            });
            const hasPendingPhotos = pendingPhotos.length > 0;

            let html = `
                <div class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-8"
                         x-data="{ sessionSubmitted: ${isCompleted ? 'true' : 'false'} }">

                        <!-- Header -->
                        <div class="text-center py-8">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">${isCompleted ? 'Session Completed!' : 'All Stages Complete!'}</h2>
                            <p class="text-gray-600 dark:text-gray-400 max-w-md mx-auto mb-8">
                                ${isCompleted 
                                    ? 'This session has been completed. You can still add notes or go back to add more photos.'
                                    : 'You have completed all stages of this cleaning session. Please add any notes and submit when ready.'}
                            </p>
                            ${isCompleted && this.sessionData.is_admin ? `
                            <a href="/sessions/${this.sessionId}?edit_report=1"
                               class="inline-flex items-center gap-2 px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors font-semibold shadow-md mb-4">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                ✏️ Edit Report
                            </a>
                            ` : ''}
                        </div>

                        ${hasPendingPhotos && !isCompleted ? `
                            <div class="max-w-2xl mx-auto mb-8 text-left">
                                <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                        </svg>
                                        <div>
                                            <p class="font-semibold text-amber-800 dark:text-amber-200 text-sm">More photos required</p>
                                            <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">Please go back to the photos stage to upload them before submitting.</p>
                                            <ul class="mt-2 space-y-1">
                                                ${pendingPhotos.map(p => `
                                                    <li class="text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1.5">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 flex-shrink-0"></span>
                                                        <span class="font-medium">${p.roomName}</span> requires ${p.remaining} more photo(s) (minimum ${p.min}).
                                                    </li>
                                                `).join('')}
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ` : ''}

                        <!-- Notes Section (always visible) -->
                        <div class="max-w-2xl mx-auto mb-8 text-left">
                            <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-6 bg-gray-50 dark:bg-gray-800/50">
                                <div class="flex items-start gap-3 mb-4">
                                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Session Notes</h3>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Add any notes, feedback, or issues you want to report.
                                        </p>
                                    </div>
                                </div>

                                <div x-data="{ note: '${(this.sessionData.session.property_notes || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, '\\n').replace(/\r/g, '')}' }" class="space-y-4">
                                    <textarea 
                                        x-model="note"
                                        rows="4"
                                        placeholder="Enter your notes here..."
                                        class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    ></textarea>

                                    <div class="flex items-center justify-end gap-3 mt-4">
                                        ${isCompleted ? `
                                        <!-- Already completed: just save notes -->
                                        <button 
                                            type="button"
                                            @click="
                                                if (!note.trim()) { window.checklistHandler?.showError('Please enter a note before saving.'); return; }
                                                $el.disabled = true;
                                                $el.textContent = 'Saving...';
                                                window.api.post('/sessions/${this.sessionId}/save-note', { note: note })
                                                .then(res => {
                                                    if (res.success) {
                                                        window.checklistHandler?.showSuccess('Note saved successfully.');
                                                        $el.textContent = 'Saved!';
                                                        setTimeout(() => { $el.textContent = 'Save Note'; $el.disabled = false; }, 2000);
                                                    } else {
                                                        throw new Error(res.message || 'Failed to save note');
                                                    }
                                                })
                                                .catch(err => {
                                                    window.checklistHandler?.showError(err.message || 'Failed to save note');
                                                    $el.disabled = false;
                                                    $el.textContent = 'Save Note';
                                                });
                                            "
                                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold cursor-pointer w-full sm:w-auto"
                                        >
                                            Save Note
                                        </button>
                                        ` : `
                                        <!-- Not yet completed: save note + complete session -->
                                        <button 
                                            type="button"
                                            ${hasPendingPhotos ? 'disabled' : ''}
                                            @click="
                                                if (sessionSubmitted) return;
                                                $el.disabled = true;
                                                $el.textContent = note.trim() ? 'Saving & Submitting...' : 'Submitting...';
                                                
                                                if (!note.trim()) {
                                                    window.api.post('/sessions/${this.sessionId}/complete', {})
                                                    .then(res => {
                                                        if (res.success && res.redirect) { window.location.replace(res.redirect); }
                                                        else if (res.success) { 
                                                            sessionSubmitted = true; 
                                                            document.querySelectorAll('[data-status-badge]').forEach(el => {
                                                                el.innerHTML = '<span class=\\'inline-flex items-center justify-center rounded-full bg-green-100 px-2.5 py-0.5 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400\\'><svg class=\\'-ml-1 mr-1.5 h-4 w-4\\' fill=\\'currentColor\\' viewBox=\\'0 0 20 20\\'><path fill-rule=\\'evenodd\\' d=\\'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z\\' clip-rule=\\'evenodd\\' /></svg>Completed</span>';
                                                            });
                                                        }
                                                        else { window.checklistHandler?.showError(res.message || 'Submission failed'); $el.disabled = false; $el.textContent = 'Submit Checklist'; }
                                                    })
                                                    .catch(err => {
                                                        window.checklistHandler?.showError(err.response?.data?.message || err.message || 'Submission failed');
                                                        $el.disabled = false;
                                                        $el.textContent = 'Submit Checklist';
                                                    });
                                                    return;
                                                }

                                                window.api.post('/sessions/${this.sessionId}/save-note', { note: note })
                                                .then(res => {
                                                    if (res.success) {
                                                        return window.api.post('/sessions/${this.sessionId}/complete', {});
                                                    }
                                                    throw new Error(res.message || 'Failed to save note');
                                                })
                                                .then(res => {
                                                    if (res.success && res.redirect) { window.location.replace(res.redirect); }
                                                    else if (res.success) { 
                                                        sessionSubmitted = true;
                                                        document.querySelectorAll('[data-status-badge]').forEach(el => {
                                                            el.innerHTML = '<span class=\\'inline-flex items-center justify-center rounded-full bg-green-100 px-2.5 py-0.5 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400\\'><svg class=\\'-ml-1 mr-1.5 h-4 w-4\\' fill=\\'currentColor\\' viewBox=\\'0 0 20 20\\'><path fill-rule=\\'evenodd\\' d=\\'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z\\' clip-rule=\\'evenodd\\' /></svg>Completed</span>';
                                                        });
                                                    }
                                                    else { throw new Error(res.message || 'Submission failed'); }
                                                })
                                                .catch(err => {
                                                    window.checklistHandler?.showError(err.message || 'Submission failed');
                                                    $el.disabled = false;
                                                    $el.textContent = 'Submit Checklist';
                                                });
                                            "
                                            class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-semibold cursor-pointer w-full sm:w-auto shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <span x-text="note.trim() ? 'Save & Submit Session' : 'Submit Session'"></span>
                                        </button>
                                        `}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add More Pictures Prompt -->
                        <div class="max-w-2xl mx-auto mb-6">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/10 text-left">
                                <div>
                                    <h4 class="text-lg font-semibold text-blue-900 dark:text-blue-100">Would you like to add more pictures?</h4>
                                    <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">You can go back to the photos stage to upload additional images.</p>
                                </div>
                                <button type="button"
                                    x-data="{ navigating: false }"
                                    @click="
                                        if (navigating) return;
                                        navigating = true;
                                        $el.textContent = 'Navigating...';
                                        window.api.post('/sessions/${this.sessionId}/go-back-stage${this.editSuffix}', { current_stage: 'summary', ${this.isEditReport ? "edit_report: '1'" : ''} })
                                        .then(res => {
                                            if (res.success && res.data) {
                                                const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                                if (rendererEl && rendererEl._x_dataStack) {
                                                    rendererEl._x_dataStack[0].sessionData = res.data;
                                                    rendererEl._x_dataStack[0].renderChecklist();
                                                }
                                                window.scrollTo({ top: 0, behavior: 'smooth' });
                                            } else {
                                                window.location.reload();
                                            }
                                        })
                                        .catch(() => { window.location.reload(); });
                                    "
                                    class="px-5 py-2.5 bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 border border-blue-300 dark:border-blue-700 rounded-lg hover:bg-blue-50 dark:hover:bg-gray-700 font-medium transition-colors whitespace-nowrap">
                                    Yes, add more pictures
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            `;

            html += `</div>`;

            return html;
        },

        renderTaskItem(task, room, disabled = false) {
            const isPropertyTask = room === null;
            const toggleUrl = isPropertyTask
                ? `/sessions/${this.sessionId}/tasks/${task.id}/toggle`
                : `/sessions/${this.sessionId}/rooms/${room.id}/tasks/${task.id}/toggle`;
            const noteUrl = isPropertyTask
                ? `/sessions/${this.sessionId}/tasks/${task.id}/note`
                : `/sessions/${this.sessionId}/rooms/${room.id}/tasks/${task.id}/note`;
            const photoUrl = isPropertyTask
                ? `/sessions/${this.sessionId}/tasks/${task.id}/photo`
                : `/sessions/${this.sessionId}/rooms/${room.id}/tasks/${task.id}/photo`;

            const checked = task.checklist_item?.checked || false;
            const hasInstructions = task.instructions && task.instructions.trim().length > 0;
            const hasMedia = task.media && task.media.length > 0;
            const showDetails = hasInstructions || hasMedia;
            const isViewOnly = this.sessionData.is_view_only || false;
            const taskDisabled = disabled || isViewOnly;

            // Check if user has added a note
            const userNote = task.checklist_item?.note || '';
            const hasUserNote = userNote.trim().length > 0;

            // Clean up property wide labels for occasional tasks
            const displayName = task.name.replace(/\[Property Wide\]\\s*-?\\s*|\\(Property Wide\\)\\s*-?\\s*|Property\\sWide\\s*-?\\s*/gi, '').trim() || task.name;

            return `
                <div data-task-item data-task-id="${task.id}" data-room-id="${room ? room.id : 'property'}"
                     class="p-3 transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/30 ${checked ? 'bg-gray-50/50 dark:bg-gray-900/20' : ''}"
                     x-data="{
                         detailsOpen: false,
                         noteSaving: false,
                         photoUploading: false,
                         taskName: ${JSON.stringify(displayName).replace(/"/g, '&quot;')},
                         noteValue: ${JSON.stringify(userNote).replace(/"/g, '&quot;')},
                         itemPhotos: ${JSON.stringify(task.checklist_item?.photos || []).replace(/"/g, '&quot;')},
                         instructionViewed: ${task.checklist_item?.instruction_viewed ? 'true' : 'false'},
                         isFamiliar: ${task.is_familiar ? 'true' : 'false'},
                         viewsCompleted: ${task.views_completed !== undefined ? task.views_completed : 0},
                         requiredViews: 0,
                         requiresViewing: ${showDetails || task.type === 'instructions' ? 'true' : 'false'},
                         hasContentToView: ${showDetails || task.type === 'instructions' ? 'true' : 'false'},
                         taskType: '${task.type}',
                         get photoRequirement() {
                             if (this.taskType === 'instructions') return 0;
                             if (this.taskType === 'verify') return 1;
                             if (this.taskType === 'inventory') return 1;
                             return 0;
                         },
                         get isMandatoryPhoto() {
                             return this.photoRequirement > 0;
                         },
                         get canToggle() {
                              if (this.isMandatoryPhoto && this.itemPhotos.length < this.photoRequirement) return false;
                              if (this.requiresViewing && !this.instructionViewed) return false;
                              return true;
                          },
                         handleClick(event, el) {
                              if (!this.canToggle) {
                                  if (this.requiresViewing && !this.instructionViewed) {
                                      window.checklistHandler?.showError('You must view the instructions before completing this task.');
                                      return;
                                  }
                                  if (this.itemPhotos.length < this.photoRequirement) {
                                      if (this.taskType === 'inventory') {
                                          window.dispatchEvent(new CustomEvent('open-inventory-modal', {
                                              detail: {
                                                  taskId: ${task.id},
                                                  roomId: '${room ? room.id : 'property'}',
                                                  taskName: this.taskName,
                                                  photoUrl: '${photoUrl}',
                                                  toggleUrl: '${toggleUrl}'
                                              }
                                          }));
                                      } else {
                                          window.dispatchEvent(new CustomEvent('open-photo-modal', { detail: { photoUrl: '${photoUrl}', taskId: ${task.id}, roomId: '${room ? room.id : 'property'}', taskType: this.taskType } }));
                                      }
                                      return;
                                  }
                              }
                              if (this.taskType === 'inventory' && !this.checked) {
                                  window.dispatchEvent(new CustomEvent('open-inventory-modal', {
                                      detail: {
                                          taskId: ${task.id},
                                          roomId: '${room ? room.id : 'property'}',
                                          taskName: this.taskName,
                                          photoUrl: '${photoUrl}',
                                          toggleUrl: '${toggleUrl}'
                                      }
                                  }));
                                  return;
                              }
                              window.checklistHandler.handleToggle(event, el);
                          },
                         openUploadModal() {
                             if (this.taskType === 'inventory' && !this.checked) {
                                 window.dispatchEvent(new CustomEvent('open-inventory-modal', {
                                     detail: { taskId: ${task.id}, roomId: '${room ? room.id : 'property'}', taskName: this.taskName, photoUrl: '${photoUrl}', toggleUrl: '${toggleUrl}', itemPhotos: this.itemPhotos, noteValue: this.noteValue }
                                 }));
                             } else {
                                 window.dispatchEvent(new CustomEvent('open-photo-modal', { detail: { photoUrl: '${photoUrl}', toggleUrl: '${toggleUrl}', taskId: ${task.id}, roomId: '${room ? room.id : 'property'}', taskType: this.taskType, itemPhotos: this.itemPhotos, noteValue: this.noteValue } }));
                             }
                         }
                     }"
                     @note-saved.window="if ($event.detail.taskId === ${task.id}) { noteValue = $event.detail.note; }"
                     @photo-uploaded.window="if ($event.detail.taskId === ${task.id}) { itemPhotos.push($event.detail.photo); }">
                    <div class="flex items-start gap-3">
                        <template x-if="taskType !== 'instructions'">
                            <div class="flex-shrink-0 pt-0.5">
                                <button type="button"
                                        data-checklist-toggle
                                        @click="handleClick($event, $el)"
                                        data-toggle-url="${toggleUrl}"
                                        data-checked="${checked}"
                                        :disabled="${taskDisabled} || noteSaving || photoUploading"
                                        class="relative w-6 h-6 rounded-md border-2 flex items-center justify-center transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 ${taskDisabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:scale-105 shadow-sm'} ${checked ? 'bg-green-600 border-green-600 text-white' : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600'}"
                                        :class="{ 'opacity-50 grayscale cursor-not-allowed': (isMandatoryPhoto && itemPhotos.length === 0 && !${checked}) }"
                                        aria-label="${checked ? 'Mark as incomplete' : 'Mark as complete'}">
                                    ${checked ? `
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                ` : `
                                    <template x-if="isMandatoryPhoto && itemPhotos.length === 0">
                                        <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                        </svg>
                                    </template>
                                `}
                                </button>
                            </div>
                        </template>

                        <div class="flex-1 min-w-0">
                            <div class="flex flex-col gap-1">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <h3 data-task-name x-text="taskName" class="text-[15px] font-bold text-gray-900 dark:text-gray-100 transition-all ${checked ? 'text-gray-400 dark:text-gray-500 font-medium' : ''}">
                                        ${displayName}
                                    </h3>
                                    
                                    <template x-if="isMandatoryPhoto && itemPhotos.length < photoRequirement && !${checked}">
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-orange-600 uppercase tracking-tight">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                            </svg>
                                            <span>Photo Req</span>
                                        </span>
                                    </template>

                                    <template x-if="taskType === 'inventory' && ${task.checklist_item?.quantity ? 'true' : 'false'}">
                                        <span class="inline-flex items-center gap-1.5 text-[10px] font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded w-fit">
                                            <span>Qty: ${task.checklist_item?.quantity}</span>
                                        </span>
                                    </template>
                                </div>
                                
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 mt-0.5">
                                    <template x-if="hasContentToView">
                                        <button type="button"
                                                @click="window.dispatchEvent(new CustomEvent('open-instructions-modal', { detail: { taskId: ${task.id}, roomId: '${room ? room.id : 'property'}', taskName: taskName, instructions: ${JSON.stringify(task.instructions || '').replace(/"/g, '&quot;')}, media: ${JSON.stringify(task.media || []).replace(/"/g, '&quot;')} } }))"
                                                class="inline-flex items-center gap-1 text-[11px] font-medium tracking-tight transition-colors cursor-pointer focus:outline-none group text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-400"
                                                aria-label="View Instructions">
                                            <span class="flex items-center gap-0.5 group-hover:underline underline-offset-2">
                                                <span>Instructions</span>
                                                <template x-if="instructionViewed">
                                                    <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                                </template>
                                            </span>
                                        </button>
                                    </template>

                                    <!-- Compact Note/Photo Upload Actions -->
                                    <button type="button"
                                            @click="openUploadModal()"
                                            class="inline-flex items-center gap-1 text-[11px] font-medium transition-colors cursor-pointer focus:outline-none group"
                                            :class="itemPhotos.length > 0 || noteValue.length > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-400'">
                                        <template x-if="taskType === 'verify' || taskType === 'inventory'">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                            </svg>
                                        </template>
                                        <template x-if="taskType !== 'verify' && taskType !== 'inventory'">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </template>
                                        <span class="group-hover:underline underline-offset-2" x-text="(taskType === 'verify' || taskType === 'inventory') ? (itemPhotos.length > 0 ? 'Add more photos' : '+ Photo/Note') : '+ Note'"></span>
                                        <template x-if="itemPhotos.length > 0">
                                            <span class="bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 text-[9px] px-1 rounded-sm ml-0.5" x-text="itemPhotos.length"></span>
                                        </template>
                                        <template x-if="noteValue.length > 0">
                                            <svg class="w-3 h-3 ml-0.5 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path></svg>
                                        </template>
                                    </button>
                                    
                                    <template x-if="itemPhotos.length > 0 || noteValue.length > 0">
                                        <button type="button" @click="detailsOpen = !detailsOpen" class="inline-flex items-center gap-1 text-[11px] text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                                            <span x-text="detailsOpen ? 'Hide details' : 'View details'" class="underline underline-offset-2"></span>
                                        </button>
                                    </template>
                                </div>
                                <input type="hidden" data-note-input x-model="noteValue" />
                            </div>

                            <div x-show="detailsOpen" x-collapse x-cloak class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700/50">
                                ${hasInstructions ? `
                                    <div class="mb-3">
                                        <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1">Instructions</div>
                                        <div class="prose dark:prose-invert prose-sm max-w-none text-gray-600 dark:text-gray-400 leading-relaxed">
                                            ${this.formatInstructions(task.instructions)}
                                        </div>
                                    </div>
                                ` : ''}

                                ${hasMedia ? `
                                    <div class="mb-3">
                                        <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1">Examples</div>
                                        <div class="grid grid-cols-3 gap-2">
                                            ${task.media.map(media => `
                                                <div class="relative rounded overflow-hidden border border-gray-200 dark:border-gray-700 group bg-gray-100 dark:bg-gray-800">
                                                    ${media.type === 'image' ? `
                                                        <button type="button" @click="window.dispatchEvent(new CustomEvent('open-gallery', { detail: { src: '${media.url}' } }))" class="block w-full h-full">
                                                            <div class="aspect-video w-full relative bg-gray-200 dark:bg-gray-700 rounded overflow-hidden">
                                                                <img src="${media.thumbnail || media.url}" alt="${media.caption || 'Task media'}"
                                                                     class="w-full h-full object-cover transition-transform group-hover:scale-105"
                                                                     onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'400\\' height=\\'300\\' viewBox=\\'0 0 400 300\\'%3E%3Crect width=\\'100%25\\' height=\\'100%25\\' fill=\\'%23f1f5f9\\'/%3E%3Ctext x=\\'50%25\\' y=\\'50%25\\' dominant-baseline=\\'middle\\' text-anchor=\\'middle\\' font-family=\\'sans-serif\\' font-size=\\'14\\' fill=\\'%2364748b\\'%3EFailed to Load%3C/text%3E%3C/svg%3E'; this.parentElement.classList.add('bg-gray-200');"
                                                                     loading="lazy" />
                                                            </div>
                                                        </button>
                                                    ` : `
                                                        <video src="${media.url}" class="w-full h-16 object-cover" controls muted></video>
                                                    `}
                                                    ${media.caption ? `
                                                        <div class="absolute bottom-0 left-0 right-0 bg-black/50 p-1">
                                                            <p class="text-[9px] text-white truncate text-center">${this.escapeHtml(media.caption)}</p>
                                                        </div>
                                                    ` : ''}
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                ` : ''}

                                <template x-if="itemPhotos.length > 0">
                                    <div class="mb-2">
                                        <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1">Your Photos</div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <template x-for="photo in itemPhotos" :key="photo.id">
                                                <div class="relative rounded overflow-hidden border border-gray-200 dark:border-gray-700 group bg-gray-100 dark:bg-gray-800">
                                                    <button type="button" @click="window.dispatchEvent(new CustomEvent('open-gallery', { detail: { src: photo.url } }))" class="block w-full">
                                                        <img :src="photo.url" alt="Attached photo"
                                                             class="w-full h-16 object-cover transition-transform group-hover:scale-105"
                                                             loading="lazy"
                                                             onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27400%27 height=%27300%27 viewBox=%270 0 400 300%27%3E%3Crect width=%27100%25%27 height=%27100%25%27 fill=%27%23f1f5f9%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 dominant-baseline=%27middle%27 text-anchor=%27middle%27 font-family=%27sans-serif%27 font-size=%2714%27 fill=%27%2364748b%27%3EFailed to Load%3C/text%3E%3C/svg%3E';" />
                                                    </button>
                                                    <button type="button" 
                                                            @click.stop="
                                                                if(confirm('Are you sure you want to delete this photo?')) {
                                                                    itemPhotos = itemPhotos.filter(p => p.id !== photo.id);
                                                                    if (itemPhotos.length === 0) {
                                                                        const toggleBtn = $el.closest('[data-task-item]')?.querySelector('[data-checklist-toggle]');
                                                                        if (toggleBtn) {
                                                                            toggleBtn.dataset.checked = 'false';
                                                                            window.checklistHandler?.updateToggleUI(toggleBtn, false);
                                                                        }
                                                                    }
                                                                    const deleteUrl = '${photoUrl}/' + photo.id;
                                                                    window.api.delete(deleteUrl).then(res => {
                                                                        if(!res.success) alert(res.message || 'Failed to delete photo');
                                                                    }).catch(err => {
                                                                        console.error(err);
                                                                        alert('Error deleting photo');
                                                                    });
                                                                }
                                                            "
                                                            class="absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 hover:bg-red-700 shadow-md transform scale-90 opacity-80 hover:opacity-100 hover:scale-100 transition-all z-10"
                                                            title="Delete Photo">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                    <template x-if="photo.note">
                                                        <div class="absolute bottom-0 left-0 right-0 bg-black/50 p-1">
                                                            <p class="text-[9px] text-white truncate text-center" x-text="photo.note"></p>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },

        formatInstructions(instructions) {
            if (!instructions) return '';
            // Convert newlines to <br> and escape HTML
            return this.escapeHtml(instructions).replace(/\n/g, '<br>');
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        setupEventHandlers() {
            // Event handlers are set up by the checklist.js module
            // This is called after rendering to ensure new elements are handled
            if (window.checklistHandler) {
                window.checklistHandler.setupFormHandlers();
            }
        },

        // Public method to refresh data and re-render
        async refresh() {
            await this.loadSessionData();
        },

        // Check if a room is complete and unlock next room
        checkRoomCompletion() {
            if (!this.sessionData) return;

            // Re-calculate completion status
            let rooms = this.sessionData.rooms || [];
            if (!Array.isArray(rooms)) {
                rooms = Object.values(rooms);
            }
            rooms.forEach((room, index) => {
                // Ensure room.tasks is an array
                const roomTasksArray = Array.isArray(room.tasks) ? room.tasks : Object.values(room.tasks || {});
                const roomTasks = roomTasksArray.filter(t => room.room_tasks.includes(t.id) && t.type !== 'instructions');
                const checkedCount = roomTasks.filter(t => t.checklist_item?.checked).length;
                const totalCount = roomTasks.length;
                const isComplete = totalCount === 0 || checkedCount === totalCount;

                // If room just completed, refresh to unlock next room
                if (isComplete) {
                    // Small delay to ensure database is updated
                    setTimeout(() => {
                        this.refresh();
                    }, 500);
                }
            });
        },

        // --- Persistent Instructions Methods ---
        getActiveTaskAndRoom() {
            if (this.activeTaskId) {
                return { taskId: this.activeTaskId, roomId: this.activeRoomId };
            }

            const stage = this.sessionData?.stage;
            if (!stage) return { taskId: null, roomId: null };

            if (stage === 'pre_cleaning' || stage === 'during_cleaning' || stage === 'post_cleaning') {
                let tasks = this.sessionData.property_tasks?.[stage] || [];
                if (!Array.isArray(tasks)) tasks = Object.values(tasks);
                const firstIncomplete = tasks.find(t => t.type !== 'instructions' && !t.checklist_item?.checked);
                if (firstIncomplete) {
                    return { taskId: firstIncomplete.id, roomId: null };
                }
                if (tasks.length > 0) {
                    return { taskId: tasks[0].id, roomId: null };
                }
            } else if (stage === 'rooms' || stage === 'rooms_first_half' || stage === 'rooms_second_half') {
                let rooms = this.sessionData.rooms || [];
                if (!Array.isArray(rooms)) rooms = Object.values(rooms);
                
                for (const room of rooms) {
                    let tasksArr = room.tasks || [];
                    if (!Array.isArray(tasksArr)) tasksArr = Object.values(tasksArr);
                    const countableTasks = tasksArr.filter(t => t.type !== 'instructions');
                    const isComplete = countableTasks.every(t => t.checklist_item?.checked);
                    
                    if (!isComplete && countableTasks.length > 0) {
                        const firstIncomplete = countableTasks.find(t => !t.checklist_item?.checked);
                        if (firstIncomplete) {
                            return { taskId: firstIncomplete.id, roomId: room.id };
                        }
                    }
                }
                if (rooms.length > 0) {
                    const firstRoom = rooms[0];
                    let tasksArr = firstRoom.tasks || [];
                    if (!Array.isArray(tasksArr)) tasksArr = Object.values(tasksArr);
                    if (tasksArr.length > 0) {
                        return { taskId: tasksArr[0].id, roomId: firstRoom.id };
                    }
                }
            }

            return { taskId: null, roomId: null };
        },

        async loadInstructions() {
            const { taskId, roomId } = this.getActiveTaskAndRoom();
            
            this.instructionsLoading = true;
            this.instructionsError = null;
            
            let url = `/sessions/${this.sessionId}/instructions`;
            const params = [];
            if (taskId) params.push(`task_id=${taskId}`);
            if (roomId) params.push(`room_id=${roomId}`);
            if (params.length > 0) {
                url += `?${params.join('&')}`;
            }
            
            try {
                const response = await window.api.get(url);
                if (response.success && response.data) {
                    this.instructionsData = response.data;
                    
                    // Sync dropdowns state
                    if (this.instructionsData.task) {
                        this.activeTaskId = this.instructionsData.task.id;
                        this.activeRoomId = roomId;
                    }
                } else {
                    throw new Error(response.message || 'Failed to load instructions');
                }
            } catch (error) {
                console.error('Error loading instructions:', error);
                this.instructionsError = error.response?.data?.message || error.message || 'Could not load instructions';
            } finally {
                this.instructionsLoading = false;
            }
        },

        getTasksForSelector() {
            if (!this.sessionData) return [];
            
            if (this.activeRoomId === null) {
                const pre = this.sessionData.property_tasks?.pre_cleaning || [];
                const dur = this.sessionData.property_tasks?.during_cleaning || [];
                const post = this.sessionData.property_tasks?.post_cleaning || [];
                return [...pre, ...dur, ...post];
            } else {
                let rooms = this.sessionData.rooms || [];
                if (!Array.isArray(rooms)) rooms = Object.values(rooms);
                
                const selectedRoom = rooms.find(r => r.id === this.activeRoomId);
                if (selectedRoom) {
                    return selectedRoom.tasks || [];
                }
            }
            return [];
        },

        formatInstructionsText(text) {
            if (!text) return '';
            return this.escapeHtml(text).replace(/\n/g, '<br>');
        },
    };
}







