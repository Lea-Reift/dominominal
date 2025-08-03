const addHoverNavigationToMainLinks = () => {
    const links = document.querySelectorAll('a[href*="main"]');
    links.forEach(link => {
        if (!link.hasAttribute('wire:navigate.hover')) {
            link.removeAttribute('wire:navigate');
            link.setAttribute('wire:navigate.hover', '');
        }
    });
};

document.addEventListener('livewire:init', () => {
    addHoverNavigationToMainLinks();
    window.Livewire.hook('morphed', () => {
        addHoverNavigationToMainLinks()
    });
});

document.addEventListener('livewire:navigated', () => {
    addHoverNavigationToMainLinks()
});
