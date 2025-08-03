const addHoverNavigationToMainLinks = () => {
    const links = document.querySelectorAll('a[href*="main"]');
    links.forEach(link => {
        if (!link.hasAttribute('wire:navigate.hover')) {
            link.removeAttribute('wire:navigate');
            link.setAttribute('wire:navigate.hover', '');
        }
    });
};

// Run on DOM content loaded
document.addEventListener('livewire:init', () => {
    addHoverNavigationToMainLinks()
});

// Also run when new content is dynamically loaded
const observer = new MutationObserver(() => {
    addHoverNavigationToMainLinks();
});

observer.observe(document.body, {
    childList: true,
    subtree: true
});