@extends('_layouts.app', [
    'title'     => 'Content Planner — List',
    'pageTitle' => 'Content Planner',
])

@section('content')
<div class="space-y-5">

    {{-- Page header --}}
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <iconify-icon icon="heroicons-outline:list-bullet" width="22" class="text-primary-500"></iconify-icon>
            List View
        </h2>
        <p class="text-sm text-slate-500 mt-1">All posts in a sortable table</p>
    </div>

    {{-- Filter bar --}}
    <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-3 flex-wrap">
        <div class="relative flex-1 min-w-[180px] max-w-[240px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" id="filterSearch" placeholder="Search content..." oninput="debounceRefresh()"
                   class="w-full h-[30px] pl-8 pr-3 rounded-md border border-slate-200 bg-white text-xs text-slate-700 placeholder:text-slate-400 outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">
        </div>
        <select id="filterPlatform" class="h-[30px] rounded-md border border-slate-200 bg-white px-2.5 text-xs text-slate-600 outline-none focus:ring-2 focus:ring-primary-500/20" onchange="refreshList()">
            <option value="">All Platforms</option>
            <option value="facebook">Facebook</option>
            <option value="instagram">Instagram</option>
            <option value="tiktok">TikTok</option>
        </select>
        <select id="filterStatus" class="h-[30px] rounded-md border border-slate-200 bg-white px-2.5 text-xs text-slate-600 outline-none focus:ring-2 focus:ring-primary-500/20" onchange="refreshList()">
            <option value="">All Statuses</option>
            @foreach($statuses as $s)
                <option value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
            @endforeach
        </select>
        <select id="filterLabel" class="h-[30px] rounded-md border border-slate-200 bg-white px-2.5 text-xs text-slate-600 outline-none focus:ring-2 focus:ring-primary-500/20" onchange="refreshList()">
            <option value="">All Labels</option>
            @foreach($labels as $label)
                <option value="{{ $label->id }}">{{ $label->name }}</option>
            @endforeach
        </select>
        <div class="flex items-center gap-2 ml-auto">
            <button onclick="syncFromMeta(this)" class="h-[30px] inline-flex items-center gap-1 px-2.5 rounded-md border border-slate-200 text-slate-500 text-xs font-medium hover:bg-slate-50 transition-colors">
                <iconify-icon icon="heroicons-outline:arrow-path" width="14"></iconify-icon> Sync Meta
            </button>
            <button onclick="openComposer()" class="h-[30px] inline-flex items-center gap-1.5 px-3.5 rounded-md bg-primary-600 text-white text-xs font-semibold hover:bg-primary-700 transition-colors">
                <iconify-icon icon="heroicons-outline:plus" width="14"></iconify-icon> New Post
            </button>
        </div>
    </div>

    {{-- Bulk actions bar --}}
    <div id="bulkBar" class="hidden bg-primary-50 border border-primary-200 rounded-xl px-5 py-2.5 flex items-center gap-3 text-sm">
        <span id="bulkCount" class="font-semibold text-primary-700">0 selected</span>
        <button onclick="bulkChangeStatus('approved')" class="px-2.5 py-1 rounded-md border border-slate-200 bg-white text-xs font-medium text-slate-600 hover:bg-slate-50">Approve</button>
        <button onclick="bulkChangeStatus('draft')" class="px-2.5 py-1 rounded-md border border-slate-200 bg-white text-xs font-medium text-slate-600 hover:bg-slate-50">Move to Draft</button>
        <button onclick="bulkDelete()" class="px-2.5 py-1 rounded-md border border-red-200 bg-white text-xs font-medium text-red-600 hover:bg-red-50">Delete</button>
        <button onclick="clearSelection()" class="ml-auto text-xs text-slate-500 hover:text-slate-700">Cancel</button>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50/60 border-b border-slate-100">
                        <th class="px-3 py-2.5 w-[30px]"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" class="w-3.5 h-3.5 rounded border-slate-300 text-primary-600"></th>
                        <th class="px-3 py-2.5 w-[44px]"></th>
                        <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" onclick="sortBy('scheduled_at')">Date <iconify-icon icon="heroicons-outline:chevron-up-down" width="11"></iconify-icon></th>
                        <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Platform</th>
                        <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700 min-w-[200px]" onclick="sortBy('content')">Content <iconify-icon icon="heroicons-outline:chevron-up-down" width="11"></iconify-icon></th>
                        <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" onclick="sortBy('status')">Status <iconify-icon icon="heroicons-outline:chevron-up-down" width="11"></iconify-icon></th>
                        <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Labels</th>
                        <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Creator</th>
                        <th class="px-3 py-2.5 w-[60px]"></th>
                    </tr>
                </thead>
                <tbody id="listBody">
                    <tr><td colspan="9" class="text-center py-10 text-slate-400 text-sm">Duke ngarkuar...</td></tr>
                </tbody>
            </table>
        </div>
        <div id="listPagination" class="flex justify-center gap-1 py-3 border-t border-slate-100"></div>
    </div>
