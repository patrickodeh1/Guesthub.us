            <!-- Global Modal Portal — lives OUTSIDE the margin-animated Page Wrapper so
                 position:fixed children are always relative to the viewport, never to a
                 CSS-composited layer created by the Page Wrapper's margin transition. -->
            <div x-data="globalModal()" x-cloak>

                <!-- Note Modal -->
                <div x-show="show && type === 'note'"
                     class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                     @click.self="close()"
                     @keydown.escape.window="close()">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md p-6">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Add Note</h3>

                        <!-- NEW: Preview existing data -->
                        <template x-if="(existingPhotos && existingPhotos.length > 0) || existingNote">
                            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-100 dark:border-gray-700">
                                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Previously Saved</div>
                                <template x-if="existingNote">
                                    <p class="text-sm text-gray-700 dark:text-gray-300 italic mb-3" x-text="'&quot;' + existingNote + '&quot;'"></p>
                                </template>
                                <template x-if="existingPhotos && existingPhotos.length > 0">
                                    <div class="flex gap-2 overflow-x-auto pb-1">
                                        <template x-for="photo in existingPhotos" :key="photo.id || photo.url">
                                            <div class="w-16 h-16 flex-shrink-0 rounded shadow-sm overflow-hidden border border-gray-200 dark:border-gray-600">
                                                <img :src="photo.thumbnail || photo.url" class="w-full h-full object-cover" />
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <textarea x-model="noteValue"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  rows="4"
                                  placeholder="Enter your note here..."></textarea>
                        <div class="flex justify-end gap-3 mt-4">
                            <button type="button" @click="close()"
                                    class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                                Cancel
                            </button>
                            <button type="button" @click="saveNote()"
                                    :disabled="noteSaving"
                                    class="px-4 py-2 bg-[var(--button-primary-color)] text-[var(--on-primary)] rounded-lg hover:bg-[var(--button-primary-hover)] disabled:opacity-50">
                                <span x-show="!noteSaving">Save Note</span>
                                <span x-show="noteSaving">Saving...</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Photo Upload Modal -->
                <div x-show="show && type === 'photo'"
                     class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                     @click.self="close()"
                     @keydown.escape.window="close()">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">
                            <span x-text="isPhotoRequired ? 'Upload Photo' : 'Add Note / Photo'"></span>
                        </h3>

                        <!-- Preview existing data -->
                        <template x-if="(existingPhotos && existingPhotos.length > 0) || existingNote">
                            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-100 dark:border-gray-700">
                                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Previously Saved</div>
                                <template x-if="existingNote">
                                    <p class="text-sm text-gray-700 dark:text-gray-300 italic mb-3" x-text="'&quot;' + existingNote + '&quot;'"></p>
                                </template>
                                <template x-if="existingPhotos && existingPhotos.length > 0">
                                    <div class="flex gap-2 overflow-x-auto pb-1">
                                        <template x-for="photo in existingPhotos" :key="photo.id || photo.url">
                                            <div class="w-16 h-16 flex-shrink-0 rounded shadow-sm overflow-hidden border border-gray-200 dark:border-gray-600">
                                                <img :src="photo.thumbnail || photo.url" class="w-full h-full object-cover" />
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Multi-File Upload Area -->
                        <div class="mb-4">
                            <div class="relative flex flex-col items-center justify-center w-full h-28 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <div class="flex flex-col items-center justify-center py-4 pointer-events-none">
                                    <svg class="w-7 h-7 mb-2 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        <span class="font-semibold text-blue-600 dark:text-blue-400" x-text="previews.length > 0 ? 'Tap to add more' : 'Tap to select photos'"></span>
                                    </p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">Select multiple photos at once</p>
                                </div>
                                <input type="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" accept="image/*" multiple
                                       :capture="!window.__userCanUpload ? 'environment' : null"
                                       @change="handleFileChange($event)"
                                       id="global-task-photo-input" />
                            </div>
                        </div>

                        <!-- Multi-file Preview Grid -->
                        <template x-if="previews.length > 0">
                            <div class="mb-4">
                                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">
                                    <span x-text="previews.length"></span> photo(s) selected
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <template x-for="(preview, index) in previews" :key="index">
                                        <div class="relative group rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800">
                                            <img :src="preview.url" class="w-full aspect-square object-cover" :alt="preview.name" />
                                            <button type="button"
                                                    @click.stop="removePreview(index)"
                                                    class="absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 hover:bg-red-700 shadow-md transition-all z-10">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Upload Progress Bar -->
                        <template x-if="photoUploading && uploadProgress > 0">
                            <div class="mb-4">
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="bg-[var(--theme-primary)] h-2 rounded-full transition-all duration-300"
                                         :style="'width: ' + uploadProgress + '%'"></div>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 text-center" x-text="uploadProgress + '%'"></p>
                            </div>
                        </template>

                        <!-- Note Input -->
                        <div class="mb-4">
                            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                Note <span class="text-gray-400 text-xs">(optional)</span>
                            </label>
                            <textarea x-model="photoNoteValue"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-[var(--theme-primary)]"
                                      rows="2"
                                      placeholder="Add a note (optional)..."></textarea>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button" @click="close()"
                                    class="px-4 py-2 min-h-[44px] text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                                Cancel
                            </button>
                            <button type="button"
                                    :disabled="!canSubmitPhoto || photoUploading"
                                    @click="uploadPhoto()"
                                    class="px-4 py-2 min-h-[44px] bg-[var(--button-primary-color)] text-[var(--on-primary)] rounded-lg hover:bg-[var(--button-primary-hover)] disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="!photoUploading" x-text="previews.length > 0 ? 'Upload ' + previews.length + ' Photo' + (previews.length !== 1 ? 's' : '') : 'Save Note'"></span>
                                <span x-show="photoUploading" class="flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Uploading...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Inventory Modal (Qty + Photo) -->
                <div x-show="show && type === 'inventory'"
                     class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                     @click.self="close()"
                     @keydown.escape.window="close()">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4" x-text="'Inventory: ' + inventoryTaskName"></h3>

                        <!-- NEW: Preview existing data -->
                        <template x-if="(existingPhotos && existingPhotos.length > 0) || existingNote">
                            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-100 dark:border-gray-700">
                                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Previously Saved</div>
                                <template x-if="existingNote">
                                    <p class="text-sm text-gray-700 dark:text-gray-300 italic mb-3" x-text="'&quot;' + existingNote + '&quot;'"></p>
                                </template>
                                <template x-if="existingPhotos && existingPhotos.length > 0">
                                    <div class="flex gap-2 overflow-x-auto pb-1">
                                        <template x-for="photo in existingPhotos" :key="photo.id || photo.url">
                                            <div class="w-16 h-16 flex-shrink-0 rounded shadow-sm overflow-hidden border border-gray-200 dark:border-gray-600">
                                                <img :src="photo.thumbnail || photo.url" class="w-full h-full object-cover" />
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Step 1: Quantity -->
                        <div x-show="inventoryStep === 1">
                            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-100">Enter Quantity</label>
                            <input type="number" x-model="inventoryQuantity" min="0" step="1"
                                   @keydown.enter.prevent="inventoryNextStep()"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-2"
                                   placeholder="e.g. 5" />
                            <p x-show="inventoryError !== ''" x-text="inventoryError" class="text-sm text-red-600 dark:text-red-400 mb-4"></p>
                            
                            <div class="flex justify-end gap-3 mt-4">
                                <button type="button" @click="close()"
                                        class="px-4 py-2 min-h-[44px] text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                                    Cancel
                                </button>
                                <button type="button" @click="inventoryNextStep()"
                                        class="px-4 py-2 min-h-[44px] bg-[var(--button-primary-color)] text-[var(--on-primary)] rounded-lg hover:bg-[var(--button-primary-hover)]">
                                    Next: Take Photo
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Photo Upload -->
                        <div x-show="inventoryStep === 2" x-cloak>
                            <div class="mb-4">
                                <template x-if="!previewUrl">
                                    <div class="relative flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6 pointer-events-none">
                                            <svg class="w-8 h-8 mb-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                            </svg>
                                            <p class="mb-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold text-blue-600 dark:text-blue-400">Tap to capture/upload</span></p>
                                        </div>
                                        <input type="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" accept="image/*" :capture="!window.__userCanUpload ? 'environment' : null"
                                               @change="handleFileChange($event)" />
                                    </div>
                                </template>
                                <template x-if="previewUrl">
                                    <div class="relative mt-2">
                                        <img :src="previewUrl" class="w-full h-48 object-cover rounded-lg" />
                                        <button @click="clearPreview()"
                                                class="absolute top-2 right-2 bg-red-600 text-white rounded-full p-1 hover:bg-red-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                            
                            <div class="flex justify-between mt-4">
                                <button type="button" @click="inventoryStep = 1; clearPreview()"
                                        class="px-4 py-2 min-h-[44px] text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                                    &larr; Back
                                </button>
                                <button type="button"
                                        :disabled="!previewUrl || photoUploading"
                                        @click="inventoryUploadAndComplete()"
                                        class="px-4 py-2 min-h-[44px] bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span x-show="!photoUploading">Upload & Save</span>
                                    <span x-show="photoUploading">Saving...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instructions / Videos Quick-Access Modal -->
                <div x-show="show && type === 'instructions'"
                     class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                     @click.self="close()"
                     @keydown.escape.window="close()">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                        <!-- Header -->
                        <div class="sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between rounded-t-xl z-10">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span x-text="instructionTaskName"></span>
                            </h3>
                            <button type="button" @click="close()"
                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        <div class="p-6 space-y-5">
                            <!-- Instructions Text -->
                            <template x-if="instructionText">
                                <div>
                                    <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">Instructions</div>
                                    <div class="prose dark:prose-invert prose-sm max-w-none text-gray-700 dark:text-gray-300 leading-relaxed bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-100 dark:border-gray-700"
                                         x-html="instructionText.replace(/\n/g, '<br>')"></div>
                                </div>
                            </template>

                            <!-- Instructional Media (Images & Videos) -->
                            <template x-if="instructionMedia && instructionMedia.length > 0">
                                <div>
                                    <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">Reference Media</div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <template x-for="(media, idx) in instructionMedia" :key="idx">
                                            <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800">
                                                <template x-if="media.type === 'image'">
                                                    <button type="button"
                                                            @click="$dispatch('open-gallery', { src: media.url })"
                                                            class="block w-full">
                                                        <div class="aspect-video w-full relative bg-gray-200 dark:bg-gray-700">
                                                            <img :src="media.thumbnail || media.url"
                                                                 :alt="media.caption || 'Instruction image'"
                                                                 class="w-full h-full object-cover hover:scale-105 transition-transform" />
                                                        </div>
                                                    </button>
                                                </template>
                                                <template x-if="media.type === 'video'">
                                                    <video :src="media.url" class="w-full aspect-video object-cover" controls playsinline>
                                                        Your browser does not support the video tag.
                                                    </video>
                                                </template>
                                                <template x-if="media.caption">
                                                    <div class="px-2 py-1.5 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700">
                                                        <p class="text-xs text-gray-600 dark:text-gray-400 text-center" x-text="media.caption"></p>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Empty state -->
                            <template x-if="!instructionText && (!instructionMedia || instructionMedia.length === 0)">
                                <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p class="text-sm">No instructions available for this task.</p>
                                </div>
                            </template>
                        </div>

                        <!-- Footer -->
                        <div class="sticky bottom-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 px-6 py-4 rounded-b-xl">
                            <button type="button" @click="markInstructionRead()"
                                    :disabled="instructionMarkingRead"
                                    class="w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-semibold text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="!instructionMarkingRead">✓ I Have Read and Understood</span>
                                <span x-show="instructionMarkingRead" class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Confirming...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Gallery / Fullscreen Modal -->
                <div x-show="show && type === 'gallery'"
                     class="fixed inset-0 z-[200] bg-black/90 flex items-center justify-center p-4"
                     @click.self="close()"
                     @keydown.escape.window="close()">
                    <img :src="gallerySrc" class="max-h-[90vh] max-w-[90vw] rounded-lg shadow-2xl" alt="Gallery view" />
                    <button type="button" @click="close()"
                            class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300 transition-colors">×</button>
                </div>
            </div>
