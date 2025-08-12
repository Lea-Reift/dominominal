const addHoverNavigationToMainLinks = () => {
    const links = document.querySelectorAll('a[href*="main"]');
    links.forEach(link => {
        if (!link.hasAttribute('wire:navigate.hover')) {
            link.removeAttribute('wire:navigate');
            link.setAttribute('wire:navigate.hover', '');
            console.log('✅ Added hover navigation to:', link.href);
        }
    });
};

document.addEventListener('livewire:initialized', () => {
    console.log('🚀 Prefetch: Livewire initialized');
    
    // Run initial scan
    addHoverNavigationToMainLinks();
    
    if (window.Livewire) {
        // Hook into element initialization (for new elements)
        window.Livewire.hook('element.init', (el) => {
            if (el.tagName === 'A' && el.hasAttribute('href') && el.href.includes('main')) {
                if (!el.hasAttribute('wire:navigate.hover')) {
                    el.removeAttribute('wire:navigate');
                    el.setAttribute('wire:navigate.hover', '');
                    console.log('✅ Added hover navigation to new link:', el.href);
                }
            }
        });
        
        // Hook into DOM morphing (when table content updates)
        window.Livewire.hook('morph.updated', (fromEl, toEl) => {
            console.log('🔄 Prefetch: DOM morphed, rescanning links');
            // Small delay to ensure DOM is fully updated
            setTimeout(() => {
                addHoverNavigationToMainLinks();
            }, 50);
        });
        
        // Hook into full component morphing (when entire components update)
        window.Livewire.hook('morphed', (el) => {
            console.log('🔄 Prefetch: Component morphed, rescanning links in:', el);
            // Small delay to ensure DOM is fully updated
            setTimeout(() => {
                addHoverNavigationToMainLinks();
            }, 50);
        });
        
        // Hook into commit success (when Livewire updates complete)
        window.Livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                console.log('✅ Prefetch: Commit succeeded, rescanning links');
                setTimeout(() => {
                    addHoverNavigationToMainLinks();
                }, 100);
            });
        });
    }
});

// Also run on page navigation
document.addEventListener('livewire:navigated', () => {
    console.log('🧭 Prefetch: Page navigated, rescanning links');
    addHoverNavigationToMainLinks();
});

// Fallback: Use MutationObserver for any DOM changes
const observer = new MutationObserver((mutations) => {
    let shouldRescan = false;
    
    mutations.forEach((mutation) => {
        if (mutation.type === 'childList') {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) { // Element node
                    // Check if new node contains links or is a link itself
                    if (node.tagName === 'A' || node.querySelector('a')) {
                        shouldRescan = true;
                    }
                }
            });
        }
    });
    
    if (shouldRescan) {
        console.log('👀 Prefetch: MutationObserver detected new links, rescanning');
        setTimeout(() => {
            addHoverNavigationToMainLinks();
        }, 100);
    }
});

// Start observing after Livewire is ready
document.addEventListener('livewire:initialized', () => {
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});

