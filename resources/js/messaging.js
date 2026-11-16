const requestOptions = { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' };

async function refreshActivity() {
    if (document.hidden) return;

    for (const container of document.querySelectorAll('[data-poll-url]')) {
        if (container.contains(document.activeElement) || container.querySelector('details[open]')) continue;
        try {
            const response = await fetch(container.dataset.pollUrl, requestOptions);
            if (response.ok && !response.redirected) {
                const content = await response.text();
                if (container.contains(document.activeElement) || container.querySelector('details[open]')) continue;
                if (container.innerHTML !== content) container.innerHTML = content;
            }
        } catch {
            // Keep the current content when offline; the next interval retries.
        }
    }

    const navigation = document.querySelector('[data-notification-url]');
    if (!navigation) return;
    try {
        const response = await fetch(navigation.dataset.notificationUrl, requestOptions);
        if (!response.ok || response.redirected) return;
        const { unread } = await response.json();
        document.querySelectorAll('[data-notification-count]').forEach(badge => {
            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.hidden = unread === 0;
        });
    } catch {
        // A failed badge refresh must not interrupt the page.
    }
}

setInterval(refreshActivity, 10000);
