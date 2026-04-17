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
                            <button onclick="document.getElementById('mediaFileInput').click()" style="width:30px; height:30px; border-radius:50%; background:rgba(0,0,0,0.5); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="Replace photo">
                                <iconify-icon icon="heroicons-outline:camera" width="14" style="color:#fff;"></iconify-icon>
                            </button>
                            <button onclick="editCurrentMedia()" type="button" style="width:30px; height:30px; border-radius:50%; background:rgba(0,0,0,0.5); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="Edit photo">
                                <iconify-icon icon="heroicons-outline:pencil" width="14" style="color:#fff;"></iconify-icon>
                            </button>
                            <button onclick="editCurrentMedia()" type="button" style="width:30px; height:30px; border-radius:50%; background:rgba(0,0,0,0.5); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="Filter / adjust">
                                <iconify-icon icon="heroicons-outline:adjustments-horizontal" width="14" style="color:#fff;"></iconify-icon>
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
                                <div id="composerMediaViewport" style="position:relative; overflow:hidden; touch-action:pan-y; user-select:none;">
                                    <div id="composerMediaMain" style="display:flex; transition:transform 0.3s ease; will-change:transform;"></div>
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
                            <div id="composerMediaEmpty" style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; cursor:pointer; aspect-ratio:1; background:#f8fafc;" onclick="document.getElementById('mediaFileInput').click()">
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
                    <input id="mediaFileInput" type="file" accept="image/*" multiple style="display:none;" onchange="handleMediaSelect(this.files); this.value='';">
                    <input id="mediaVideoInput" type="file" accept="video/*" multiple style="display:none;" onchange="handleMediaSelect(this.files); this.value='';">
                </div>
            </div>

            {{-- Right: Feedback sidebar --}}
            <div style="width:320px; flex-shrink:0; border-left:1px solid #e5e7eb; display:flex; flex-direction:column; background:#fff;">
                {{-- Header --}}
                <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid #f1f5f9;">
                    <span style="font-size:14px; font-weight:600; color:#1e293b;">Feedback</span>
                    <button onclick="toggleFeedbackPanel()" style="width:24px; height:24px; border:none; background:none; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                        <iconify-icon icon="heroicons-outline:x-mark" width="16" style="color:#94a3b8;"></iconify-icon>
                    </button>
                </div>
                {{-- Tabs --}}
                <div style="display:flex; border-bottom:1px solid #f1f5f9;">
                    <button class="cp-feedback-tab active" onclick="switchFeedbackTab('comments', this)">Comments</button>
                    <button class="cp-feedback-tab" onclick="switchFeedbackTab('suggestions', this)">Suggestions</button>
                </div>
                {{-- Comment input --}}
                <div style="padding:12px 16px; border-bottom:1px solid #f1f5f9;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <iconify-icon icon="heroicons-outline:chat-bubble-left" width="16" style="color:#cbd5e1;"></iconify-icon>
                        <input id="feedbackCommentInput" type="text" placeholder="Say something..." style="flex:1; border:none; outline:none; font-size:13px; color:#374151; font-family:inherit;" onkeydown="if(event.key==='Enter')addComment()">
                    </div>
                </div>
                {{-- Comments list --}}
                <div id="feedbackCommentsList" style="flex:1; overflow-y:auto; padding:16px;">
                    <div style="text-align:center; padding:40px 16px;">
                        <p style="font-size:13px; font-weight:500; color:#64748b; margin:0 0 4px;">No comments yet</p>
                        <p style="font-size:12px; color:#94a3b8; margin:0;">Start the conversation by leaving the first comment.</p>
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
    /* When phone preview is active, preview card shrinks to ~iPhone width */
    #composerPreviewCard.device-phone { max-width: 320px; }
    #composerPreviewCard.device-desktop { max-width: 380px; }

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
        composerState = { postId, platforms: [], mediaIds: [], labelIds: [], campaignId: null, contentType: 'post', isEditing: !!postId, mediaItems: [] };
        document.getElementById('postComposerOverlay').style.display = 'block';
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

    // ── Load Post ──
    async function loadPostForEditing(postId) {
        try {
            const res = await fetch(`{{ url('/marketing/planner/api/posts') }}/${postId}`);
            const post = await res.json();
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
        slide.style.cssText = 'flex:0 0 100%;width:100%;';

        const isVideo = (media.mime_type || '').startsWith('video/');
        const el = document.createElement(isVideo ? 'video' : 'img');
        el.src = media.thumbnail_url || media.url;
        el.style.cssText = 'width:100%;display:block;' + (isVideo ? '' : 'pointer-events:none;');
        if (isVideo) {
            el.muted = true; el.autoplay = true; el.loop = true; el.playsInline = true;
        } else {
            el.alt = '';
            el.draggable = false;
        }
        slide.appendChild(el);
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

    // ── Delete ──
    async function deletePost() {
        if (!composerState.postId || !confirm('Delete this post?')) return;
        try {
            await fetch(`{{ url('/marketing/planner/api/posts') }}/${composerState.postId}`, {
                method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            });
            closeComposer();
            if (typeof refreshGrid === 'function') refreshGrid();
        } catch (e) { alert('Delete failed: ' + e.message); }
    }

    // ── Comments ──
    async function addComment() {
        if (!composerState.postId) return alert('Save the post first.');
        const body = document.getElementById('newCommentBody')?.value?.trim();
        if (!body) return;
        try {
            await fetch('{{ route("marketing.planner.api.comments.store") }}', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ content_post_id: composerState.postId, body }),
            });
        } catch (e) { console.error(e); }
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

    // ── Campaign / Labels pills (placeholder for Faza 6 polish) ──
    function openCampaignPicker() {
        // TODO Faza 6: replace with a proper dropdown fetching /api/campaigns.
        const name = prompt('Campaign name (leave empty to remove):', '');
        const pill = document.getElementById('composerCampaignPill');
        const span = pill.querySelector('span[data-default]');
        if (name && name.trim()) {
            span.textContent = name.trim();
            pill.classList.add('has-value');
        } else if (name === '') {
            span.textContent = span.dataset.default;
            pill.classList.remove('has-value');
            composerState.campaignId = null;
        }
    }

    function openLabelsPicker() {
        // TODO Faza 6: replace with a proper dropdown fetching /api/labels.
        const labels = prompt('Labels (comma-separated, leave empty to clear):', '');
        const pill = document.getElementById('composerLabelsPill');
        const span = pill.querySelector('span[data-default]');
        if (labels && labels.trim()) {
            const list = labels.split(',').map(s => s.trim()).filter(Boolean);
            span.textContent = list.length + ' label' + (list.length === 1 ? '' : 's');
            pill.classList.add('has-value');
        } else if (labels === '') {
            span.textContent = span.dataset.default;
            pill.classList.remove('has-value');
            composerState.labelIds = [];
        }
    }
</script>
