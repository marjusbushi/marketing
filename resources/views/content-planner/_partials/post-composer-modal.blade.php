{{-- Post Detail View — Planable style --}}
<div id="postComposerOverlay" style="display:none; position:fixed; inset:0; z-index:9990; background:#fff; font-family:Inter,system-ui,sans-serif;">

    {{-- Top bar --}}
    <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 20px; border-bottom:1px solid #e5e7eb; height:48px;">
        {{-- Left: platform icon + type tabs + Campaign + Labels pills (Planable-style) --}}
        <div style="display:flex; align-items:center; gap:12px;">
            <div id="composerPlatformIcon" style="width:28px; height:28px; display:flex; align-items:center; justify-content:center;">
                <iconify-icon icon="skill-icons:instagram" width="24"></iconify-icon>
            </div>
            <div style="display:flex; align-items:center; gap:4px;">
                <button type="button" class="cp-type-tab active" data-type="post" onclick="switchContentType('post')">Post</button>
                <button type="button" class="cp-type-tab" data-type="story" onclick="switchContentType('story')">Story</button>
                <button type="button" class="cp-type-tab" data-type="reels" onclick="switchContentType('reels')">Reels</button>
            </div>
            <div style="width:1px; height:20px; background:#e5e7eb;"></div>
            <button type="button" id="composerCampaignPill" onclick="openCampaignPicker()" title="Assign a campaign"
                style="height:28px; padding:0 10px; border-radius:14px; border:1px solid #e2e8f0; background:#fff; color:#64748b; cursor:pointer; display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:500;">
                <iconify-icon icon="heroicons-outline:megaphone" width="12"></iconify-icon>
                <span data-default="Campaign">Campaign</span>
            </button>
            <button type="button" id="composerLabelsPill" onclick="openLabelsPicker()" title="Add labels"
                style="height:28px; padding:0 10px; border-radius:14px; border:1px solid #e2e8f0; background:#fff; color:#64748b; cursor:pointer; display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:500;">
                <iconify-icon icon="heroicons-outline:tag" width="12"></iconify-icon>
                <span data-default="Labels">Labels</span>
            </button>
        </div>
        {{-- Right: platforms + share + actions + close --}}
        <div style="display:flex; align-items:center; gap:8px;">
            <div id="composerPlatforms" style="display:flex; gap:4px;">
                <button type="button" class="cp-plat-btn" data-platform="instagram" onclick="togglePlatformBtn(this)" style="width:28px;height:28px;">
                    <iconify-icon icon="skill-icons:instagram" width="18"></iconify-icon>
                </button>
                <button type="button" class="cp-plat-btn" data-platform="facebook" onclick="togglePlatformBtn(this)" style="width:28px;height:28px;">
                    <iconify-icon icon="logos:facebook" width="18"></iconify-icon>
                </button>
                <button type="button" class="cp-plat-btn" data-platform="tiktok" onclick="togglePlatformBtn(this)" style="width:28px;height:28px;">
                    <iconify-icon icon="logos:tiktok-icon" width="16"></iconify-icon>
                </button>
            </div>
            <div style="width:1px; height:20px; background:#e5e7eb;"></div>
            <button onclick="savePost('draft')" style="height:30px; padding:0 12px; font-size:11px; font-weight:500; border-radius:6px; border:1px solid #e2e8f0; background:#fff; color:#64748b; cursor:pointer; display:inline-flex; align-items:center;">Save draft</button>
            <button onclick="savePost('scheduled')" style="height:30px; padding:0 12px; font-size:11px; font-weight:600; border-radius:6px; border:none; background:#6366f1; color:#fff; cursor:pointer; display:inline-flex; align-items:center;">Schedule</button>
            {{-- Delete button -- gjithmone i dukshem kur composer hapet per
                 nje post ekzistues. Per post te ri (postId=null) fshihet ne
                 openComposer(). Backend permission gate (CONTENT_PLANNER_DELETE)
                 vendos perfundimisht; UI shfaq alert nese deshton. --}}
            <button id="composerDeleteBtn" onclick="deletePost()" type="button"
                style="display:inline-flex; height:30px; padding:0 12px; font-size:11px; font-weight:500; border-radius:6px; border:1px solid #fecaca; background:#fff; color:#dc2626; cursor:pointer; align-items:center; gap:5px;"
                onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff'"
                title="Fshi kete post">
                <iconify-icon icon="heroicons-outline:trash" width="13"></iconify-icon>
                Fshi
            </button>
            <div style="width:1px; height:20px; background:#e5e7eb;"></div>
            <button onclick="closeComposer()" style="width:30px; height:30px; border:none; background:none; cursor:pointer; display:flex; align-items:center; justify-content:center; border-radius:6px;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">
                <iconify-icon icon="heroicons-outline:x-mark" width="18" style="color:#94a3b8;"></iconify-icon>
            </button>
        </div>
    </div>

    {{-- Main body --}}
    <div style="display:flex; height:calc(100vh - 48px); overflow:hidden;">

        {{-- POST / REELS view --}}
        <div id="composerPostReels" style="display:flex; flex:1; min-width:0;">

            {{-- Planable-style vertical approval rail — compact column on the far left --}}
            <div id="composerApprovalRail" style="width:72px; flex-shrink:0; border-right:1px solid #f1f5f9; display:flex; flex-direction:column; align-items:center; padding:24px 0; gap:4px; background:#fafbfc;">
                {{-- Step 1: Approval status --}}
                <button id="composerApproveBtn" onclick="approvePost()" title="Approve post"
                    style="width:32px; height:32px; border-radius:50%; border:1.5px solid #d1d5db; background:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                    <iconify-icon icon="heroicons-outline:check" width="14" style="color:#9ca3af;"></iconify-icon>
                </button>
                <span id="composerApprovalStatus" style="font-size:9px; color:#94a3b8; text-align:center; line-height:1.2; max-width:64px;">Not approved<br>yet</span>

                {{-- Connector line --}}
                <div style="width:1px; height:20px; background:#e5e7eb; margin:4px 0;"></div>

                {{-- Step 2: Auto-publish status (lightning) --}}
                <div title="Auto-publish disabled" style="width:32px; height:32px; border-radius:50%; background:#fff; border:1.5px solid #e5e7eb; display:flex; align-items:center; justify-content:center;">
                    <iconify-icon icon="heroicons-outline:bolt" width="14" style="color:#cbd5e1;"></iconify-icon>
                </div>

                {{-- Connector --}}
                <div style="width:1px; height:20px; background:#e5e7eb; margin:4px 0;"></div>

                {{-- Step 3: Device (phone) --}}
                <div title="Mobile preview" style="width:32px; height:32px; border-radius:50%; background:#fff; border:1.5px solid #e5e7eb; display:flex; align-items:center; justify-content:center;">
                    <iconify-icon icon="heroicons-outline:device-phone-mobile" width="14" style="color:#9ca3af;"></iconify-icon>
                </div>

                {{-- Connector --}}
                <div style="width:1px; height:20px; background:#e5e7eb; margin:4px 0;"></div>

                {{-- Step 4: Target platform --}}
                <div title="Publishes to Instagram" style="width:32px; height:32px; border-radius:50%; background:#fff; border:1.5px solid #e5e7eb; display:flex; align-items:center; justify-content:center;">
                    <iconify-icon icon="skill-icons:instagram" width="18"></iconify-icon>
                </div>
            </div>

            {{-- Center: Post content --}}
            <div style="flex:1; display:flex; flex-direction:column; overflow-y:auto; min-width:0;">

                {{-- User + schedule + device toggle --}}
                <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:16px 24px 12px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="width:28px; height:28px; border-radius:50%; background:#e0e7ff; display:flex; align-items:center; justify-content:center;">
                            <iconify-icon icon="heroicons-outline:user" width="14" style="color:#6366f1;"></iconify-icon>
                        </div>
                        <span style="font-size:13px; font-weight:500; color:#1e293b;">{{ auth()->user()->name ?? 'User' }}</span>
                        <span style="color:#cbd5e1;">·</span>
                        <span id="scheduleLabel" style="font-size:12px; color:#94a3b8; cursor:pointer;" onclick="openSchedulePicker()">Select date & time</span>
                        <input id="composerScheduledAt" type="hidden">
                    </div>

                    {{-- Device toggle (phone / desktop) — controls preview width --}}
                    <div id="composerDeviceToggle" role="tablist" style="display:flex; background:#f1f5f9; border-radius:7px; padding:2px; gap:0;">
                        <button type="button" class="cp-device-btn active" data-device="desktop" onclick="setPreviewDevice('desktop')" aria-label="Desktop preview"
                            style="width:32px; height:28px; border:none; background:#fff; border-radius:5px; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 2px rgba(0,0,0,0.04);">
                            <iconify-icon icon="heroicons-outline:computer-desktop" width="14" style="color:#18181b;"></iconify-icon>
                        </button>
                        <button type="button" class="cp-device-btn" data-device="phone" onclick="setPreviewDevice('phone')" aria-label="Phone preview"
                            style="width:32px; height:28px; border:none; background:transparent; border-radius:5px; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                            <iconify-icon icon="heroicons-outline:device-phone-mobile" width="14" style="color:#71717a;"></iconify-icon>
                        </button>
                    </div>
                </div>

                {{-- Instagram post mockup frame --}}
                <div style="flex:1; display:flex; align-items:center; justify-content:center; background:#fafbfc; padding:24px; overflow-y:auto;">
                    <div id="composerPreviewCard" style="width:100%; max-width:380px; position:relative; transition:max-width 0.2s ease;">

                        {{-- Photo toolbar left --}}
                        <div id="composerPhotoToolbarLeft" style="display:none; position:absolute; top:12px; left:12px; z-index:10; gap:4px;">
                            <button onclick="openMediaLibraryPicker()" style="width:30px; height:30px; border-radius:50%; background:rgba(0,0,0,0.5); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="Replace photo">
                                <iconify-icon icon="heroicons-outline:camera" width="14" style="color:#fff;"></iconify-icon>
                            </button>
                            <button onclick="editCurrentMedia()" type="button" style="width:34px; height:34px; border-radius:50%; background:rgba(0,0,0,0.65); border:1.5px solid rgba(255,255,255,0.4); cursor:pointer; display:flex; align-items:center; justify-content:center; backdrop-filter:blur(4px);" title="Edit photo (crop, filter, resize)">
                                <iconify-icon icon="heroicons-outline:pencil-square" width="16" style="color:#fff;"></iconify-icon>
                            </button>
                            <button onclick="editCurrentMedia()" type="button" style="width:34px; height:34px; border-radius:50%; background:rgba(0,0,0,0.65); border:1.5px solid rgba(255,255,255,0.4); cursor:pointer; display:flex; align-items:center; justify-content:center; backdrop-filter:blur(4px);" title="Filter / adjust">
                                <iconify-icon icon="heroicons-outline:adjustments-horizontal" width="16" style="color:#fff;"></iconify-icon>
                            </button>
                        </div>
                        {{-- Photo toolbar right --}}
                        <div id="composerPhotoToolbarRight" style="display:none; position:absolute; top:12px; right:12px; z-index:10; gap:4px;">
                            <button onclick="downloadComposerMedia()" style="width:30px; height:30px; border-radius:50%; background:rgba(0,0,0,0.5); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="Download">
                                <iconify-icon icon="heroicons-outline:arrow-down-tray" width="14" style="color:#fff;"></iconify-icon>
                            </button>
                            <button onclick="removeAllMedia()" style="width:30px; height:30px; border-radius:50%; background:rgba(0,0,0,0.5); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="Remove">
                                <iconify-icon icon="heroicons-outline:x-mark" width="14" style="color:#fff;"></iconify-icon>
                            </button>
                        </div>

                        {{-- The card --}}
                        <div style="background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,0.08); overflow:hidden;">
                            {{-- Photo / Carousel --}}
                            <div id="composerMediaPreview" style="display:none; position:relative;">
                                {{-- Overflow-hidden viewport holds the sliding track. --}}
                                <div id="composerMediaViewport" class="cp-media-viewport" style="position:relative; overflow:hidden; touch-action:pan-y; user-select:none;">
                                    <div id="composerMediaMain" style="display:flex; transition:transform 0.3s ease; will-change:transform;"></div>

                                    {{-- Hover overlay with prominent 'Edit photo' button.
                                         Shows on mouse-hover over the photo area. Does NOT
                                         swallow swipe/drag events — we only capture clicks. --}}
                                    <div class="cp-media-hover" id="composerMediaHover">
                                        <button type="button" class="cp-media-edit-btn" onclick="event.stopPropagation(); editCurrentMedia();">
                                            <iconify-icon icon="heroicons-outline:pencil-square" width="16"></iconify-icon>
                                            <span>Edit photo</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Left / right arrows (shown only on multi-item) --}}
                                <button type="button" id="composerCarouselPrev" onclick="carouselPrev()" aria-label="Previous slide"
                                    style="display:none; position:absolute; left:8px; top:50%; transform:translateY(-50%); width:30px; height:30px; border-radius:50%; border:none; background:rgba(255,255,255,0.9); box-shadow:0 1px 3px rgba(0,0,0,0.15); cursor:pointer; align-items:center; justify-content:center; padding:0;">
                                    <iconify-icon icon="heroicons-outline:chevron-left" width="18" style="color:#262626;"></iconify-icon>
                                </button>
                                <button type="button" id="composerCarouselNext" onclick="carouselNext()" aria-label="Next slide"
                                    style="display:none; position:absolute; right:8px; top:50%; transform:translateY(-50%); width:30px; height:30px; border-radius:50%; border:none; background:rgba(255,255,255,0.9); box-shadow:0 1px 3px rgba(0,0,0,0.15); cursor:pointer; align-items:center; justify-content:center; padding:0;">
                                    <iconify-icon icon="heroicons-outline:chevron-right" width="18" style="color:#262626;"></iconify-icon>
                                </button>

                                {{-- Slide counter badge (top-right, IG style) --}}
                                <div id="composerCarouselCounter" style="display:none; position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.55); color:#fff; font-size:11px; font-weight:500; padding:3px 9px; border-radius:11px; letter-spacing:0.2px;"></div>

                                {{-- Dots indicator (bottom, IG style) --}}
                                <div id="composerCarouselDots" style="display:none; position:absolute; bottom:10px; left:0; right:0; justify-content:center; gap:4px; pointer-events:none;"></div>
                            </div>
                            <div id="composerMediaEmpty" style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; cursor:pointer; aspect-ratio:1; background:#f8fafc;" onclick="openMediaLibraryPicker()">
                                <iconify-icon icon="heroicons-outline:photo" width="48" style="color:#d1d5db;"></iconify-icon>
                                <span style="font-size:13px; color:#94a3b8;">Click to add media</span>
                            </div>

                            {{-- Below photo: IG actions row --}}
                            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px 4px;">
                                <div style="display:flex; gap:12px;">
                                    <iconify-icon icon="heroicons-outline:heart" width="22" style="color:#262626;"></iconify-icon>
                                    <iconify-icon icon="heroicons-outline:chat-bubble-oval-left" width="22" style="color:#262626;"></iconify-icon>
                                    <iconify-icon icon="heroicons-outline:paper-airplane" width="22" style="color:#262626;"></iconify-icon>
                                </div>
                                <iconify-icon icon="heroicons-outline:bookmark" width="22" style="color:#262626;"></iconify-icon>
                            </div>

                            {{-- Caption area --}}
                            <div style="padding:4px 14px 14px;">
                                <textarea id="composerContent" placeholder="Write something..." style="width:100%; min-height:40px; font-size:13px; line-height:1.5; resize:none; outline:none; border:none; padding:0; color:#262626; font-family:inherit; background:transparent;" oninput="updateCharCount();"></textarea>
                            </div>
                        </div>

                        {{-- Platform indicator below card --}}
                        <div style="display:flex; align-items:center; gap:6px; padding:10px 4px 0;">
                            <iconify-icon icon="mdi:instagram" width="16" style="color:#e4405f;"></iconify-icon>
                        </div>

                        {{-- Media strip --}}
                        <div id="composerMediaStrip" style="display:none; padding:8px 0 0;">
                            <div id="composerMediaGrid" style="display:flex; gap:6px; overflow-x:auto;"></div>
                        </div>
                    </div>
                    <input id="mediaFileInput" type="file" accept="image/*,video/*" multiple style="display:none;" onchange="handleMediaSelect(this.files); this.value='';">
                    <input id="mediaVideoInput" type="file" accept="image/*,video/*" multiple style="display:none;" onchange="handleMediaSelect(this.files); this.value='';">
                </div>
            </div>

            {{-- Right: Feedback sidebar --}}
            <div style="width:320px; flex-shrink:0; border-left:1px solid #e5e7eb; display:flex; flex-direction:column; background:#fff;">
                {{-- Header --}}
                <div style="display:flex; align-items:center; padding:14px 16px; border-bottom:1px solid #f1f5f9;">
                    <span style="font-size:14px; font-weight:600; color:#1e293b;">Feedback</span>
                </div>
                {{-- Tabs --}}
                <div style="display:flex; border-bottom:1px solid #f1f5f9;">
                    <button class="cp-feedback-tab active" data-feedback-tab="comments" onclick="switchFeedbackTab('comments', this)">
                        Comments
                        <span id="cp-tab-comments-count" class="cp-tab-count" style="display:none;"></span>
                    </button>
                    <button class="cp-feedback-tab" data-feedback-tab="suggestions" onclick="switchFeedbackTab('suggestions', this)">
                        Suggestions
                        <span id="cp-tab-suggestions-count" class="cp-tab-count" style="display:none;"></span>
                    </button>
                </div>

                {{-- Comments panel --}}
                <div id="cp-panel-comments" class="cp-feedback-panel" style="display:flex; flex-direction:column; flex:1; min-height:0;">
                    <div style="padding:12px 16px; border-bottom:1px solid #f1f5f9;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <iconify-icon icon="heroicons-outline:chat-bubble-left" width="16" style="color:#cbd5e1;"></iconify-icon>
                            <input id="feedbackCommentInput" type="text" placeholder="Say something..." style="flex:1; border:none; outline:none; font-size:13px; color:#374151; font-family:inherit;" onkeydown="if(event.key==='Enter')addComment()">
                        </div>
                    </div>
                    <div id="feedbackCommentsList" style="flex:1; overflow-y:auto; padding:16px;">
                        <div class="cp-feedback-empty" style="text-align:center; padding:40px 16px;">
                            <p style="font-size:13px; font-weight:500; color:#64748b; margin:0 0 4px;">No comments yet</p>
                            <p style="font-size:12px; color:#94a3b8; margin:0;">Start the conversation by leaving the first comment.</p>
                        </div>
                    </div>
                </div>

                {{-- Suggestions panel (list only — the composer pops up inline from the caption selection) --}}
                <div id="cp-panel-suggestions" class="cp-feedback-panel" style="display:none; flex-direction:column; flex:1; min-height:0;">
                    <div style="padding:10px 16px; border-bottom:1px solid #f1f5f9; background:#fafbfc; font-size:11px; color:#64748b; display:flex; align-items:center; gap:6px;">
                        <iconify-icon icon="heroicons-outline:cursor-arrow-rays" width="14" style="color:#94a3b8;"></iconify-icon>
                        Select any text in the caption to suggest an edit.
                    </div>
                    <div id="feedbackSuggestionsList" style="flex:1; overflow-y:auto; padding:16px;">
                        <div class="cp-feedback-empty" style="text-align:center; padding:40px 16px;">
                            <p style="font-size:13px; font-weight:500; color:#64748b; margin:0 0 4px;">No suggestions yet</p>
                            <p style="font-size:12px; color:#94a3b8; margin:0;">Highlight text in the caption and propose a replacement.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- STORY view --}}
        <div id="composerStory" style="display:none; flex:1; align-items:center; justify-content:center; gap:16px; background:#fafbfc;">
            <div style="width:48px; height:84px; border:1.5px dashed #e2e8f0; border-radius:10px; display:flex; align-items:center; justify-content:center; cursor:pointer;" onclick="document.getElementById('storyFileInput').click()">
                <iconify-icon icon="heroicons-outline:plus" width="14" style="color:#cbd5e1;"></iconify-icon>
            </div>
            <div id="storyFrame" class="story-phone-frame" onclick="document.getElementById('storyFileInput').click()" ondragover="event.preventDefault(); this.classList.add('border-primary-400','bg-primary-50');" ondragleave="this.classList.remove('border-primary-400','bg-primary-50');" ondrop="handleStoryDrop(event)">
                <div id="storyFrameContent" style="width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px;">
                    <iconify-icon icon="heroicons-outline:cloud-arrow-up" width="24" style="color:#cbd5e1;"></iconify-icon>
                    <p style="font-size:11px; color:#94a3b8; text-align:center; line-height:1.4; margin:0;">Drop media from your library<br>or from computer</p>
                </div>
            </div>
            <div style="width:48px; height:84px; border:1.5px dashed #e2e8f0; border-radius:10px; display:flex; align-items:center; justify-content:center; cursor:pointer;" onclick="document.getElementById('storyFileInput').click()">
                <iconify-icon icon="heroicons-outline:plus" width="14" style="color:#cbd5e1;"></iconify-icon>
            </div>
            <input id="storyFileInput" type="file" accept="image/*,video/*" style="display:none;" onchange="handleStoryMedia(this.files); this.value='';">
        </div>
    </div>
