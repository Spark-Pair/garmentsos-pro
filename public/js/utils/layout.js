function changeLayout() {
    if (typeof $ === 'undefined') return;
    const layoutBtn = document.getElementById('changeLayoutBtn');
    const changeLayoutUrl = window.__changeLayoutUrl || layoutBtn?.dataset?.changeLayoutUrl;
    if (!changeLayoutUrl) {
        if (typeof showMessageBox === 'function') {
            showMessageBox('warning', 'Layout change is not available on this page.');
        }
        return;
    }

    const currentLayout = layoutBtn?.dataset?.layout || window.authLayout || window.__authLayout || 'grid';
    const nextLayout = currentLayout === 'grid' ? 'table' : 'grid';
    window.__authLayout = currentLayout;

    $.ajax({
        url: changeLayoutUrl,
        type: 'POST',
        data: {
            layout: currentLayout,
            route_name: window.__routeName || null,
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.status === 'updated') {
                if (layoutBtn) {
                    layoutBtn.dataset.layout = nextLayout;
                }
                window.__authLayout = nextLayout;
                window.authLayout = nextLayout;
                window.__pendingAuthLayout = nextLayout;
                if (window.GlobalFilterManager?.showLoading) {
                    window.GlobalFilterManager.showLoading(true);
                }
                location.reload();
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to update Layout', error);
        }
    });
}
