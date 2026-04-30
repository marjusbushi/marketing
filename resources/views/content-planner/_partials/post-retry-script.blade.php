{{-- Globals used by post-card.blade.php's "Riprovo" button on failed posts.
     Calls POST /marketing/planner/api/posts/{id}/retry, which clears the
     error, bumps scheduled_at by a minute and the version, and re-queues
     the publish job through the standard pipeline. Included once per page
     in grid/list/calendar — defining the function multiple times is safe
     but wasteful, hence the central include. --}}
<script>
    (function () {
        if (typeof window.cpRetryPost === 'function') return; // idempotent

        window.cpRetryPost = async function (postId, btn) {
            if (!postId) return;
            const original = btn ? btn.textContent : null;
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Po riprovohet…';
            }

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const url = `{{ url('/marketing/planner/api/posts') }}/${postId}/retry`;

                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                });

                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    throw new Error(data.error || `HTTP ${res.status}`);
                }

                if (btn) btn.textContent = 'Riprovë e dërguar';
                // Reload after a short delay so the user sees the confirmation
                // and the next render reflects the new status.
                setTimeout(() => window.location.reload(), 800);
            } catch (e) {
                console.error('Retry failed', e);
                alert('Nuk mundëm të riprovojmë: ' + e.message);
                if (btn) {
                    btn.disabled = false;
                    if (original) btn.textContent = original;
                }
            }
        };
    })();
</script>
