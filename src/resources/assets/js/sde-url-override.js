/**
 * Override Fuzzwork URL with CCP official URL
 * This script replaces the SDE download URL from Fuzzwork to CCP official site
 */
(function() {
    'use strict';
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', replaceSdeUrl);
    } else {
        replaceSdeUrl();
    }
    
    function replaceSdeUrl() {
        // Find all links pointing to Fuzzwork dump page
        const links = document.querySelectorAll('a[href*="fuzzwork.co.uk/dump"]');
        
        links.forEach(function(link) {
            // Replace with CCP official URL
            link.href = 'https://developers.eveonline.com/static-data';
            link.textContent = 'https://developers.eveonline.com/static-data';
            link.setAttribute('title', 'CCP Official Static Data Export');
        });
        
        console.log('[seat-local-sde] Replaced ' + links.length + ' Fuzzwork URL(s) with CCP official URL');
    }
})();
