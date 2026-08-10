function updateLastActivity() {
    if (typeof $ === 'undefined') return;

    $.ajax({
        url: '/update-last-activity',
        type: 'POST',
        data: {},
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.status === 'updated') {
                // noop
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to update last activity', error);
        }
    });
}

function initActivityPing() {
    setTimeout(updateLastActivity, 60 * 1000);
    setInterval(updateLastActivity, 60 * 60 * 1000);
}
