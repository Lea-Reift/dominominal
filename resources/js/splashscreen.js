import { invoke } from "@tauri-apps/api/core";

window.addEventListener('DOMContentLoaded', async (e) => {
    let serverStarted = false;
    do {
        const response = await fetch("http://localhost:8000/", { redirect: 'manual' }).catch(() => { });
        serverStarted = serverStarted;

        if (![301, 302, 200].includes(response?.status)) {
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
    } while (!serverStarted);

    invoke('set_complete');
});