{{-- Reusable post card component - used in calendar tooltips, grid tiles, etc. --}}
{{-- Expected vars: $post (ContentPost model or array with extendedProps) --}}
<div class="cp-post-card" style="background: #fff; border-radius: 8px; border: 1px solid #E5E7EB; overflow: hidden; cursor: pointer; transition: box-shadow 0.2s;">
    {{-- Thumbnail --}}
    @if(!empty($post['thumbnail']) || !empty($post['first_thumbnail_url']))
        @php $thumb = $post['thumbnail'] ?? $post['first_thumbnail_url'] ?? null; @endphp
        @if($thumb)
            <div style="width: 100%; aspect-ratio: 1; overflow: hidden; background: #F3F4F6;">
                <img src="{{ $thumb }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
        @endif
    @endif

    <div style="padding: 10px;">
        {{-- Platform icons --}}
        <div style="display: flex; align-items: center; gap: 4px; margin-bottom: 6px;">
            @php
                $icons = $post['platform_icons'] ?? ($post['platforms'] ?? []);
                if (is_string($icons)) $icons = [$icons];
            @endphp
            @foreach($icons as $platform)
                @if($platform === 'facebook')
                    <iconify-icon icon="logos:facebook" width="14"></iconify-icon>
                @elseif($platform === 'instagram')
                    <iconify-icon icon="skill-icons:instagram" width="14"></iconify-icon>
                @elseif($platform === 'tiktok')
                    <iconify-icon icon="logos:tiktok-icon" width="14"></iconify-icon>
                @endif
            @endforeach

            {{-- Status badge --}}
            @php
                $status = $post['status'] ?? 'draft';
                $statusColors = [
                    'draft' => '#9CA3AF',
                    'pending_review' => '#F59E0B',
                    'approved' => '#3B82F6',
                    'scheduled' => '#8B5CF6',
                    'publishing' => '#06B6D4',
                    'published' => '#10B981',
                    'failed' => '#EF4444',
                ];
                $statusLabels = [
                    'draft' => 'Draft',
                    'pending_review' => 'In Review',
                    'approved' => 'Approved',
                    'scheduled' => 'Scheduled',
                    'publishing' => 'Publishing…',
                    'published' => 'Published',
                    'failed' => 'Failed',
                ];
            @endphp
            <span style="margin-left: auto; font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 4px; background: {{ $statusColors[$status] ?? '#6B7280' }}20; color: {{ $statusColors[$status] ?? '#6B7280' }};">
                {{ $statusLabels[$status] ?? ucfirst($status) }}
            </span>
        </div>

        {{-- Failure banner — shown only for failed posts. Includes the
             Meta error message and an inline retry button that re-queues
             the post via /api/posts/{id}/retry. --}}
        @if($status === 'failed' && !empty($post['id']))
            @php $err = $post['error_message'] ?? null; @endphp
            <div style="margin-bottom: 6px; padding: 6px 8px; background: #FEE2E2; border: 1px solid #FECACA; border-radius: 4px; font-size: 11px; color: #991B1B; line-height: 1.4;">
                @if($err)
                    <div style="margin-bottom: 4px;">{{ \Illuminate\Support\Str::limit($err, 160) }}</div>
                @endif
                <button type="button"
                        onclick="event.stopPropagation(); cpRetryPost({{ (int) $post['id'] }}, this);"
                        style="font-size: 10px; font-weight: 600; padding: 3px 8px; background: #DC2626; color: #fff; border: 0; border-radius: 3px; cursor: pointer;">
                    Riprovo
                </button>
            </div>
        @endif

        {{-- Content preview --}}
        @php $content = $post['content'] ?? ''; @endphp
        @if($content)
            <p style="font-size: 12px; color: #374151; line-height: 1.4; margin: 0 0 6px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                {{ \Illuminate\Support\Str::limit(strip_tags($content), 120) }}
            </p>
        @endif

        {{-- Labels --}}
        @php $labels = $post['labels'] ?? []; @endphp
        @if(count($labels))
            <div style="display: flex; flex-wrap: wrap; gap: 3px;">
                @foreach($labels as $label)
                    <span style="font-size: 10px; padding: 1px 6px; border-radius: 3px; background: {{ $label['color'] ?? '#6366f1' }}20; color: {{ $label['color'] ?? '#6366f1' }}; font-weight: 500;">
                        {{ $label['name'] ?? '' }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</div>
