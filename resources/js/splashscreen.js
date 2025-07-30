import { invoke } from "@tauri-apps/api/core";

window.addEventListener('DOMContentLoaded', async (e) => {
    let serverStarted = false;
    do {
        try {
            const response = await fetch("http://localhost:8000/up");
            serverStarted = [301, 302, 200].includes(response?.status);
        } catch (e) {
            serverStarted = false;
        }

        if (!serverStarted) {
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
    } while (!serverStarted);

    invoke('set_complete');
});