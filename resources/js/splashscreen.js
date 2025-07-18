import { invoke } from "@tauri-apps/api/core";

window.addEventListener('DOMContentLoaded', function () {
    let interval = setInterval(async () => {
        await fetch("http://localhost:8000/")
            .then((response) => {
                if (response.ok) {
                    clearInterval(interval)
                    invoke('set_complete');
                }
            })
    }, 1000);
})