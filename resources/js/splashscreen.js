import { invoke } from "@tauri-apps/api/core";

window.addEventListener('DOMContentLoaded', async () => {
    const cookies = await window.cookieStore.getAll();
    if (cookies && cookies.length > 0) {
        await invoke('store_session_cookies', { cookies });
        console.log(`Stored ${cookies.length} cookies:`, cookies);
    } else {
        console.log('No cookies found to store');
    }
});