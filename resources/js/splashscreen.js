import { invoke } from "@tauri-apps/api/core";

console.log('triggered');
setTimeout(() => {
    setInterval(() => {
        fetch("https://dominominal.test")
            .then((response) => {
                if (response.ok || response.redirected) {
                    invoke('set_complete');
                    console.log('executed');
                }
            })
    }, 1000);
}, 1000);