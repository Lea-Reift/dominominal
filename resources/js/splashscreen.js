import { invoke } from "@tauri-apps/api/core";

setTimeout(() => {
    setInterval(() => {
        fetch("http://localhost:8000")
            .then((response) => {
                if (response.ok || response.redirected) {
                    invoke('set_complete');
                }
            })
    }, 1000);
}, 1000);