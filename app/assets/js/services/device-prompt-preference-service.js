/** Browser-local presentation preference only; never a credential or authorization decision. */
export function devicePromptKey(owner) {
    if (owner === undefined) {
        try {
            const context = JSON.parse(document.querySelector('meta[name="kmp-offline-session"]')?.content || 'null');
            owner = context?.impersonating ? null : context?.owner;
        } catch { return null; }
    }
    return typeof owner === 'string' && owner ? `kmp.offline.declined.${encodeURIComponent(owner)}` : null;
}

export function devicePromptDismissed() {
    const key = devicePromptKey();
    if (!key) return false;
    for (const storage of [() => localStorage, () => sessionStorage]) {
        try { if (storage().getItem(key) === '1') return true; } catch { /* Storage may be unavailable. */ }
    }
    return false;
}

/** Return whether the choice was saved persistently; session storage is a best-effort fallback. */
export function rememberDevicePromptChoice(declined, owner) {
    const key = devicePromptKey(owner);
    if (!key) return false;
    let persistent = false;
    try {
        if (declined) localStorage.setItem(key, '1');
        else localStorage.removeItem(key);
        persistent = true;
    } catch { /* Private/storage-blocked browsers can still dismiss the prompt. */ }
    try {
        if (declined) sessionStorage.setItem(key, '1');
        else sessionStorage.removeItem(key);
    } catch { /* The UI can retain its current-page choice. */ }
    return persistent;
}