</div>

{{-- Floating 'Suggest edit' pill — shown next to the caption textarea
     whenever the user has selected some text. Clicking it opens a small
     inline popover with just the replacement input. --}}
<button type="button" id="suggestFloatBtn" class="cp-suggest-float" onclick="openSuggestFloatPopover()" aria-label="Suggest an edit to the selected text">
    <iconify-icon icon="heroicons-outline:pencil-square" width="13"></iconify-icon>
    Suggest edit
</button>

<div id="suggestFloatPopover" style="position:fixed; z-index:10006; display:none; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.15); padding:10px; width:260px; font-family:Inter,system-ui,sans-serif;">
    <div style="font-size:10px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Suggest edit</div>
    <div id="suggestFloatOriginal" style="font-size:12px; line-height:1.5; color:#991b1b; text-decoration:line-through; padding:6px 8px; background:#fef2f2; border-radius:5px; margin-bottom:6px; word-break:break-word;"></div>
    <input id="suggestFloatReplacement" type="text" placeholder="Replace with…" style="width:100%; padding:7px 10px; border:1px solid #e5e7eb; border-radius:6px; font-size:12px; outline:none; font-family:inherit; margin-bottom:8px;" onkeydown="if(event.key==='Enter')submitSuggestFloat(); if(event.key==='Escape')closeSuggestFloatPopover();">
    <div style="display:flex; justify-content:flex-end; gap:6px;">
        <button type="button" onclick="closeSuggestFloatPopover()" style="padding:5px 10px; font-size:11px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; color:#64748b; cursor:pointer;">Cancel</button>
        <button type="button" onclick="submitSuggestFloat()" style="padding:5px 12px; font-size:11px; border:none; border-radius:6px; background:#6366f1; color:#fff; cursor:pointer; font-weight:500;">Propose</button>
    </div>
</div>