</div>

@include('content-planner._partials.post-composer-modal')
@include('content-planner._partials.media-picker-modal')
@include('content-planner._partials.image-editor-modal')
@include('content-planner._partials.post-retry-script')

<style>
    .cp-list-thumb { @apply w-9 h-9 rounded-md object-cover bg-slate-100; }
    .cp-status-badge { @apply inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold whitespace-nowrap; }
    .cp-label-pill { @apply inline-block text-[10px] px-1.5 py-px rounded font-medium mr-0.5; }
    .cp-pg-btn { @apply px-3 py-1.5 border border-slate-200 rounded-md bg-white text-xs text-slate-600 cursor-pointer hover:border-primary-500 hover:text-primary-600 transition-colors; }
    .cp-pg-btn.active { @apply bg-primary-600 text-white border-primary-600 font-semibold; }
    .cp-pg-btn:disabled { @apply opacity-40 cursor-not-allowed; }
</style>

<script>
    const statusColors = { draft:'#9CA3AF', pending_review:'#F59E0B', approved:'#3B82F6', scheduled:'#8B5CF6', publishing:'#06B6D4', published:'#10B981', failed:'#EF4444' };
    const statusLabels = { draft:'Draft', pending_review:'In Review', approved:'Approved', scheduled:'Scheduled', publishing:'Publishing…', published:'Published', failed:'Failed' };
    const statusBgColors = { draft:'#F3F4F6', pending_review:'#FEF3C7', approved:'#DBEAFE', scheduled:'#EDE9FE', publishing:'#CFFAFE', published:'#D1FAE5', failed:'#FEE2E2' };
    const platformIcons = { facebook:'logos:facebook', instagram:'skill-icons:instagram', tiktok:'logos:tiktok-icon' };

    let currentSort = 'scheduled_at', currentDir = 'desc', currentPage = 1, selectedIds = [], debounceTimer;

    async function syncFromMeta(btn) {
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<iconify-icon icon="heroicons-outline:arrow-path" width="14" class="animate-spin"></iconify-icon> Syncing...';
        try {
            const res = await fetch('{{ route("marketing.planner.api.posts.sync-meta") }}', { method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'} });
            const data = await res.json();
            if (res.ok) { alert(`Imported ${data.facebook??0} FB + ${data.instagram??0} IG posts`); refreshList(); }
            else { alert('Sync failed: '+(data.message||res.statusText)); }
        } catch(e) { alert('Sync failed: '+e.message); }
        finally { btn.disabled=false; btn.innerHTML=origText; }
    }

    function debounceRefresh() { clearTimeout(debounceTimer); debounceTimer=setTimeout(refreshList,300); }
    function sortBy(field) { if(currentSort===field) currentDir=currentDir==='desc'?'asc':'desc'; else { currentSort=field; currentDir='desc'; } refreshList(); }

    async function refreshList(page) {
        if(page) currentPage=page;
        const params = new URLSearchParams({ sort_by:currentSort, sort_dir:currentDir, page:currentPage, per_page:20 });
        const search=document.getElementById('filterSearch').value, platform=document.getElementById('filterPlatform').value, status=document.getElementById('filterStatus').value, label=document.getElementById('filterLabel').value;
        if(search) params.set('search',search); if(platform) params.set('platforms',platform); if(status) params.set('statuses',status); if(label) params.set('label_ids',label);
        try {
            const res = await fetch(`{{ route('marketing.planner.api.posts.paginated') }}?${params}`);
            const data = await res.json();
            renderTable(data.data);
            renderPagination(data);
        } catch(e) { console.error(e); }
    }

    function renderTable(posts) {
        const body = document.getElementById('listBody');
        if(!posts.length) { body.innerHTML='<tr><td colspan="9" class="text-center py-10 text-slate-400 text-sm">Nuk ka postime. Krijo postimin e parë!</td></tr>'; return; }
        body.innerHTML = posts.map(post => {
            const date = post.scheduled_at ? new Date(post.scheduled_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '<span class="text-slate-400">Unscheduled</span>';
            const platforms = (post.platforms||[]).map(p=>`<iconify-icon icon="${platformIcons[p.platform]||''}" width="14"></iconify-icon>`).join('');
            const content = (post.content||'').substring(0,80)+((post.content||'').length>80?'...':'');
            const sc=statusColors[post.status]||'#6B7280', sbg=statusBgColors[post.status]||'#F3F4F6', sl=statusLabels[post.status]||post.status;
            const labels = (post.labels||[]).map(l=>`<span class="cp-label-pill" style="background:${l.color}20;color:${l.color};">${l.name}</span>`).join('');
            const creator = post.user?.name||'';
            const thumbUrl = post.media?.[0]?.thumbnail_url||post.media?.[0]?.url||null;
            const thumb = thumbUrl ? `<img src="${thumbUrl}" class="cp-list-thumb" alt="" onerror="this.style.display='none'">` : `<div class="cp-list-thumb flex items-center justify-center"><iconify-icon icon="heroicons-outline:photo" width="14" class="text-slate-300"></iconify-icon></div>`;
            return `<tr onclick="openComposer(${post.id})" class="cursor-pointer hover:bg-slate-50/60 border-b border-slate-50" data-id="${post.id}">
                <td class="px-3 py-2.5" onclick="event.stopPropagation()"><input type="checkbox" value="${post.id}" onchange="updateSelection()" class="w-3.5 h-3.5 rounded border-slate-300 text-primary-600"></td>
                <td class="px-3 py-2.5">${thumb}</td>
                <td class="px-3 py-2.5 whitespace-nowrap text-xs text-slate-600">${date}</td>
                <td class="px-3 py-2.5"><span class="inline-flex gap-1">${platforms}</span></td>
                <td class="px-3 py-2.5 text-slate-700">${content||'<span class="text-slate-400">No content</span>'}</td>
                <td class="px-3 py-2.5"><span class="cp-status-badge" style="background:${sbg};color:${sc};">${sl}</span></td>
                <td class="px-3 py-2.5">${labels}</td>
                <td class="px-3 py-2.5 text-xs text-slate-500">${creator}</td>
                <td class="px-3 py-2.5" onclick="event.stopPropagation()">
                    <button onclick="deletePost(${post.id})" class="p-1 rounded hover:bg-red-50 transition-colors" title="Delete">
                        <iconify-icon icon="heroicons-outline:trash" width="15" class="text-red-400 hover:text-red-600"></iconify-icon>
                    </button>
                </td>
            </tr>`;
        }).join('');
    }

    function renderPagination(data) {
        const el = document.getElementById('listPagination');
        if(data.last_page<=1) { el.innerHTML=''; return; }
        let html = `<button class="cp-pg-btn" ${data.current_page<=1?'disabled':''} onclick="refreshList(${data.current_page-1})">←</button>`;
        for(let i=1;i<=data.last_page;i++) html+=`<button class="cp-pg-btn ${i===data.current_page?'active':''}" onclick="refreshList(${i})">${i}</button>`;
        html+=`<button class="cp-pg-btn" ${data.current_page>=data.last_page?'disabled':''} onclick="refreshList(${data.current_page+1})">→</button>`;
        el.innerHTML=html;
    }

    function updateSelection() {
        selectedIds=[...document.querySelectorAll('#listBody input[type="checkbox"]:checked')].map(cb=>parseInt(cb.value));
        const bar=document.getElementById('bulkBar');
        if(selectedIds.length) { bar.classList.remove('hidden'); bar.style.display='flex'; } else { bar.classList.add('hidden'); bar.style.display=''; }
        document.getElementById('bulkCount').textContent=selectedIds.length+' selected';
    }
    function toggleSelectAll(cb) { document.querySelectorAll('#listBody input[type="checkbox"]').forEach(el=>el.checked=cb.checked); updateSelection(); }
    function clearSelection() { document.querySelectorAll('#listBody input[type="checkbox"]').forEach(el=>el.checked=false); document.getElementById('selectAll').checked=false; selectedIds=[]; document.getElementById('bulkBar').classList.add('hidden'); document.getElementById('bulkBar').style.display=''; }

    async function bulkChangeStatus(status) {
        for(const id of selectedIds) { await fetch(`/marketing/planner/api/posts/${id}/status`,{method:'PATCH',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},body:JSON.stringify({status})}); }
        clearSelection(); refreshList();
    }
    async function bulkDelete() {
        if(!confirm(`Delete ${selectedIds.length} post(s)?`)) return;
        for(const id of selectedIds) { await fetch(`/marketing/planner/api/posts/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}}); }
        clearSelection(); refreshList();
    }
    async function deletePost(id) {
        if(!confirm('Delete this post?')) return;
        await fetch(`/marketing/planner/api/posts/${id}`,{method:'DELETE',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}});
        refreshList();
    }

    document.addEventListener('DOMContentLoaded', refreshList);
</script>
@endsection
