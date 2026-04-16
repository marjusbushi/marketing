{{-- Post Detail View — Planable style --}}
<div id="postComposerOverlay" style="display:none; position:fixed; inset:0; z-index:9990; background:#fff; font-family:Inter,system-ui,sans-serif;">

    {{-- Top bar --}}
    <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 20px; border-bottom:1px solid #e5e7eb; height:48px;">
        {{-- Left: platform icon + Campaign + Labels --}}
        <div style="display:flex; align-items:center; gap:12px;">
            <div id="composerPlatformIcon" style="width:28px; height:28px; display:flex; align-items:center; justify-content:center;">
                <iconify-icon icon="skill-icons:instagram" width="24"></iconify-icon>
            </div>
            <div style="display:flex; align-items:center; gap:4px;">
                <button type="button" class="cp-type-tab active" data-type="post" onclick="switchContentType('post')">Post</button>
                <button type="button" class="cp-type-tab" data-type="story" onclick="switchContentType('story')">Story</button>
                <button type="button" class="cp-type-tab" data-type="reels" onclick="switchContentType('reels')">Reels</button>
            </div>
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

            {{-- Left: Post content --}}
            <div style="flex:1; display:flex; flex-direction:column; overflow-y:auto; min-width:0;">

                {{-- Approval row --}}
                <div style="display:flex; align-items:center; gap:8px; padding:12px 24px;">
                    <span id="composerApprovalStatus" style="font-size:12px; color:#94a3b8;">Not approved yet</span>
                    <button id="composerApproveBtn" onclick="approvePost()" style="width:22px; height:22px; border-radius:50%; border:1.5px solid #d1d5db; background:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="Approve">
                        <iconify-icon icon="heroicons-outline:check" width="12" style="color:#9ca3af;"></iconify-icon>
                    </button>
                </div>

                {{-- User + schedule --}}
                <div style="display:flex; align-items:center; gap:8px; padding:0 24px 12px;">
                    <div style="width:28px; height:28px; border-radius:50%; background:#e0e7ff; display:flex; align-items:center; justify-content:center;">
                        <iconify-icon icon="heroicons-outline:user" width="14" style="color:#6366f1;"></iconify-icon>
                    </div>
                    <span style="font-size:13px; font-weight:500; color:#1e293b;">{{ auth()->user()->name ?? 'User' }}</span>
                    <span style="color:#cbd5e1;">·</span>
                    <span id="scheduleLabel" style="font-size:12px; color:#94a3b8; cursor:pointer;" onclick="openSchedulePicker()">Select date & time</span>
                    <input id="composerScheduledAt" type="hidden">
                </div>

                {{-- Instagram post mockup frame --}}
                <div style="flex:1; display:flex; align-items:center; justify-content:center; background:#fafbfc; padding:24px; overflow-y:auto;">
                    <div style="width:100%; max-width:380px; position:relative;">

                        {{-- Photo toolbar left --}}
                        <div id="composerPhotoToolbarLeft" style="display:none; position:absolute; top:12px; left:12px; z-index:10; gap:4px;">
                            <button onclick="document.getElementById('mediaFileInput').click()" style="width:30px; height:30px; border-radius:50%; background:rgba(0,0,0,0.5); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="Replace photo">
                                <iconify-icon icon="heroicons-outline:camera" width="14" style="color:#fff;"></iconify-icon>
                            </button>
                            <button style="width:30px; height:30px; border-radius:50%; background:rgba(0,0,0,0.5); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="Edit">
                                <iconify-icon icon="heroicons-outline:pencil" width="14" style="color:#fff;"></iconify-icon>
                            </button>
                            <button style="width:30px; height:30px; border-radius:50%; background:rgba(0,0,0,0.5); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="Filter">
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
                            {{-- Photo --}}
                            <div id="composerMediaPreview" style="display:none; position:relative;">
                                <div id="composerMediaMain" style="position:relative;"></div>
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