{{-- Schedule Picker Modal (Planable-style: calendar + top picks + engagement forecast + recurring) --}}
<div id="schedPickerOverlay" class="hidden fixed inset-0 bg-black/20 z-[9998]" onclick="closeSchedulePicker()"></div>
<div id="schedPickerModal" class="hidden fixed z-[9999] bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden" style="width:640px; max-width:95vw; top:50%; left:50%; transform:translate(-50%,-50%);">
    <div style="display:flex; min-height:0;">
        {{-- Calendar side --}}
        <div style="flex:1; padding:20px 16px 12px; border-right:1px solid #f1f5f9;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <div style="display:flex; align-items:center; gap:4px;">
                    <button onclick="schedNavMonth(-12)" class="w-6 h-6 rounded hover:bg-slate-100 flex items-center justify-center text-slate-400" title="Previous year"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5"/></svg></button>
                    <button onclick="schedNavMonth(-1)" class="w-6 h-6 rounded hover:bg-slate-100 flex items-center justify-center text-slate-400" title="Previous month"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg></button>
                </div>
                <span id="schedMonthLabel" class="text-[13px] font-semibold text-slate-800"></span>
                <div style="display:flex; align-items:center; gap:4px;">
                    <button onclick="schedNavMonth(1)" class="w-6 h-6 rounded hover:bg-slate-100 flex items-center justify-center text-slate-400" title="Next month"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg></button>
                    <button onclick="schedNavMonth(12)" class="w-6 h-6 rounded hover:bg-slate-100 flex items-center justify-center text-slate-400" title="Next year"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 4.5l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5"/></svg></button>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:repeat(7,1fr); text-align:center; margin-bottom:4px;">
                <span class="text-[10px] font-medium text-slate-400 py-1">Mo</span>
                <span class="text-[10px] font-medium text-slate-400 py-1">Tu</span>
                <span class="text-[10px] font-medium text-slate-400 py-1">We</span>
                <span class="text-[10px] font-medium text-slate-400 py-1">Th</span>
                <span class="text-[10px] font-medium text-slate-400 py-1">Fr</span>
                <span class="text-[10px] font-medium text-slate-400 py-1">Sa</span>
                <span class="text-[10px] font-medium text-slate-400 py-1">Su</span>
            </div>
            <div id="schedCalendarGrid" style="display:grid; grid-template-columns:repeat(7,1fr); text-align:center;"></div>
        </div>

        {{-- Time + suggestions side --}}
        <div style="width:300px; padding:20px 18px 12px; display:flex; flex-direction:column; gap:14px;">
            {{-- Select time heading + help tooltip --}}
            <div style="display:flex; align-items:center; gap:6px;">
                <span style="font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.05em;">Select time</span>
            </div>

            {{-- Top picks for you (Planable-style) --}}
            <div>
                <div style="display:flex; align-items:center; gap:5px; margin-bottom:8px;">
                    <span style="font-size:11px; font-weight:600; color:#0f172a;">Top picks for you</span>
                    <span title="Based on your past 90 days of scheduled posts" style="cursor:help; color:#cbd5e1;">
                        <iconify-icon icon="heroicons-outline:question-mark-circle" width="13"></iconify-icon>
                    </span>
                </div>
                <div id="schedTopPicks" style="display:flex; gap:8px;">
                    {{-- Filled by JS: two buttons with HH:MM times --}}
                </div>
            </div>

            {{-- Time input (replaces old quick-picks) --}}
            <div>
                <input id="schedTimeInput" type="time" value="12:00" style="width:100%; height:38px; border:1px solid #e2e8f0; border-radius:8px; padding:0 12px; font-size:14px; font-weight:600; color:#1e293b; text-align:center; outline:none; font-family:inherit;" onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#e2e8f0'" onchange="updateSchedSummary()">
            </div>

            {{-- Engagement forecast bar chart --}}
            <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <span style="font-size:10px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.05em;">Engagement forecast</span>
                    <span style="font-size:9px; color:#94a3b8;">3-hour avg</span>
                </div>
                <div id="schedForecast" style="display:flex; align-items:flex-end; gap:4px; height:56px; padding:4px 0;">
                    {{-- Filled by JS: 8 bars --}}
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:4px;">
                    <span style="font-size:9px; color:#cbd5e1;">00</span>
                    <span style="font-size:9px; color:#cbd5e1;">06</span>
                    <span style="font-size:9px; color:#cbd5e1;">12</span>
                    <span style="font-size:9px; color:#cbd5e1;">18</span>
                </div>
            </div>

            {{-- Recurring toggle --}}
            <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 10px; background:#f8fafc; border-radius:8px; margin-top:auto;">
                <span style="font-size:12px; font-weight:500; color:#334155;">Recurring</span>
                <label style="position:relative; display:inline-block; width:32px; height:18px;">
                    <input id="schedRecurring" type="checkbox" style="opacity:0; width:0; height:0;" onchange="updateSchedSummary()">
                    <span class="cp-switch-track" style="position:absolute; cursor:pointer; inset:0; background:#e2e8f0; border-radius:9px; transition:0.2s;"></span>
                    <span class="cp-switch-thumb" style="position:absolute; height:14px; width:14px; left:2px; bottom:2px; background:#fff; border-radius:50%; transition:0.2s; box-shadow:0 1px 2px rgba(0,0,0,0.1);"></span>
                </label>
            </div>
        </div>
    </div>
    <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; border-top:1px solid #f1f5f9; background:#fafbfc;">
        <span id="schedSummary" class="text-xs text-slate-500 font-medium"></span>
        <div style="display:flex; gap:8px;">
            <button onclick="closeSchedulePicker()" class="px-3 py-1.5 text-xs font-medium text-slate-500 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition-colors">Cancel</button>
            <button onclick="applySchedule()" class="px-4 py-1.5 text-xs font-semibold text-white bg-primary-600 rounded-lg hover:bg-primary-700 shadow-sm transition-colors">Save</button>
        </div>
    </div>
</div>

