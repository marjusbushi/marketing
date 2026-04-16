@extends('_layouts.app', [
    'title' => 'Content Planner — Inbox',
    'container_class' => 'container-fluid zoho-page'
])

@section('styles')
    {{-- TODO: port report-styles partial --}}
    <style>
        .inbox-container { display: grid; grid-template-columns: 380px 1fr; gap: 0; height: calc(100vh - 300px); min-height: 500px; border: 1px solid #E5E7EB; border-radius: 0 0 8px 8px; overflow: hidden; }
        .inbox-list { border-right: 1px solid #E5E7EB; overflow-y: auto; background: #fff; }
        .inbox-detail { overflow-y: auto; background: #FAFAFA; display: flex; flex-direction: column; }

        .inbox-comment { display: flex; gap: 12px; padding: 14px 20px; border-bottom: 1px solid #F3F4F6; cursor: pointer; transition: background 0.15s; }
        .inbox-comment:hover { background: #F9FAFB; }
        .inbox-comment.active { background: #EEF2FF; border-left: 3px solid #6366f1; padding-left: 17px; }

        .inbox-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #8B5CF6); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #fff; flex-shrink: 0; }
        .inbox-avatar.external { background: linear-gradient(135deg, #3B82F6, #06B6D4); }
        .inbox-comment-meta { flex: 1; min-width: 0; }
        .inbox-comment-author { font-size: 13px; font-weight: 600; color: #374151; }
        .inbox-comment-time { font-size: 11px; color: #9CA3AF; }
        .inbox-comment-body { font-size: 12px; color: #6B7280; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .inbox-comment-post { font-size: 11px; color: #9CA3AF; margin-top: 4px; display: flex; align-items: center; gap: 4px; }
        .inbox-platform-badge { font-size: 9px; font-weight: 600; padding: 1px 5px; border-radius: 3px; text-transform: uppercase; letter-spacing: .3px; }

        .inbox-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #9CA3AF; gap: 4px; }

        .inbox-filter-bar { display: flex; align-items: center; gap: 8px; padding: 12px 20px; border-bottom: 1px solid #E5E7EB; flex-wrap: wrap; background: #fff; }
        .inbox-filter-select { padding: 5px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 12px; outline: none; background: #fff; }
        .inbox-filter-input { padding: 5px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 12px; outline: none; background: #fff; width: 180px; }
        .inbox-filter-input:focus, .inbox-filter-select:focus { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,0.1); }

        .inbox-detail-header { padding: 16px 20px; border-bottom: 1px solid #E5E7EB; background: #fff; }
        .inbox-detail-body { padding: 20px; flex: 1; overflow-y: auto; }
        .inbox-detail-post { display: flex; gap: 10px; padding: 12px; background: #fff; border-radius: 8px; margin-bottom: 16px; border: 1px solid #E5E7EB; }
        .inbox-detail-post-thumb { width: 48px; height: 48px; border-radius: 6px; object-fit: cover; background: #E5E7EB; flex-shrink: 0; }

        .inbox-reply-form { padding: 16px 20px; border-top: 1px solid #E5E7EB; background: #fff; }
        .inbox-reply-textarea { width: 100%; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 13px; resize: none; outline: none; font-family: Inter, sans-serif; transition: border-color 0.15s, box-shadow 0.15s; }
        .inbox-reply-textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,0.15); }

        .inbox-thread-reply { padding: 10px 0; border-top: 1px solid #F3F4F6; }

        .inbox-count { font-size: 11px; color: #6B7280; padding: 8px 20px; border-bottom: 1px solid #F3F4F6; background: #FAFAFA; display: flex; align-items: center; justify-content: space-between; }

        @media (max-width: 768px) {
            .inbox-container { grid-template-columns: 1fr; height: auto; }
            .inbox-list { max-height: 50vh; }
            .inbox-detail { border-top: 1px solid #E5E7EB; min-height: 400px; }
        }
    </style>
@endsection

@section('breadcrumb')
    <li class="inline-block relative top-[3px] text-base text-primary-500 font-Inter">
        <a href="{{ route('marketing.dashboard') }}">
            <iconify-icon icon="heroicons-outline:home"></iconify-icon>
            <iconify-icon icon="heroicons-outline:chevron-right" class="relative text-slate-500 text-sm rtl:rotate-180"></iconify-icon>
        </a>
    </li>
    <li class="inline-block relative text-sm text-slate-500 font-Inter dark:text-white">
        <a href="{{ route('marketing.dashboard') }}">Marketing</a>
        <iconify-icon icon="heroicons-outline:chevron-right" class="relative text-slate-500 text-sm rtl:rotate-180"></iconify-icon>
    </li>
    <li class="inline-block relative text-sm text-slate-500 font-Inter dark:text-white">Content Planner</li>
@endsection

@section('content')
@include('marketing._partials.nav')

<div class="zoho-report-page" id="content-planner" x-data="inboxApp()">

    {{-- Header --}}
    <div style="padding: 24px 24px 0; border-bottom: 1px solid #EEEEEE; padding-bottom: 20px;">
        <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px;">
            <div>
                <h2 style="font-size: 22px; font-weight: 700; color: #212121; margin: 0 0 6px; display: flex; align-items: center; gap: 8px;">
                    <iconify-icon icon="heroicons-outline:inbox" width="24" style="color: #6366f1;"></iconify-icon>
                    Inbox
                </h2>
                <p style="font-size: 14px; color: #757575; margin: 0;">
                    Manage and reply to comments across all your social platforms
                </p>
            </div>

            @include('content-planner._partials.nav')
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="inbox-filter-bar">
        <select class="inbox-filter-select" x-model="filters.platform" @change="loadComments()">
            <option value="">All Platforms</option>
            <option value="facebook">Facebook</option>
            <option value="instagram">Instagram</option>
            <option value="tiktok">TikTok</option>
        </select>

        <select class="inbox-filter-select" x-model="filters.source" @change="loadComments()">
            <option value="">All Sources</option>
            <option value="internal">Internal</option>
            <option value="external">External</option>
        </select>

        <input type="text" class="inbox-filter-input" placeholder="Search comments..."
               x-model="filters.search" @input.debounce.400ms="loadComments()">

        <input type="date" class="inbox-filter-select" x-model="filters.from" @change="loadComments()" title="From date">
        <input type="date" class="inbox-filter-select" x-model="filters.to" @change="loadComments()" title="To date">

        <div style="margin-left: auto; display: flex; gap: 8px;">
            <button @click="syncComments()" class="zoho-btn zoho-btn-sm" style="border: 1px solid #D1D5DB; background: #fff; color: #374151;" :disabled="syncing">
                <iconify-icon icon="heroicons-outline:arrow-path" width="14" :class="syncing && 'animate-spin'"></iconify-icon>
                <span x-text="syncing ? 'Syncing...' : 'Sync'"></span>
            </button>
        </div>
    </div>

    {{-- Two-column inbox layout --}}
    <div class="inbox-container">

        {{-- Left: comment list --}}
        <div class="inbox-list">
            {{-- Result count --}}
            <div class="inbox-count" x-show="!loading">
                <span x-text="pagination.total + ' comment' + (pagination.total !== 1 ? 's' : '')"></span>
                <span x-show="pagination.last_page > 1" x-text="'Page ' + pagination.current_page + ' of ' + pagination.last_page"></span>
            </div>

            <template x-if="loading && !comments.length">
                <div class="inbox-empty">
                    <iconify-icon icon="heroicons-outline:arrow-path" width="28" class="animate-spin"></iconify-icon>
                    <span style="font-size: 13px;">Loading comments...</span>
                </div>
            </template>

            <template x-if="!loading && !comments.length">
                <div class="inbox-empty">
                    <iconify-icon icon="heroicons-outline:chat-bubble-left-right" width="36"></iconify-icon>
                    <span style="font-size: 13px; font-weight: 500;">No comments found</span>
                    <span style="font-size: 11px;">Try adjusting your filters or sync from platforms</span>
                </div>
            </template>

            <template x-for="comment in comments" :key="comment.id">
                <div class="inbox-comment" :class="selectedComment?.id === comment.id && 'active'"
                     @click="selectComment(comment)">
                    <div class="inbox-avatar" :class="comment.external_id && 'external'"
                         x-text="getInitial(comment)"></div>
                    <div class="inbox-comment-meta">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 6px; min-width: 0;">
                                <span class="inbox-comment-author" x-text="getAuthorName(comment)"></span>
                                <template x-if="comment.external_platform">
                                    <span>
                                        <iconify-icon x-show="comment.external_platform === 'facebook'" icon="logos:facebook" width="12"></iconify-icon>
                                        <iconify-icon x-show="comment.external_platform === 'instagram'" icon="skill-icons:instagram" width="12"></iconify-icon>
                                        <iconify-icon x-show="comment.external_platform === 'tiktok'" icon="logos:tiktok-icon" width="12"></iconify-icon>
                                    </span>
                                </template>
                            </div>
                            <span class="inbox-comment-time" x-text="formatTime(comment.created_at)"></span>
                        </div>
                        <div class="inbox-comment-body" x-text="comment.body"></div>
                        <div class="inbox-comment-post" x-show="comment.post">
                            <iconify-icon icon="heroicons-outline:document-text" width="11"></iconify-icon>
                            <span x-text="comment.post?.content ? comment.post.content.substring(0, 50) + '...' : 'Post'"></span>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Pagination --}}
            <div x-show="pagination.last_page > 1" style="padding: 12px 20px; display: flex; align-items: center; justify-content: center; gap: 8px; border-top: 1px solid #F3F4F6;">
                <button @click="prevPage()" :disabled="pagination.current_page <= 1"
                        class="zoho-btn zoho-btn-sm" style="border: 1px solid #D1D5DB; background: #fff; color: #374151; font-size: 11px; padding: 4px 10px;">
                    <iconify-icon icon="heroicons-outline:chevron-left" width="12"></iconify-icon> Prev
                </button>
                <span style="font-size: 12px; color: #6B7280;" x-text="pagination.current_page + ' / ' + pagination.last_page"></span>
                <button @click="nextPage()" :disabled="pagination.current_page >= pagination.last_page"
                        class="zoho-btn zoho-btn-sm" style="border: 1px solid #D1D5DB; background: #fff; color: #374151; font-size: 11px; padding: 4px 10px;">
                    Next <iconify-icon icon="heroicons-outline:chevron-right" width="12"></iconify-icon>
                </button>
            </div>
        </div>

        {{-- Right: detail / reply panel --}}
        <div class="inbox-detail">
            <template x-if="!selectedComment">
                <div class="inbox-empty">
                    <iconify-icon icon="heroicons-outline:chat-bubble-bottom-center-text" width="40" style="color: #D1D5DB;"></iconify-icon>
                    <span style="font-size: 14px; font-weight: 500; color: #6B7280;">Select a comment</span>
                    <span style="font-size: 12px;">Choose a comment from the list to view its details and reply</span>
                </div>
            </template>

            <template x-if="selectedComment">
                <div style="display: flex; flex-direction: column; height: 100%;">
                    {{-- Detail header --}}
                    <div class="inbox-detail-header">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="inbox-avatar" :class="selectedComment.external_id && 'external'"
                                     style="width: 40px; height: 40px; font-size: 15px;"
                                     x-text="getInitial(selectedComment)"></div>
                                <div>
                                    <div style="font-size: 14px; font-weight: 600; color: #374151;" x-text="getAuthorName(selectedComment)"></div>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span style="font-size: 12px; color: #9CA3AF;" x-text="formatTime(selectedComment.created_at)"></span>
                                        <template x-if="selectedComment.external_platform">
                                            <span class="inbox-platform-badge"
                                                  :style="selectedComment.external_platform === 'facebook' ? 'background:#DBEAFE;color:#1D4ED8;' : selectedComment.external_platform === 'instagram' ? 'background:#FCE7F3;color:#BE185D;' : 'background:#F3F4F6;color:#374151;'"
                                                  x-text="selectedComment.external_platform"></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <template x-if="selectedComment.external_id">
                                    <span style="font-size: 10px; font-weight: 600; padding: 2px 8px; background: #DBEAFE; color: #2563EB; border-radius: 4px;">External</span>
                                </template>
                                <template x-if="!selectedComment.external_id">
                                    <span style="font-size: 10px; font-weight: 600; padding: 2px 8px; background: #F3E8FF; color: #7C3AED; border-radius: 4px;">Internal</span>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Detail body --}}
                    <div class="inbox-detail-body">
                        {{-- Post context --}}
                        <template x-if="selectedComment.post">
                            <div class="inbox-detail-post">
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 11px; font-weight: 600; color: #9CA3AF; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 4px;">
                                        Comment on post
                                    </div>
                                    <div style="font-size: 13px; color: #374151; line-height: 1.4;"
                                         x-text="selectedComment.post.content ? selectedComment.post.content.substring(0, 120) + (selectedComment.post.content.length > 120 ? '...' : '') : 'Untitled post'"></div>
                                </div>
                            </div>
                        </template>

                        {{-- Comment body --}}
                        <div style="font-size: 14px; color: #374151; line-height: 1.7; margin-bottom: 20px; white-space: pre-wrap; background: #fff; padding: 16px; border-radius: 8px; border: 1px solid #E5E7EB;"
                             x-text="selectedComment.body"></div>

                        {{-- Replies thread --}}
                        <template x-if="selectedComment.replies && selectedComment.replies.length">
                            <div>
                                <div style="font-size: 12px; font-weight: 600; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">
                                    <iconify-icon icon="heroicons-outline:chat-bubble-left-right" width="14" style="vertical-align: middle;"></iconify-icon>
                                    Replies (<span x-text="selectedComment.replies.length"></span>)
                                </div>
                                <template x-for="reply in selectedComment.replies" :key="reply.id">
                                    <div class="inbox-thread-reply" style="padding: 12px; background: #fff; border-radius: 8px; border: 1px solid #E5E7EB; margin-bottom: 8px;">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                            <div class="inbox-avatar" style="width: 24px; height: 24px; font-size: 10px;"
                                                 x-text="(reply.user?.name || 'U').charAt(0).toUpperCase()"></div>
                                            <span style="font-size: 12px; font-weight: 600; color: #374151;"
                                                  x-text="reply.user?.name || 'Unknown'"></span>
                                            <span style="font-size: 11px; color: #9CA3AF;" x-text="formatTime(reply.created_at)"></span>
                                        </div>
                                        <div style="font-size: 13px; color: #6B7280; margin-left: 32px; white-space: pre-wrap;"
                                             x-text="reply.body"></div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- Reply form --}}
                    <div class="inbox-reply-form">
                        <textarea class="inbox-reply-textarea" rows="3" placeholder="Write a reply..."
                                  x-model="replyBody" @keydown.ctrl.enter="submitReply()" @keydown.meta.enter="submitReply()"></textarea>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                            <span style="font-size: 11px; color: #9CA3AF;">Ctrl+Enter to send</span>
                            <button @click="submitReply()" :disabled="!replyBody.trim() || replying"
                                    class="zoho-btn zoho-btn-primary zoho-btn-sm" style="background: #6366f1; border-color: #6366f1; font-size: 12px; padding: 6px 16px;">
                                <iconify-icon icon="heroicons-outline:paper-airplane" width="14"></iconify-icon>
                                <span x-text="replying ? 'Sending...' : 'Reply'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function inboxApp() {
    return {
        comments: [],
        selectedComment: null,
        loading: false,
        syncing: false,
        replying: false,
        replyBody: '',
        filters: {
            platform: '',
            source: '',
            from: '',
            to: '',
            search: '',
        },
        pagination: {
            current_page: 1,
            last_page: 1,
            total: 0,
        },

        init() {
            this.loadComments();
        },

        async loadComments(page = 1) {
            this.loading = true;
            const params = new URLSearchParams({ page });

            if (this.filters.platform) params.set('platform', this.filters.platform);
            if (this.filters.source) params.set('source', this.filters.source);
            if (this.filters.from) params.set('from', this.filters.from);
            if (this.filters.to) params.set('to', this.filters.to);
            if (this.filters.search) params.set('search', this.filters.search);

            try {
                const res = await fetch(`{{ route('marketing.planner.api.inbox.comments') }}?${params}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const json = await res.json();

                this.comments = json.data || [];
                this.pagination = json.meta || {
                    current_page: 1,
                    last_page: 1,
                    total: 0,
                };

                // Clear selection if the selected comment is no longer in list
                if (this.selectedComment && !this.comments.find(c => c.id === this.selectedComment.id)) {
                    this.selectedComment = null;
                }
            } catch (e) {
                console.error('Failed to load comments:', e);
            } finally {
                this.loading = false;
            }
        },

        selectComment(comment) {
            this.selectedComment = comment;
            this.replyBody = '';
        },

        async submitReply() {
            if (!this.replyBody.trim() || !this.selectedComment || this.replying) return;

            this.replying = true;
            try {
                const res = await fetch(`{{ url('/management/marketing/planner/inbox/comments') }}/${this.selectedComment.id}/reply`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ body: this.replyBody }),
                });

                if (res.ok) {
                    const reply = await res.json();
                    if (!this.selectedComment.replies) this.selectedComment.replies = [];
                    this.selectedComment.replies.push(reply);
                    this.replyBody = '';
                } else {
                    const err = await res.json();
                    alert('Failed to send reply: ' + (err.message || res.statusText));
                }
            } catch (e) {
                alert('Failed to send reply: ' + e.message);
            } finally {
                this.replying = false;
            }
        },

        async syncComments() {
            this.syncing = true;
            try {
                const res = await fetch('{{ route('marketing.planner.api.inbox.sync') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ platform: this.filters.platform || 'all' }),
                });

                const data = await res.json();
                if (res.ok) {
                    alert(`Synced ${data.synced || 0} comments.`);
                    this.loadComments();
                } else {
                    alert('Sync failed: ' + (data.message || res.statusText));
                }
            } catch (e) {
                alert('Sync failed: ' + e.message);
            } finally {
                this.syncing = false;
            }
        },

        prevPage() {
            if (this.pagination.current_page > 1) {
                this.loadComments(this.pagination.current_page - 1);
            }
        },

        nextPage() {
            if (this.pagination.current_page < this.pagination.last_page) {
                this.loadComments(this.pagination.current_page + 1);
            }
        },

        getAuthorName(comment) {
            if (comment.external_author) return comment.external_author;
            if (comment.user?.name) return comment.user.name;
            return 'Unknown';
        },

        getInitial(comment) {
            return this.getAuthorName(comment).charAt(0).toUpperCase();
        },

        formatTime(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const now = new Date();
            const diff = (now - date) / 1000;

            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';

            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },
    };
}
</script>
@endsection