{{-- Schedule Picker Modal --}}
<div id="schedPickerOverlay" class="hidden fixed inset-0 bg-black/20 z-[9998]" onclick="closeSchedulePicker()"></div>
<div id="schedPickerModal" class="hidden fixed z-[9999] bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden" style="width:480px; top:50%; left:50%; transform:translate(-50%,-50%);">
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
        {{-- Time side — Planable-style single input --}}
        <div style="width:170px; padding:20px 16px 12px; display:flex; flex-direction:column;">
            <div style="font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:12px;">Select time</div>
            <input id="schedTimeInput" type="time" value="12:00" style="width:100%; height:40px; border:1px solid #e2e8f0; border-radius:8px; padding:0 12px; font-size:15px; font-weight:600; color:#1e293b; text-align:center; outline:none; font-family:inherit;" onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#e2e8f0'" onchange="updateSchedSummary()">
            <div style="margin-top:16px;">
                <div style="font-size:10px; font-weight:500; color:#94a3b8; margin-bottom:6px;">Quick picks</div>
                <div style="display:flex; flex-wrap:wrap; gap:4px;">
                    <button onclick="setQuickTime('09:00')" style="padding:3px 8px; font-size:11px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#475569; cursor:pointer;">09:00</button>
                    <button onclick="setQuickTime('12:00')" style="padding:3px 8px; font-size:11px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#475569; cursor:pointer;">12:00</button>
                    <button onclick="setQuickTime('15:00')" style="padding:3px 8px; font-size:11px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#475569; cursor:pointer;">15:00</button>
                    <button onclick="setQuickTime('18:00')" style="padding:3px 8px; font-size:11px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#475569; cursor:pointer;">18:00</button>
                    <button onclick="setQuickTime('20:00')" style="padding:3px 8px; font-size:11px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#475569; cursor:pointer;">20:00</button>
                    <button onclick="setQuickTime('21:30')" style="padding:3px 8px; font-size:11px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; color:#475569; cursor:pointer;">21:30</button>
                </div>
            </div>
        </div>
    </div>
    <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; border-top:1px solid #f1f5f9; background:#fafbfc;">
        <span id="schedSummary" class="text-xs text-slate-500 font-medium"></span>
        <div style="display:flex; gap:8px;">
            <button onclick="closeSchedulePicker()" class="px-3 py-1.5 text-xs font-medium text-slate-500 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition-colors">Cancel</button>
            <button onclick="applySchedule()" class="px-4 py-1.5 text-xs font-semibold text-white bg-primary-600 rounded-lg hover:bg-primary-700 shadow-sm transition-colors">Apply</button>
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

    // ── Media ──
    function addMediaToComposer(media) {
        const preview = document.getElementById('composerMediaPreview');
        const main = document.getElementById('composerMediaMain');
        const strip = document.getElementById('composerMediaStrip');
        const grid = document.getElementById('composerMediaGrid');

        // First media → show full size
        if (composerState.mediaItems.length <= 1 || preview.style.display === 'none') {
            preview.style.display = 'block';
            document.getElementById('composerMediaEmpty').style.display = 'none';
            showPhotoToolbars(true);
            if (media.mime_type?.startsWith('video/')) {
                main.innerHTML = `<video src="${media.url}" muted autoplay loop playsinline style="width:100%;display:block;"></video>`;
            } else {
                main.innerHTML = `<img src="${media.thumbnail_url || media.url}" style="width:100%;display:block;">`;
            }
        }

        // Multiple media → show strip
        if (composerState.mediaItems.length > 1) {
            strip.style.display = 'block';
            grid.innerHTML = '';
            composerState.mediaItems.forEach((m, i) => {
                const thumb = document.createElement('div');
                thumb.style.cssText = 'width:48px;height:48px;border-radius:6px;overflow:hidden;flex-shrink:0;position:relative;cursor:pointer;border:2px solid ' + (i === 0 ? '#6366f1' : 'transparent');
                thumb.innerHTML = `<img src="${m.thumbnail_url || m.url}" style="width:100%;height:100%;object-fit:cover;">`;
                thumb.onclick = () => showMediaAtIndex(i);
                grid.appendChild(thumb);
            });
        }
    }

    function showMediaAtIndex(index) {
        const media = composerState.mediaItems[index];
        if (!media) return;
        const main = document.getElementById('composerMediaMain');
        if (media.mime_type?.startsWith('video/')) {
            main.innerHTML = `<video src="${media.url}" muted autoplay loop playsinline style="width:100%;display:block;"></video>`;
        } else {
            main.innerHTML = `<img src="${media.thumbnail_url || media.url}" style="width:100%;display:block;">`;
        }
        // Update strip selection
        document.querySelectorAll('#composerMediaGrid > div').forEach((el, i) => {
            el.style.borderColor = i === index ? '#6366f1' : 'transparent';
        });
    }

    function removeAllMedia() {
        composerState.mediaIds = [];
        composerState.mediaItems = [];
        document.getElementById('composerMediaPreview').style.display = 'none';
        document.getElementById('composerMediaEmpty').style.display = 'flex';
        document.getElementById('composerMediaStrip').style.display = 'none';
        document.getElementById('composerMediaMain').innerHTML = '';
        document.getElementById('composerMediaGrid').innerHTML = '';
        showPhotoToolbars(false);
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
        const img = document.querySelector('#composerMediaMain img');
        const vid = document.querySelector('#composerMediaMain video');
        const src = img?.src || vid?.src;
        if (src) { const a = document.createElement('a'); a.href = src; a.download = ''; a.click(); }
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
        btn.innerHTML = '<iconify-icon icon="heroicons-solid:check" width="12" style="color:#fff;"></iconify-icon>';
        status.textContent = 'Approved'; status.style.color = '#10b981';
    }
</script>