<style>
    .cp-plat-btn { width: 32px; height: 32px; border-radius: 8px; border: 2px solid transparent; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f8fafc; transition: all 0.15s; }
    .cp-plat-btn:hover { background: #f1f5f9; }
    .cp-plat-btn.active { border-color: #6366f1; background: #eef2ff; }
    .cp-type-tab { padding: 7px 14px; font-size: 12px; font-weight: 600; color: #94a3b8; background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; transition: all 0.15s; }
    .cp-type-tab:hover { color: #64748b; }
    .cp-type-tab.active { color: #1e293b; border-bottom-color: #1e293b; }
    .cp-media-btn { width: 32px; height: 32px; border-radius: 8px; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; background: transparent; color: #94a3b8; transition: all 0.15s; }
    .cp-media-btn:hover { background: #f1f5f9; color: #64748b; }
    .cp-feedback-tab { padding:10px 16px; font-size:12px; font-weight:600; color:#94a3b8; background:none; border:none; border-bottom:2px solid transparent; cursor:pointer; transition:all 0.15s; }
    .cp-feedback-tab:hover { color:#64748b; }
    .cp-feedback-tab.active { color:#6366f1; border-bottom-color:#6366f1; }
    .story-phone-frame { width: 200px; height: 350px; border: 2px dashed #e2e8f0; border-radius: 18px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s; background: #fafbfc; flex-shrink: 0; }
    .story-phone-frame:hover { border-color: #6366f1; background: #f5f3ff; }
    .story-phone-frame.has-media { border: none; padding: 0; overflow: hidden; background: #000; }
    .story-phone-frame.has-media img, .story-phone-frame.has-media video { width: 100%; height: 100%; object-fit: cover; border-radius: 16px; }
    .story-side-slot { width: 44px; height: 78px; border: 1.5px dashed #e2e8f0; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s; flex-shrink: 0; }
    .story-side-slot:hover { border-color: #6366f1; background: #f5f3ff; }

    /* Device toggle (phone / desktop) */
    .cp-device-btn { transition: all 0.15s; }
    .cp-device-btn.active { background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
    .cp-device-btn:not(.active):hover { background: rgba(255,255,255,0.5); }

    /* Desktop mode — plain card */
    #composerPreviewCard.device-desktop { max-width: 380px; }

    /* Phone mode — wrap card inside an iPhone-like bezel with notch.
       The .cp-phone-frame pseudo-elements draw the outer bezel and notch
       so no extra DOM is needed; the card itself sits inside with padding. */
    #composerPreviewCard.device-phone {
        max-width: 340px;
        padding: 12px 10px 26px;
        background: #111827;
        border-radius: 42px;
        box-shadow:
            0 0 0 2px #1f2937,           /* outer rim */
            0 20px 50px rgba(0,0,0,0.18); /* drop shadow */
        position: relative;
    }
    /* Notch (speaker + camera pill) */
    #composerPreviewCard.device-phone::before {
        content: '';
        position: absolute;
        top: 10px;
        left: 50%;
        transform: translateX(-50%);
        width: 110px;
        height: 22px;
        background: #111827;
        border-radius: 0 0 14px 14px;
        z-index: 2;
    }
    /* Home indicator bar */
    #composerPreviewCard.device-phone::after {
        content: '';
        position: absolute;
        bottom: 10px;
        left: 50%;
        transform: translateX(-50%);
        width: 110px;
        height: 4px;
        background: #374151;
        border-radius: 2px;
    }
    /* The card (direct child div) gets rounded inner corners */
    #composerPreviewCard.device-phone > div {
        border-radius: 28px !important;
        overflow: hidden;
    }
    /* Photo toolbars sit absolute inside #composerPreviewCard; in phone mode
       we push them down so they don't overlap the notch, and inward so they
       stay inside the inner card rather than hovering over the bezel. */
    #composerPreviewCard.device-phone #composerPhotoToolbarLeft {
        top: 44px !important;
        left: 22px !important;
    }
    #composerPreviewCard.device-phone #composerPhotoToolbarRight {
        top: 44px !important;
        right: 22px !important;
    }

    /* Campaign / Labels pills (top bar) */
    #composerCampaignPill:hover, #composerLabelsPill:hover { background:#f8fafc; border-color:#cbd5e1; color:#1e293b; }
    #composerCampaignPill.has-value, #composerLabelsPill.has-value { background:#eef2ff; border-color:#a5b4fc; color:#3730a3; }

    /* Schedule picker — Top picks */
    .sched-top-pick { flex:1; height:40px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; font-size:14px; font-weight:600; color:#1e293b; cursor:pointer; transition:all 0.15s; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:4px 0; }
    .sched-top-pick:hover { border-color:#6366f1; background:#eef2ff; }
    .sched-top-pick.selected { border-color:#6366f1; background:#6366f1; color:#fff; }
    .sched-top-pick-time { line-height:1; }
    .sched-top-pick-score { font-size:9px; font-weight:500; opacity:0.7; margin-top:2px; line-height:1; text-transform:uppercase; letter-spacing:0.05em; }

    /* Schedule picker — Forecast bars */
    .sched-bar { flex:1; background:#e2e8f0; border-radius:3px 3px 0 0; min-height:4px; transition:all 0.2s; position:relative; }
    .sched-bar.peak { background:linear-gradient(to top, #3b82f6, #6366f1); }
    .sched-bar.near-current { background:#6366f1; }
    .sched-bar.dim { background:#e2e8f0; }
    .sched-bar:hover { opacity:0.8; }

    /* Recurring switch */
    #schedRecurring:checked ~ .cp-switch-track { background:#6366f1 !important; }
    #schedRecurring:checked ~ .cp-switch-thumb { transform:translateX(14px); }

    /* Media hover overlay — surfaces 'Edit photo' prominently.
       Pointer-events:none on the overlay itself so swipe/drag still works;
       only the button captures clicks. */
    .cp-media-viewport { position: relative; }
    .cp-media-hover {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        padding-bottom: 16px;
        background: linear-gradient(to top, rgba(0,0,0,0.35) 0%, rgba(0,0,0,0) 50%);
        opacity: 0;
        transition: opacity 0.15s;
        pointer-events: none;
    }
    .cp-media-viewport:hover .cp-media-hover,
    .cp-media-hover:focus-within { opacity: 1; }
    .cp-media-edit-btn {
        pointer-events: auto;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: rgba(255,255,255,0.95);
        border: none;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        color: #18181b;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        transition: transform 0.15s, background 0.15s;
    }
    .cp-media-edit-btn:hover { background: #fff; transform: translateY(-1px); }

    /* Feedback tabs + panels */
    .cp-feedback-tab { position: relative; }
    .cp-tab-count { margin-left: 6px; padding: 1px 7px; border-radius: 9px; background: #e2e8f0; color: #475569; font-size: 10px; font-weight: 600; }
    .cp-feedback-tab.active .cp-tab-count { background: #eef2ff; color: #4338ca; }

    /* Comment / suggestion cards */
    .cp-comment, .cp-suggestion {
        padding: 10px 12px;
        border-radius: 8px;
        background: #fff;
        border: 1px solid #f1f5f9;
        margin-bottom: 8px;
    }
    .cp-comment-meta { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #94a3b8; margin-bottom: 4px; }
    .cp-comment-author { font-weight: 600; color: #475569; }
    .cp-comment-body { font-size: 13px; color: #334155; line-height: 1.5; white-space: pre-wrap; }
    .cp-comment-resolved { opacity: 0.55; }
    .cp-comment-actions { display: flex; gap: 8px; margin-top: 8px; }
    .cp-comment-action-btn { background: none; border: none; cursor: pointer; font-size: 11px; color: #64748b; padding: 0; }
    .cp-comment-action-btn:hover { color: #1e293b; text-decoration: underline; }

    /* Inline 'Suggest edit' floating pill (shown when caption text is selected) */
    .cp-suggest-float {
        position: fixed;
        z-index: 10005;
        display: none;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: #18181b;
        color: #fff;
        border: none;
        border-radius: 18px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        font-family: inherit;
        animation: cp-float-in 0.12s ease-out;
    }
    .cp-suggest-float:hover { background: #27272a; }
    .cp-suggest-float.visible { display: inline-flex; }
    @keyframes cp-float-in { from { opacity: 0; transform: translate(-50%, -4px) scale(0.94); } to { opacity: 1; transform: translate(-50%, 0) scale(1); } }

    /* Highlight of the caption substring that has a pending suggestion.
       Textarea can't carry HTML, so we underline via text-decoration on a
       ghost <span> rendered inside the IG mockup when no edit is happening.
       Simpler fallback: put a faint bottom-border on the original text in
       the diff card via class .cp-suggestion-pending. */
    .cp-suggestion-pending { box-shadow: inset 0 -2px 0 rgba(99,102,241,0.35); }

    /* Suggestion diff */
    .cp-suggestion-diff { font-size: 12px; line-height: 1.5; background: #f8fafc; border-radius: 6px; padding: 8px 10px; margin-top: 6px; }
    .cp-suggestion-from { color: #991b1b; text-decoration: line-through; text-decoration-color: rgba(153,27,27,0.4); }
    .cp-suggestion-to { color: #065f46; font-weight: 500; }
    .cp-suggestion-arrow { color: #cbd5e1; margin: 0 6px; }
    .cp-suggestion-status { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 6px; border-radius: 4px; }
    .cp-suggestion-status.resolved { background: #dcfce7; color: #166534; }
    .cp-suggestion-status.rejected { background: #fef2f2; color: #991b1b; }
</style>

<script>
    let composerState = { postId: null, platforms: [], mediaIds: [], labelIds: [], campaignId: null, contentType: 'post', isEditing: false, mediaItems: [] };

    // ── Content Type ──
    function switchContentType(type) {
        composerState.contentType = type;
        document.querySelectorAll('.cp-type-tab').forEach(t => t.classList.toggle('active', t.dataset.type === type));
        document.getElementById('composerPostReels').style.display = type === 'story' ? 'none' : 'flex';
        document.getElementById('composerStory').style.display = type === 'story' ? 'flex' : 'none';
    }

    // ── Platform ──
    function togglePlatformBtn(btn) {
        const p = btn.dataset.platform;
        btn.classList.toggle('active');
        if (btn.classList.contains('active')) {
            if (!composerState.platforms.includes(p)) composerState.platforms.push(p);
        } else {
            composerState.platforms = composerState.platforms.filter(x => x !== p);
        }
    }

    // ── Open / Close ──
    function openComposer(postId = null, date = null) {
        composerState = { postId, platforms: [], mediaIds: [], labelIds: [], campaignId: null, contentType: 'post', isEditing: !!postId, mediaItems: [], status: null };
        document.getElementById('postComposerOverlay').style.display = 'block';
        // Delete button -- shfaqet vetem kur editojme post ekzistues.
        // Per post te ri (postId=null) fshihet sepse s'ka cfare te fshish.
        const delBtn = document.getElementById('composerDeleteBtn');
        if (delBtn) delBtn.style.display = postId ? 'inline-flex' : 'none';
        document.getElementById('composerContent').value = '';
        document.getElementById('composerMediaPreview').style.display = 'none';
        document.getElementById('composerMediaEmpty').style.display = 'flex';
        document.getElementById('composerMediaStrip').style.display = 'none';
        document.getElementById('composerMediaGrid').innerHTML = '';
        document.getElementById('composerMediaMain').innerHTML = '';
        showPhotoToolbars(false);
        // Reset approval
        const approvalStatus = document.getElementById('composerApprovalStatus');
        const approveBtn = document.getElementById('composerApproveBtn');
        if (approvalStatus) { approvalStatus.textContent = 'Not approved yet'; approvalStatus.style.color = '#94a3b8'; }
        if (approveBtn) { approveBtn.style.background = '#fff'; approveBtn.style.borderColor = '#d1d5db'; approveBtn.innerHTML = '<iconify-icon icon="heroicons-outline:check" width="12" style="color:#9ca3af;"></iconify-icon>'; }
        // Reset feedback
        const commentsList = document.getElementById('feedbackCommentsList');
        if (commentsList) commentsList.innerHTML = '<div style="text-align:center; padding:40px 16px;"><p style="font-size:13px; font-weight:500; color:#64748b; margin:0 0 4px;">No comments yet</p><p style="font-size:12px; color:#94a3b8; margin:0;">Start the conversation by leaving the first comment.</p></div>';
        const commentInput = document.getElementById('feedbackCommentInput');
        if (commentInput) commentInput.value = '';
        setScheduleInputs(date || '');
        document.querySelectorAll('.cp-plat-btn').forEach(b => b.classList.remove('active'));
        switchContentType('post');
        resetStoryFrame();
        if (postId) loadPostForEditing(postId);
    }

    function closeComposer() {
        document.getElementById('postComposerOverlay').style.display = 'none';
    }

    // ── Delete Post ──
    // I aksesueshem permes butonit "Fshi" ne header-in e composer-it.
    // Backend (DELETE /marketing/planner/api/posts/{id}) gating-on perms
    // (CONTENT_PLANNER_DELETE) -- por UI gjithashtu e fsheh butonin per
    // posts qe nuk lejohen (scheduled/published/failed) qe te shmangim
    // confusion. Pas DELETE-it: closeComposer + refreshGrid (nese funksioni
    // ekziston, p.sh. te grid view) ose location.reload().
    async function deletePost() {
        if (!composerState.postId) return;
        if (!confirm('Te hiqet ky post? Ky veprim nuk mund te zhbehet.')) return;
        try {
            const res = await fetch(`{{ url('/marketing/planner/api/posts') }}/${composerState.postId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            closeComposer();
            if (typeof refreshGrid === 'function') refreshGrid();
            else if (typeof window.fc?.refetchEvents === 'function') window.fc.refetchEvents();
            else window.location.reload();
        } catch (err) {
            alert('Fshirja deshtoi: ' + err.message);
        }
    }

    // ── Load Post ──
    async function loadPostForEditing(postId) {
        try {
            const res = await fetch(`{{ url('/marketing/planner/api/posts') }}/${postId}`);
            const post = await res.json();
            // Save status (delete button-i tashme i dukshem nga openComposer
            // sepse postId eshte set; backend gate-on permission-in CONTENT_PLANNER_DELETE).
            composerState.status = post.status || null;
            document.getElementById('composerContent').value = post.content || '';
            setScheduleInputs(post.scheduled_at ? post.scheduled_at.slice(0, 16) : '');
            if (post.content_type) switchContentType(post.content_type);
            (post.platforms || []).forEach(p => {
                const btn = document.querySelector(`.cp-plat-btn[data-platform="${p.platform}"]`);
                if (btn) { btn.classList.add('active'); composerState.platforms.push(p.platform); }
            });
            composerState.mediaIds = (post.media || []).map(m => m.id);
            if (post.media?.length) {
                post.media.forEach(m => {
                    composerState.mediaItems.push(m);
                    addMediaToComposer(m);
                });
            }
            composerState.labelIds = (post.labels || []).map(l => l.id);
            if (post.campaign_id) composerState.campaignId = post.campaign_id;

            // Pull in existing comments + suggestions so the side panel is
            // populated instead of showing a stale 'No comments yet' message.
            refreshFeedback();
        } catch (e) { console.error('Failed to load post:', e); }
    }

    // ── Media carousel ──
    //
    // Slides live inside #composerMediaMain (a flex row). The viewport
    // (#composerMediaViewport) is overflow:hidden and the track is moved
    // with translateX(-index * 100%). Swipe is wired to touch and mouse.
    // All DOM is built with createElement (no innerHTML + user data) so
    // media URLs from the API cannot inject markup.
    const carousel = {
        index: 0,
        dragging: false,
        startX: 0,
        currentDx: 0,
        width: 0,
    };

    function buildSlideElement(media) {
        const slide = document.createElement('div');
        slide.className = 'cp-slide';
        slide.dataset.mediaId = String(media.id || '');
        slide.style.cssText = 'flex:0 0 100%;width:100%;position:relative;';

        const isVideo = (media.mime_type || '').startsWith('video/');
        const el = document.createElement(isVideo ? 'video' : 'img');
        el.src = isVideo ? media.url : (media.thumbnail_url || media.url);
        el.style.cssText = 'width:100%;display:block;' + (isVideo ? '' : 'pointer-events:none;');
        if (isVideo) {
            el.muted = true; el.autoplay = true; el.loop = true; el.playsInline = true;
        } else {
            el.alt = '';
            el.draggable = false;
        }
        slide.appendChild(el);

        // Cover picker affordance — only for video. Reels covers what
        // followers see on the IG grid before the video plays; without
        // this, Meta auto-picks (often a black first frame).
        if (isVideo) {
            const coverBtn = document.createElement('button');
            coverBtn.type = 'button';
            coverBtn.className = 'cp-cover-btn';
            coverBtn.dataset.mediaId = String(media.id || '');
            coverBtn.style.cssText = 'position:absolute;top:8px;right:8px;background:rgba(15,23,42,0.78);color:#fff;border:none;border-radius:6px;padding:5px 10px;font-size:11px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:4px;backdrop-filter:blur(4px);z-index:5;';
            const ic = document.createElement('iconify-icon');
            ic.setAttribute('icon', 'heroicons-outline:photo');
            ic.setAttribute('width', '12');
            coverBtn.appendChild(ic);
            const lbl = document.createElement('span');
            lbl.textContent = media.cover_path ? 'Cover ✓' : 'Cover';
            coverBtn.appendChild(lbl);
            coverBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                if (typeof window.openCoverPicker === 'function') {
                    window.openCoverPicker(media);
                }
            });
            slide.appendChild(coverBtn);
        }

        return slide;
    }

    function renderCarousel() {
        const main = document.getElementById('composerMediaMain');
        const items = composerState.mediaItems;

        // Clear and rebuild slides with DOM methods.
        while (main.firstChild) main.removeChild(main.firstChild);
        items.forEach((m) => main.appendChild(buildSlideElement(m)));

        carousel.index = Math.min(carousel.index, Math.max(0, items.length - 1));
        applyCarouselTransform(false);
        updateCarouselChrome();
    }

    function applyCarouselTransform(animate) {
        const main = document.getElementById('composerMediaMain');
        main.style.transition = animate === false ? 'none' : 'transform 0.3s ease';
        main.style.transform = `translateX(-${carousel.index * 100}%)`;
        if (animate === false) {
            // Re-enable transition on next frame so future moves animate.
            requestAnimationFrame(() => { main.style.transition = 'transform 0.3s ease'; });
        }
    }

    function updateCarouselChrome() {
        const count = composerState.mediaItems.length;
        const multi = count > 1;

        document.getElementById('composerCarouselPrev').style.display = multi ? 'flex' : 'none';
        document.getElementById('composerCarouselNext').style.display = multi ? 'flex' : 'none';

        const counter = document.getElementById('composerCarouselCounter');
        counter.style.display = multi ? 'block' : 'none';
        counter.textContent = `${carousel.index + 1}/${count}`;

        const dotsHost = document.getElementById('composerCarouselDots');
        dotsHost.style.display = multi ? 'flex' : 'none';
        while (dotsHost.firstChild) dotsHost.removeChild(dotsHost.firstChild);
        if (multi) {
            for (let i = 0; i < count; i++) {
                const dot = document.createElement('div');
                dot.style.cssText = `width:6px;height:6px;border-radius:50%;background:${i === carousel.index ? '#fff' : 'rgba(255,255,255,0.5)'};box-shadow:0 0 2px rgba(0,0,0,0.3);`;
                dotsHost.appendChild(dot);
            }
        }

        // Thumbnail strip below the card — keeps click-to-jump.
        const strip = document.getElementById('composerMediaStrip');
        const grid = document.getElementById('composerMediaGrid');
        strip.style.display = count > 1 ? 'block' : 'none';
        while (grid.firstChild) grid.removeChild(grid.firstChild);
        composerState.mediaItems.forEach((m, i) => {
            const thumb = document.createElement('div');
            thumb.style.cssText = `width:48px;height:48px;border-radius:6px;overflow:hidden;flex-shrink:0;position:relative;cursor:pointer;border:2px solid ${i === carousel.index ? '#6366f1' : 'transparent'};`;
            const tImg = document.createElement('img');
            tImg.src = m.thumbnail_url || m.url;
            tImg.draggable = false;
            tImg.style.cssText = 'width:100%;height:100%;object-fit:cover;';
            thumb.appendChild(tImg);
            thumb.onclick = () => showMediaAtIndex(i);
            grid.appendChild(thumb);
        });
    }

    function addMediaToComposer(media) {
        const preview = document.getElementById('composerMediaPreview');
        const empty = document.getElementById('composerMediaEmpty');

        preview.style.display = 'block';
        empty.style.display = 'none';
        showPhotoToolbars(true);

        // `composerState.mediaItems` already contains the new media (pushed by caller).
        // If this is the first slide, keep index at 0; otherwise jump to the newest.
        if (composerState.mediaItems.length === 1) {
            carousel.index = 0;
        } else if (composerState.mediaItems[composerState.mediaItems.length - 1]?.id === media.id) {
            carousel.index = composerState.mediaItems.length - 1;
        }

        renderCarousel();
        ensureCarouselWired();
    }

    function showMediaAtIndex(index) {
        const count = composerState.mediaItems.length;
        if (index < 0 || index >= count) return;
        carousel.index = index;
        applyCarouselTransform(true);
        updateCarouselChrome();
    }

    function carouselNext() { showMediaAtIndex(carousel.index + 1); }
    function carouselPrev() { showMediaAtIndex(carousel.index - 1); }

    function removeAllMedia() {
        composerState.mediaIds = [];
        composerState.mediaItems = [];
        carousel.index = 0;
        document.getElementById('composerMediaPreview').style.display = 'none';
        document.getElementById('composerMediaEmpty').style.display = 'flex';
        document.getElementById('composerMediaStrip').style.display = 'none';
        const main = document.getElementById('composerMediaMain');
        const grid = document.getElementById('composerMediaGrid');
        while (main.firstChild) main.removeChild(main.firstChild);
        while (grid.firstChild) grid.removeChild(grid.firstChild);
        updateCarouselChrome();
        showPhotoToolbars(false);
    }

    // Wire swipe once the viewport exists. Idempotent — guarded by data attr.
    function ensureCarouselWired() {
        const viewport = document.getElementById('composerMediaViewport');
        if (!viewport || viewport.dataset.wired === '1') return;
        viewport.dataset.wired = '1';

        const track = document.getElementById('composerMediaMain');

        const onStart = (x) => {
            if (composerState.mediaItems.length < 2) return;
            carousel.dragging = true;
            carousel.startX = x;
            carousel.currentDx = 0;
            carousel.width = viewport.clientWidth;
            track.style.transition = 'none';
        };

        const onMove = (x) => {
            if (!carousel.dragging) return;
            carousel.currentDx = x - carousel.startX;
            const base = -carousel.index * carousel.width;
            track.style.transform = `translate3d(${base + carousel.currentDx}px, 0, 0)`;
        };

        const onEnd = () => {
            if (!carousel.dragging) return;
            carousel.dragging = false;
            track.style.transition = 'transform 0.3s ease';
            const threshold = carousel.width * 0.18; // ~18% swipe = advance
            if (carousel.currentDx < -threshold) {
                carousel.index = Math.min(composerState.mediaItems.length - 1, carousel.index + 1);
            } else if (carousel.currentDx > threshold) {
                carousel.index = Math.max(0, carousel.index - 1);
            }
            track.style.transform = `translateX(-${carousel.index * 100}%)`;
            carousel.currentDx = 0;
            updateCarouselChrome();
        };

        // Touch (mobile)
        viewport.addEventListener('touchstart', (e) => onStart(e.touches[0].clientX), { passive: true });
        viewport.addEventListener('touchmove',  (e) => onMove(e.touches[0].clientX),  { passive: true });
        viewport.addEventListener('touchend',   onEnd);
        viewport.addEventListener('touchcancel', onEnd);

        // Mouse drag (desktop)
        viewport.addEventListener('mousedown', (e) => { onStart(e.clientX); e.preventDefault(); });
        window.addEventListener('mousemove',   (e) => onMove(e.clientX));
        window.addEventListener('mouseup',     onEnd);

        // Keyboard (when viewport has focus)
        viewport.tabIndex = 0;
        viewport.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft')  { e.preventDefault(); carouselPrev(); }
            if (e.key === 'ArrowRight') { e.preventDefault(); carouselNext(); }
        });
    }

    async function handleMediaSelect(files) {
        for (const file of files) await uploadMedia(file);
    }

    async function uploadMedia(file) {
        const formData = new FormData();
        formData.append('file', file);
        try {
            const res = await fetch('{{ route("marketing.planner.api.media.upload") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: formData,
            });
            if (!res.ok) { alert('Upload failed: Server error ' + res.status); return; }
            const media = await res.json();
            composerState.mediaIds.push(media.id);
            composerState.mediaItems.push(media);
            addMediaToComposer(media);
        } catch (e) { alert('Upload failed: ' + e.message); }
    }

    // ── Story ──
    function handleStoryDrop(event) {
        event.preventDefault();
        document.getElementById('storyFrame').classList.remove('border-primary-400', 'bg-primary-50');
        handleStoryMedia(event.dataTransfer.files);
    }

    function handleStoryMedia(files) {
        if (!files.length) return;
        const file = files[0];
        const frame = document.getElementById('storyFrame');
        const content = document.getElementById('storyFrameContent');
        const reader = new FileReader();
        reader.onload = function(e) {
            frame.classList.add('has-media');
            content.innerHTML = file.type.startsWith('video/')
                ? `<video src="${e.target.result}" muted autoplay loop playsinline style="width:100%;height:100%;object-fit:cover;border-radius:16px;"></video>`
                : `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:16px;">`;
        };
        reader.readAsDataURL(file);
        uploadStoryMedia(file);
    }

    async function uploadStoryMedia(file) {
        const formData = new FormData();
        formData.append('file', file);
        try {
            const res = await fetch('{{ route("marketing.planner.api.media.upload") }}', {
                method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }, body: formData,
            });
            if (!res.ok) return;
            const media = await res.json();
            composerState.mediaIds.push(media.id);
        } catch (e) { console.error(e); }
    }

    function resetStoryFrame() {
        const frame = document.getElementById('storyFrame');
        frame.classList.remove('has-media');
        document.getElementById('storyFrameContent').innerHTML = `
            <iconify-icon icon="heroicons-outline:cloud-arrow-up" width="24" class="text-slate-300"></iconify-icon>
            <p class="text-[11px] text-slate-400 text-center leading-snug m-0">Drop media from your library<br>or from computer</p>`;
    }

    function openMediaLibraryPicker() {
        // Prefer the embedded MediaPicker modal when available (Media Library v2).
        // Falls back to the legacy pop-up window if the partial wasn't included.
        if (window.MediaPicker && typeof window.MediaPicker.open === 'function') {
            window.MediaPicker.open({
                multiple: true,
                defaultFolder: '__all',
                onConfirm: (mediaArray) => {
                    if (!mediaArray || !mediaArray.length) return;
                    mediaArray.forEach(m => {
                        if (!composerState.mediaIds.includes(m.id)) {
                            composerState.mediaIds.push(m.id);
                            composerState.mediaItems.push(m);
                            addMediaToComposer(m);
                        }
                    });
                },
            });
            return;
        }
        window.open('{{ route("marketing.planner.media") }}?picker=1', 'mediaLibrary', 'width=900,height=600');
    }

    window.addEventListener('message', function(event) {
        if (event.data?.type === 'media-selected' && event.data.media) {
            const m = event.data.media;
            if (!composerState.mediaIds.includes(m.id)) {
                composerState.mediaIds.push(m.id);
                composerState.mediaItems.push(m);
                addMediaToComposer(m);
            }
        }
    });

    // ── Save ──
    async function savePost(status) {
        let scheduledAt = document.getElementById('composerScheduledAt').value;
        if (status === 'scheduled' && !scheduledAt) {
            const now = new Date(); now.setHours(now.getHours() + 1); now.setMinutes(0, 0, 0);
            const pad = n => String(n).padStart(2, '0');
            scheduledAt = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
            setScheduleInputs(scheduledAt);
        }

        const platforms = composerState.platforms.length > 0 ? composerState.platforms : ['facebook', 'instagram', 'tiktok'];
        const data = {
            content: document.getElementById('composerContent').value,
            content_type: composerState.contentType,
            platform: platforms.length > 1 ? 'multi' : platforms[0],
            platforms, scheduled_at: scheduledAt || null, status,
            media_ids: composerState.mediaIds, label_ids: composerState.labelIds,
            campaign_id: composerState.campaignId,
        };

        try {
            const url = composerState.postId ? `{{ url('/marketing/planner/api/posts') }}/${composerState.postId}` : '{{ route("marketing.planner.api.posts.store") }}';
            const res = await fetch(url, {
                method: composerState.postId ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify(data),
            });
            if (!res.ok) { const err = await res.json(); alert('Error: ' + (err.message || JSON.stringify(err.errors || err))); return; }
            closeComposer();
            if (typeof refreshCalendar === 'function') refreshCalendar();
            if (typeof refreshList === 'function') refreshList();
            if (typeof refreshGrid === 'function') refreshGrid();
        } catch (e) { alert('Failed to save: ' + e.message); }
    }

    // (deletePost() definohet me lart -- versioni i ri me confirmation,
    // status gating, error handling, dhe fallback refresh.)

    // ── Feedback (Comments + Suggestions) ──
    //
    // Two tabs share the right-hand panel. Comments live on content_comments
    // and are free-form; Suggestions live on content_suggestions and carry
    // original_text + suggested_text so the author can accept a wording
    // change with one click. Both talk to existing planner API endpoints.

    const _cpEsc = document.createElement('div');
    function cpEsc(s) {
        if (s == null) return '';
        _cpEsc.textContent = String(s);
        return _cpEsc.innerHTML;
    }

    async function refreshFeedback() {
        if (!composerState.postId) return;
        await Promise.all([loadComments(), loadSuggestions()]);
    }

    // ── Comments ──
    async function addComment() {
        if (!composerState.postId) return alert('Save the post first.');
        const input = document.getElementById('feedbackCommentInput');
        const body = (input?.value || '').trim();
        if (!body) return;
        try {
            const res = await fetch('{{ route("marketing.planner.api.comments.store") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ content_post_id: composerState.postId, body }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            input.value = '';
            loadComments();
        } catch (e) {
            alert('Could not post comment: ' + e.message);
        }
    }

    async function loadComments() {
        if (!composerState.postId) return;
        try {
            const url = '{{ url('/marketing/planner/api/posts') }}/' + encodeURIComponent(composerState.postId);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            renderComments(Array.isArray(data.comments) ? data.comments : []);
        } catch (e) { /* silent */ }
    }

    function renderComments(comments) {
        const host = document.getElementById('feedbackCommentsList');
        host.textContent = '';

        const count = comments.length;
        const badge = document.getElementById('cp-tab-comments-count');
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline-block' : 'none';

        if (!count) {
            const empty = document.createElement('div');
            empty.className = 'cp-feedback-empty';
            empty.style.cssText = 'text-align:center; padding:40px 16px;';
            empty.innerHTML = '<p style="font-size:13px; font-weight:500; color:#64748b; margin:0 0 4px;">No comments yet</p><p style="font-size:12px; color:#94a3b8; margin:0;">Start the conversation by leaving the first comment.</p>'; // eslint-disable-line
            host.appendChild(empty);
            return;
        }

        comments.forEach(c => host.appendChild(buildCommentCard(c)));
    }

    function buildCommentCard(c) {
        const card = document.createElement('div');
        card.className = 'cp-comment' + (c.resolved_at ? ' cp-comment-resolved' : '');

        const meta = document.createElement('div');
        meta.className = 'cp-comment-meta';
        const who = document.createElement('span');
        who.className = 'cp-comment-author';
        who.textContent = c.user?.name || c.user?.full_name || 'Someone';
        const when = document.createElement('span');
        when.textContent = formatFeedbackDate(c.created_at);
        meta.append(who, document.createTextNode(' · '), when);
        card.appendChild(meta);

        const body = document.createElement('div');
        body.className = 'cp-comment-body';
        body.textContent = c.body || '';
        card.appendChild(body);

        // Resolve / reopen action
        const actions = document.createElement('div');
        actions.className = 'cp-comment-actions';
        const resolveBtn = document.createElement('button');
        resolveBtn.type = 'button';
        resolveBtn.className = 'cp-comment-action-btn';
        resolveBtn.textContent = c.resolved_at ? 'Reopen' : 'Resolve';
        resolveBtn.onclick = async () => {
            try {
                await fetch('{{ url('/marketing/planner/api/comments') }}/' + c.id + '/resolve', {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                });
                loadComments();
            } catch (e) { /* silent */ }
        };
        actions.appendChild(resolveBtn);
        card.appendChild(actions);
        return card;
    }

    // ── Suggestions (selection-based, Planable-like) ──
    //
    // The flow:
    //   1. User selects text in the caption <textarea>.
    //   2. On mouseup / keyup, if there IS a selection, a small pill
    //      "Suggest edit" appears above the selection (position:fixed).
    //   3. Clicking the pill opens a popover anchored to the same place.
    //      The popover shows the selected text (read-only, struck through)
    //      and a single "Replace with…" input. Enter = submit, Esc = close.
    //   4. On submit, POST to /api/posts/{id}/suggestions and reload the
    //      right-panel list. The Suggestions tab is auto-switched active.
    //
    // No separate form exists in the right panel any more — the right
    // panel is read-only (a list of past suggestions with Accept/Reject).

    const suggestFloat = {
        original: '',
        selStart: 0,
        selEnd: 0,
        // Last known position of the floating button (for the popover anchor)
        anchorX: 0,
        anchorY: 0,
    };

    function _updateSuggestFloatFromSelection() {
        const ta = document.getElementById('composerContent');
        const btn = document.getElementById('suggestFloatBtn');
        if (!ta || !btn) return;

        const start = ta.selectionStart;
        const end   = ta.selectionEnd;

        // No selection OR composer not open -> hide.
        const overlay = document.getElementById('postComposerOverlay');
        if (!overlay || overlay.style.display === 'none') {
            btn.classList.remove('visible');
            return;
        }

        if (start === end) {
            btn.classList.remove('visible');
            return;
        }

        const selected = (ta.value || '').substring(start, end).trim();
        if (!selected) {
            btn.classList.remove('visible');
            return;
        }

        // Compute a reasonable floating position near the selection.
        // Textareas don't expose per-character coords, so we anchor above
        // the textarea at the horizontal center of the caret approximation.
        const rect = ta.getBoundingClientRect();
        suggestFloat.original = selected;
        suggestFloat.selStart = start;
        suggestFloat.selEnd   = end;
        suggestFloat.anchorX  = rect.left + Math.min(rect.width - 40, Math.max(40, rect.width / 2));
        suggestFloat.anchorY  = rect.top - 10;

        btn.style.left = suggestFloat.anchorX + 'px';
        btn.style.top  = suggestFloat.anchorY + 'px';
        btn.style.transform = 'translate(-50%, -100%)';
        btn.classList.add('visible');
    }

    function openSuggestFloatPopover() {
        if (!composerState.postId) {
            alert('Save the post first.');
            return;
        }
        if (!suggestFloat.original) return;

        const pop = document.getElementById('suggestFloatPopover');
        const orig = document.getElementById('suggestFloatOriginal');
        const rep  = document.getElementById('suggestFloatReplacement');
        orig.textContent = suggestFloat.original;
        rep.value = '';

        // Show first so we can measure.
        pop.style.display = 'block';
        pop.style.visibility = 'hidden';
        pop.style.left = '0px';
        pop.style.top = '0px';
        pop.style.transform = 'none';

        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const pw = pop.offsetWidth  || 260;
        const ph = pop.offsetHeight || 180;
        const margin = 12;

        // Horizontal: center under the anchor, clamp inside the viewport.
        let left = suggestFloat.anchorX - pw / 2;
        left = Math.max(margin, Math.min(vw - pw - margin, left));

        // Vertical: prefer BELOW the anchor; flip ABOVE when it doesn't fit.
        const spaceBelow = vh - (suggestFloat.anchorY + 28);
        let top;
        if (spaceBelow >= ph + margin) {
            top = suggestFloat.anchorY + 28;
        } else {
            top = suggestFloat.anchorY - ph - 12; // above the anchor
            if (top < margin) top = margin; // never off-screen top
        }

        pop.style.left = left + 'px';
        pop.style.top  = top + 'px';
        pop.style.visibility = 'visible';
        setTimeout(() => rep.focus(), 20);

        // Hide the pill while the popover is open.
        document.getElementById('suggestFloatBtn').classList.remove('visible');
    }

    function closeSuggestFloatPopover() {
        document.getElementById('suggestFloatPopover').style.display = 'none';
    }

    async function submitSuggestFloat() {
        const replacement = (document.getElementById('suggestFloatReplacement').value || '').trim();
        if (!replacement) return;
        const original = suggestFloat.original;
        if (!original) return;

        try {
            const res = await fetch('{{ url('/marketing/planner/api/posts') }}/' + composerState.postId + '/suggestions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ original_text: original, suggested_text: replacement }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
        } catch (e) {
            alert('Could not save suggestion: ' + e.message);
            return;
        }

        closeSuggestFloatPopover();
        loadSuggestions();

        // Switch the side panel to the Suggestions tab so the new card is visible.
        const suggestionsTab = document.querySelector('.cp-feedback-tab[data-feedback-tab="suggestions"]');
        if (suggestionsTab) switchFeedbackTab('suggestions', suggestionsTab);
    }

    // Wire selection listeners once on DOM ready. Using capture-mouseup on
    // document so it fires even when the user releases outside the textarea.
    document.addEventListener('DOMContentLoaded', () => {
        const ta = document.getElementById('composerContent');
        if (!ta) return;
        ['mouseup', 'keyup', 'select', 'focus'].forEach(evt => {
            ta.addEventListener(evt, _updateSuggestFloatFromSelection);
        });
        // Hide the pill if the user clicks elsewhere (but not on the pill
        // itself or the popover).
        document.addEventListener('mousedown', (e) => {
            const btn = document.getElementById('suggestFloatBtn');
            const pop = document.getElementById('suggestFloatPopover');
            if (!btn) return;
            if (e.target === btn || btn.contains(e.target)) return;
            if (pop && pop.contains(e.target)) return;
            if (e.target === ta || ta.contains(e.target)) return;
            btn.classList.remove('visible');
        });
    });

    async function loadSuggestions() {
        if (!composerState.postId) return;
        try {
            const res = await fetch('{{ url('/marketing/planner/api/posts') }}/' + composerState.postId + '/suggestions', {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) return;
            const data = await res.json();
            renderSuggestions(Array.isArray(data) ? data : []);
        } catch (e) { /* silent */ }
    }

    function renderSuggestions(items) {
        const host = document.getElementById('feedbackSuggestionsList');
        host.textContent = '';

        const open = items.filter(s => !s.resolved_at).length;
        const badge = document.getElementById('cp-tab-suggestions-count');
        badge.textContent = open;
        badge.style.display = open > 0 ? 'inline-block' : 'none';

        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'cp-feedback-empty';
            empty.style.cssText = 'text-align:center; padding:40px 16px;';
            empty.innerHTML = '<p style="font-size:13px; font-weight:500; color:#64748b; margin:0 0 4px;">No suggestions yet</p><p style="font-size:12px; color:#94a3b8; margin:0;">Propose a wording change and the author can accept it in one click.</p>'; // eslint-disable-line
            host.appendChild(empty);
            return;
        }

        items.forEach(s => host.appendChild(buildSuggestionCard(s)));
    }

    function buildSuggestionCard(s) {
        const card = document.createElement('div');
        card.className = 'cp-suggestion';

        const meta = document.createElement('div');
        meta.className = 'cp-comment-meta';
        const who = document.createElement('span');
        who.className = 'cp-comment-author';
        who.textContent = s.author?.name || s.author?.full_name || 'Someone';
        const when = document.createElement('span');
        when.textContent = formatFeedbackDate(s.created_at);
        meta.append(who, document.createTextNode(' · '), when);

        if (s.resolved_at) {
            const tag = document.createElement('span');
            tag.className = 'cp-suggestion-status ' + (s.accepted ? 'resolved' : 'rejected');
            tag.textContent = s.accepted ? 'Accepted' : 'Rejected';
            tag.style.marginLeft = 'auto';
            meta.appendChild(tag);
        }
        card.appendChild(meta);

        const diff = document.createElement('div');
        diff.className = 'cp-suggestion-diff';
        const from = document.createElement('span');
        from.className = 'cp-suggestion-from';
        from.textContent = s.original_text || '';
        const arrow = document.createElement('span');
        arrow.className = 'cp-suggestion-arrow';
        arrow.textContent = '→';
        const to = document.createElement('span');
        to.className = 'cp-suggestion-to';
        to.textContent = s.suggested_text || '';
        diff.append(from, arrow, to);
        card.appendChild(diff);

        if (!s.resolved_at) {
            const actions = document.createElement('div');
            actions.className = 'cp-comment-actions';

            const accept = document.createElement('button');
            accept.type = 'button';
            accept.className = 'cp-comment-action-btn';
            accept.style.color = '#166534';
            accept.textContent = 'Accept';
            accept.onclick = () => resolveSuggestion(s.id, true);

            const reject = document.createElement('button');
            reject.type = 'button';
            reject.className = 'cp-comment-action-btn';
            reject.style.color = '#991b1b';
            reject.textContent = 'Reject';
            reject.onclick = () => resolveSuggestion(s.id, false);

            actions.append(accept, reject);
            card.appendChild(actions);
        }

        return card;
    }

    async function resolveSuggestion(id, accepted) {
        // Backend expects { status: 'accepted' | 'rejected' } and, when
        // accepted, performs the content swap on the post server-side —
        // we just need to mirror the change locally in the textarea.
        let resolvedData = null;
        try {
            const res = await fetch('{{ url('/marketing/planner/api/suggestions') }}/' + id + '/resolve', {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ status: accepted ? 'accepted' : 'rejected' }),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.message || ('HTTP ' + res.status));
            }
            resolvedData = await res.json().catch(() => ({}));
        } catch (e) {
            alert('Could not resolve suggestion: ' + e.message);
            return;
        }

        // Keep the caption textarea in sync with the server-side update.
        if (accepted) {
            const ta = document.getElementById('composerContent');
            const from = resolvedData.original_text;
            const to   = resolvedData.suggested_text;
            if (ta && from && to && ta.value.includes(from)) {
                ta.value = ta.value.replace(from, to);
            }
        }

        loadSuggestions();
    }

    function formatFeedbackDate(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (isNaN(d)) return '';
        return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    }

    // ── Char Count ──
    const charLimits = { facebook: 63206, instagram: 2200, tiktok: 2200 };
    function updateCharCount() {} // simplified — no visual bar in compact mode

    // ── Schedule Picker ──
    let schedState = { year: 2026, month: 3, selectedDate: null };

    function openSchedulePicker() {
        const modal = document.getElementById('schedPickerModal');
        const overlay = document.getElementById('schedPickerOverlay');
        modal.classList.remove('hidden');
        overlay.classList.remove('hidden');

        const existing = document.getElementById('composerScheduledAt').value;
        if (existing) {
            const d = new Date(existing);
            schedState = { year: d.getFullYear(), month: d.getMonth(), selectedDate: d.getDate() };
            document.getElementById('schedTimeInput').value = String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
        } else {
            const now = new Date();
            schedState = { year: now.getFullYear(), month: now.getMonth(), selectedDate: now.getDate() };
            document.getElementById('schedTimeInput').value = String((now.getHours() + 1) % 24).padStart(2,'0') + ':00';
        }
        renderSchedCalendar();
        updateSchedSummary();

        // Fetch + render engagement suggestions (async, doesn't block the picker)
        loadSchedSuggestions();
    }

    // ── Engagement forecast + top picks (Faza 4) ──
    async function loadSchedSuggestions() {
        // Render a neutral placeholder first so the panel never looks empty.
        renderSchedTopPicks([{ time: '11:00', score: 0 }, { time: '19:00', score: 0 }]);
        renderSchedForecast([
            { hour: 0, value: 0.1 }, { hour: 3, value: 0.05 }, { hour: 6, value: 0.1 }, { hour: 9, value: 0.5 },
            { hour: 12, value: 0.8 }, { hour: 15, value: 0.55 }, { hour: 18, value: 0.95 }, { hour: 21, value: 0.7 },
        ]);

        try {
            const platform = (composerState.platforms || [])[0];
            const url = new URL('{{ url('/marketing/planner/api/schedule/suggestions') }}', window.location.origin);
            if (platform) url.searchParams.set('platform', platform);
            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return; // keep placeholder
            const data = await res.json();
            if (Array.isArray(data.top_picks) && data.top_picks.length) {
                renderSchedTopPicks(data.top_picks);
            }
            if (Array.isArray(data.hourly_engagement) && data.hourly_engagement.length) {
                renderSchedForecast(data.hourly_engagement);
            }
        } catch (e) {
            // Silent — placeholder remains.
        }
    }

    function renderSchedTopPicks(picks) {
        const host = document.getElementById('schedTopPicks');
        while (host.firstChild) host.removeChild(host.firstChild);

        const current = document.getElementById('schedTimeInput').value;
        picks.slice(0, 2).forEach(p => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sched-top-pick' + (p.time === current ? ' selected' : '');
            const t = document.createElement('span');
            t.className = 'sched-top-pick-time';
            t.textContent = p.time;
            const s = document.createElement('span');
            s.className = 'sched-top-pick-score';
            s.textContent = p.score > 0.3 ? 'Peak' : 'Good';
            btn.append(t, s);
            btn.onclick = () => {
                document.getElementById('schedTimeInput').value = p.time;
                updateSchedSummary();
                // Re-render picks to update the selected state.
                renderSchedTopPicks(picks);
            };
            host.appendChild(btn);
        });
    }

    function renderSchedForecast(bars) {
        const host = document.getElementById('schedForecast');
        while (host.firstChild) host.removeChild(host.firstChild);

        const currentTime = document.getElementById('schedTimeInput').value || '12:00';
        const currentHour = parseInt(currentTime.split(':')[0], 10);
        const currentBucket = Math.floor(currentHour / 3) * 3;

        const maxValue = Math.max(...bars.map(b => Number(b.value) || 0), 0.01);

        bars.forEach(b => {
            const wrap = document.createElement('div');
            wrap.className = 'sched-bar';
            wrap.title = `${String(b.hour).padStart(2,'0')}:00–${String((b.hour+3)%24).padStart(2,'0')}:00${b.count != null ? ' · ' + b.count + ' posts' : ''}`;
            // Height as percentage of the chart height (max 100%).
            const pct = Math.max(6, Math.round((Number(b.value) || 0) / maxValue * 100));
            wrap.style.height = pct + '%';

            if (b.hour === currentBucket) {
                wrap.classList.add('near-current');
            } else if ((Number(b.value) || 0) >= 0.8) {
                wrap.classList.add('peak');
            } else {
                wrap.classList.add('dim');
            }

            wrap.addEventListener('click', () => {
                // Clicking a bar sets time to the middle of its bucket.
                const mid = Math.min(23, b.hour + 1);
                document.getElementById('schedTimeInput').value = String(mid).padStart(2,'0') + ':00';
                updateSchedSummary();
                renderSchedForecast(bars);
            });

            host.appendChild(wrap);
        });
    }

    function closeSchedulePicker() {
        document.getElementById('schedPickerModal').classList.add('hidden');
        document.getElementById('schedPickerOverlay').classList.add('hidden');
    }

    function schedNavMonth(dir) {
        schedState.month += dir;
        while (schedState.month > 11) { schedState.month -= 12; schedState.year++; }
        while (schedState.month < 0) { schedState.month += 12; schedState.year--; }
        renderSchedCalendar();
    }

    function renderSchedCalendar() {
        const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const shortMonths = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        document.getElementById('schedMonthLabel').textContent = `${shortMonths[schedState.month]}  ${schedState.year}`;
        const grid = document.getElementById('schedCalendarGrid');
        grid.innerHTML = '';
        let startDay = new Date(schedState.year, schedState.month, 1).getDay() - 1;
        if (startDay < 0) startDay = 6;
        const daysInMonth = new Date(schedState.year, schedState.month + 1, 0).getDate();
        const daysInPrev = new Date(schedState.year, schedState.month, 0).getDate();
        const today = new Date();
        const isThisMonth = today.getFullYear() === schedState.year && today.getMonth() === schedState.month;

        const cellBase = 'display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;font-size:11px;font-weight:500;cursor:pointer;margin:1px auto;transition:all 0.1s;';

        for (let i = startDay - 1; i >= 0; i--) {
            grid.innerHTML += `<span style="${cellBase}color:#cbd5e1;cursor:default;">${daysInPrev - i}</span>`;
        }
        for (let d = 1; d <= daysInMonth; d++) {
            const sel = d === schedState.selectedDate;
            const tod = isThisMonth && d === today.getDate();
            let style = cellBase;
            if (sel) style += 'background:#6366f1;color:#fff;font-weight:600;';
            else if (tod) style += 'background:#e0e7ff;color:#4338ca;font-weight:700;';
            else style += 'color:#334155;';
            grid.innerHTML += `<span style="${style}" onclick="schedSelectDay(${d})" onmouseover="if(!this.style.background||this.style.background==='')this.style.background='#f1f5f9'" onmouseout="if(this.style.background==='rgb(241, 245, 249)')this.style.background=''">${d}</span>`;
        }
        const rem = (7 - ((startDay + daysInMonth) % 7)) % 7;
        for (let i = 1; i <= rem; i++) {
            grid.innerHTML += `<span style="${cellBase}color:#cbd5e1;cursor:default;">${i}</span>`;
        }
    }

    function schedSelectDay(d) { schedState.selectedDate = d; renderSchedCalendar(); updateSchedSummary(); }
    function setQuickTime(t) { document.getElementById('schedTimeInput').value = t; updateSchedSummary(); }
    function getSchedTime() { return document.getElementById('schedTimeInput').value || '12:00'; }
    function updateSchedSummary() {
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        document.getElementById('schedSummary').textContent = schedState.selectedDate
            ? `${months[schedState.month]} ${schedState.selectedDate}, ${getSchedTime()}` : '';
    }
    function applySchedule() {
        if (!schedState.selectedDate) { alert('Select a date'); return; }
        const pad = n => String(n).padStart(2, '0');
        const time = getSchedTime();
        const val = `${schedState.year}-${pad(schedState.month + 1)}-${pad(schedState.selectedDate)}T${time}`;
        document.getElementById('composerScheduledAt').value = val;
        updateScheduleLabel();
        closeSchedulePicker();
    }
    function updateScheduleLabel() {
        const val = document.getElementById('composerScheduledAt').value;
        const label = document.getElementById('scheduleLabel');
        if (val) {
            const d = new Date(val);
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            label.textContent = `${months[d.getMonth()]} ${d.getDate()}, ${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`;
            label.style.color = '#334155';
        } else { label.textContent = 'Select date & time'; label.style.color = ''; }
    }
    function setScheduleInputs(dt) {
        document.getElementById('composerScheduledAt').value = dt || '';
        updateScheduleLabel();
    }

    // ── Photo toolbars ──
    function showPhotoToolbars(show) {
        const left = document.getElementById('composerPhotoToolbarLeft');
        const right = document.getElementById('composerPhotoToolbarRight');
        if (left) left.style.display = show ? 'flex' : 'none';
        if (right) right.style.display = show ? 'flex' : 'none';
    }

    function downloadComposerMedia() {
        // With the carousel, multiple slides live in #composerMediaMain.
        // Download the currently-shown slide instead of the first one.
        const slides = document.querySelectorAll('#composerMediaMain .cp-slide');
        const active = slides[carousel.index];
        if (!active) return;
        const src = active.querySelector('img')?.src || active.querySelector('video')?.src;
        if (src) { const a = document.createElement('a'); a.href = src; a.download = ''; a.click(); }
    }

    // ── Image editor (Filerobot, lazy-loaded) ──
    function editCurrentMedia() {
        const active = composerState.mediaItems[carousel.index];
        if (!active) return;
        if ((active.mime_type || '').startsWith('video/')) {
            alert('Editing video files is not supported yet.');
            return;
        }
        const url = active.url || active.thumbnail_url;
        if (!url) return;

        // openImageEditor is defined by the editor partial. It lazy-loads the
        // library on first call, opens the overlay, and invokes our callback
        // with a Blob + filename when the user saves their changes.
        if (typeof window.openImageEditor !== 'function') {
            alert('Editor not available — did you forget to include image-editor-modal?');
            return;
        }

        window.openImageEditor(url, async (blob, filename) => {
            // Upload the new blob as a fresh ContentMedia, then swap it into
            // the current carousel slot. The old media id is discarded from
            // composerState but the record itself remains on the server
            // (safe for undo / audit).
            const formData = new FormData();
            formData.append('file', blob, filename || 'edited.jpg');

            let media;
            try {
                const res = await fetch('{{ route("marketing.planner.api.media.upload") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                if (!res.ok) throw new Error('Upload failed: HTTP ' + res.status);
                media = await res.json();
            } catch (e) {
                alert('Could not save the edited image: ' + e.message);
                return;
            }

            // Swap in-place: the edited image replaces the original at the
            // same index, so the carousel position doesn't jump.
            composerState.mediaItems[carousel.index] = media;
            composerState.mediaIds[carousel.index] = media.id;
            renderCarousel();
        });
    }

    // ── Feedback panel ──
    function switchFeedbackTab(tab, btn) {
        document.querySelectorAll('.cp-feedback-tab').forEach(t => t.classList.remove('active'));
        if (btn) btn.classList.add('active');

        // Show the matching panel, hide the other.
        const panels = {
            comments: document.getElementById('cp-panel-comments'),
            suggestions: document.getElementById('cp-panel-suggestions'),
        };
        Object.entries(panels).forEach(([key, el]) => {
            if (!el) return;
            el.style.display = key === tab ? 'flex' : 'none';
        });
    }

    function toggleFeedbackPanel() {
        const panel = document.querySelector('#composerPostReels > div:last-child');
        if (panel) panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
    }

    // ── Approval ──
    function approvePost() {
        if (!composerState.postId) return;
        const btn = document.getElementById('composerApproveBtn');
        const status = document.getElementById('composerApprovalStatus');
        btn.style.background = '#10b981'; btn.style.borderColor = '#10b981';
        // Swap inner icon without innerHTML + user data — static markup is safe.
        while (btn.firstChild) btn.removeChild(btn.firstChild);
        const ic = document.createElement('iconify-icon');
        ic.setAttribute('icon', 'heroicons-solid:check');
        ic.setAttribute('width', '14');
        ic.style.color = '#fff';
        btn.appendChild(ic);
        status.textContent = 'Approved'; status.style.color = '#10b981';
    }

    // ── Device toggle (Planable-style) ──
    function setPreviewDevice(device) {
        const card = document.getElementById('composerPreviewCard');
        if (!card) return;
        card.classList.remove('device-phone', 'device-desktop');
        card.classList.add('device-' + device);
        document.querySelectorAll('.cp-device-btn').forEach(el => {
            const isActive = el.dataset.device === device;
            el.classList.toggle('active', isActive);
            el.style.background = isActive ? '#fff' : 'transparent';
            const ic = el.querySelector('iconify-icon');
            if (ic) ic.style.color = isActive ? '#18181b' : '#71717a';
        });
    }

    // ── Campaign / Labels dropdowns ──
    //
    // Real dropdowns that fetch from the existing planner API. Clicking the
    // pill opens a lightweight popover anchored to the pill; clicking any
    // item selects it and updates the pill label + composerState.
    async function openCampaignPicker() {
        const pill = document.getElementById('composerCampaignPill');
        const span = pill.querySelector('span[data-default]');

        // Toggle: if the popover is already open, close it.
        const existing = document.getElementById('cp-campaign-popover');
        if (existing) { existing.remove(); return; }

        const pop = buildPopover('cp-campaign-popover', pill);
        const list = document.createElement('div');
        list.style.cssText = 'max-height:240px; overflow-y:auto;';
        const loading = document.createElement('div');
        loading.style.cssText = 'padding:14px 16px; font-size:12px; color:#94a3b8;';
        loading.textContent = 'Loading campaigns…';
        list.appendChild(loading);
        pop.appendChild(list);

        // 'None' row up top
        const noneRow = buildPopoverRow('— None (clear)', () => {
            composerState.campaignId = null;
            span.textContent = span.dataset.default;
            pill.classList.remove('has-value');
            pop.remove();
        });
        noneRow.style.color = '#94a3b8';

        try {
            const res = await fetch('{{ route("marketing.planner.api.campaigns.index") }}', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            list.innerHTML = '';
            list.appendChild(noneRow);
            const items = Array.isArray(data) ? data : (data.data || data.campaigns || []);
            if (items.length === 0) {
                const empty = document.createElement('div');
                empty.style.cssText = 'padding:14px 16px; font-size:12px; color:#94a3b8;';
                empty.textContent = 'No campaigns yet';
                list.appendChild(empty);
            } else {
                items.forEach(c => {
                    const row = buildPopoverRow(c.name || ('Campaign #' + c.id), () => {
                        composerState.campaignId = c.id;
                        span.textContent = c.name || ('Campaign #' + c.id);
                        pill.classList.add('has-value');
                        pop.remove();
                    });
                    if (c.color) {
                        const dot = document.createElement('span');
                        dot.style.cssText = `width:10px; height:10px; border-radius:50%; background:${c.color}; flex-shrink:0;`;
                        row.prepend(dot);
                    }
                    list.appendChild(row);
                });
            }
        } catch (e) {
            list.textContent = '';
            const err = document.createElement('div');
            err.style.cssText = 'padding:14px 16px; font-size:12px; color:#ef4444;';
            err.textContent = 'Could not load campaigns';
            list.appendChild(err);
        }
    }

    async function openLabelsPicker() {
        const pill = document.getElementById('composerLabelsPill');
        const span = pill.querySelector('span[data-default]');

        const existing = document.getElementById('cp-labels-popover');
        if (existing) { existing.remove(); return; }

        const pop = buildPopover('cp-labels-popover', pill);
        const list = document.createElement('div');
        list.style.cssText = 'max-height:280px; overflow-y:auto;';
        const loading = document.createElement('div');
        loading.style.cssText = 'padding:14px 16px; font-size:12px; color:#94a3b8;';
        loading.textContent = 'Loading labels…';
        list.appendChild(loading);
        pop.appendChild(list);

        composerState.labelIds = composerState.labelIds || [];

        try {
            const res = await fetch('{{ route("marketing.planner.api.labels.index") }}', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            list.innerHTML = '';
            const items = Array.isArray(data) ? data : (data.data || data.labels || []);
            if (items.length === 0) {
                const empty = document.createElement('div');
                empty.style.cssText = 'padding:14px 16px; font-size:12px; color:#94a3b8;';
                empty.textContent = 'No labels yet';
                list.appendChild(empty);
                return;
            }

            items.forEach(l => {
                const row = buildPopoverRow(l.name || ('Label #' + l.id), null);
                row.style.justifyContent = 'space-between';
                if (l.color) {
                    const dot = document.createElement('span');
                    dot.style.cssText = `width:10px; height:10px; border-radius:50%; background:${l.color}; flex-shrink:0;`;
                    row.prepend(dot);
                }
                const mark = document.createElement('span');
                mark.style.cssText = 'font-size:14px; color:#6366f1;';
                const refreshMark = () => {
                    mark.textContent = composerState.labelIds.includes(l.id) ? '\u2713' : '';
                };
                refreshMark();
                row.appendChild(mark);

                row.onclick = () => {
                    if (composerState.labelIds.includes(l.id)) {
                        composerState.labelIds = composerState.labelIds.filter(id => id !== l.id);
                    } else {
                        composerState.labelIds.push(l.id);
                    }
                    refreshMark();
                    span.textContent = composerState.labelIds.length
                        ? composerState.labelIds.length + ' label' + (composerState.labelIds.length === 1 ? '' : 's')
                        : span.dataset.default;
                    pill.classList.toggle('has-value', composerState.labelIds.length > 0);
                };
                list.appendChild(row);
            });
        } catch (e) {
            list.textContent = '';
            const err = document.createElement('div');
            err.style.cssText = 'padding:14px 16px; font-size:12px; color:#ef4444;';
            err.textContent = 'Could not load labels';
            list.appendChild(err);
        }
    }

    // Small helper: build a popover anchored below a trigger element.
    function buildPopover(id, anchor) {
        const rect = anchor.getBoundingClientRect();
        const pop = document.createElement('div');
        pop.id = id;
        pop.style.cssText = `position:fixed; z-index:10001; background:#fff; border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,0.08); min-width:220px; top:${rect.bottom + 4}px; left:${rect.left}px; overflow:hidden;`;
        document.body.appendChild(pop);

        // Click-outside closes (after a tick to avoid catching the current click).
        setTimeout(() => {
            const onDocClick = (e) => {
                if (!pop.contains(e.target) && e.target !== anchor && !anchor.contains(e.target)) {
                    pop.remove();
                    document.removeEventListener('click', onDocClick);
                }
            };
            document.addEventListener('click', onDocClick);
        }, 0);

        return pop;
    }

    function buildPopoverRow(label, onClick) {
        const row = document.createElement('div');
        row.style.cssText = 'padding:8px 14px; font-size:13px; color:#1e293b; cursor:pointer; display:flex; align-items:center; gap:8px; transition:background 0.1s;';
        row.onmouseenter = () => row.style.background = '#f8fafc';
        row.onmouseleave = () => row.style.background = 'transparent';
        const text = document.createElement('span');
        text.style.cssText = 'flex:1;';
        text.textContent = label;
        row.appendChild(text);
        if (onClick) row.onclick = onClick;
        return row;
    }

    // ─────────────────────────────────────────────────────────────
    // Cover picker integration — modal lives in the shared partial
    // `_partials/cover-picker-modal.blade.php`. The slide's Cover
    // button calls window.openCoverPicker(media) directly. The
    // picker fires `flare:cover-updated` on save; we listen below
    // to refresh the in-place button + carousel state.
    // ─────────────────────────────────────────────────────────────
    window.addEventListener('flare:cover-updated', (e) => {
        if (!e || !e.detail) return;
        applyCoverUpdateToComposer(
            e.detail.mediaId,
            e.detail.coverPath,
            e.detail.coverUrl,
            e.detail.thumbnailUrl
        );
    });

    function applyCoverUpdateToComposer(mediaId, coverPath, coverUrl, thumbnailUrl) {
        // Update in-memory state so subsequent renders carry the new cover.
        const item = composerState.mediaItems.find(m => String(m.id) === String(mediaId));
        if (item) {
            item.cover_path = coverPath || null;
            item.cover_url = coverUrl || null;
            if (thumbnailUrl) item.thumbnail_url = thumbnailUrl;
        }
        // Update the slide's Cover button label without re-rendering the carousel
        // (preserves the user's current carousel index + video playback state).
        document.querySelectorAll('.cp-cover-btn[data-media-id="' + String(mediaId) + '"] span').forEach(s => {
            s.textContent = coverPath ? 'Cover ✓' : 'Cover';
        });
    }
</script>

@include('content-planner._partials.cover-picker-modal')
