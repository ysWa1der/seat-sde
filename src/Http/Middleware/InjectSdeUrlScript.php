<?php

// Copyright (C) 2025 kangtong@cloudtemple.cc

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.

// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

namespace LocalSde\SeatLocalSde\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * InjectSdeUrlScript Middleware
 *
 * Injects JavaScript to replace Fuzzwork URL with CCP official URL
 * on SeAT settings page.
 */
class InjectSdeUrlScript
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only inject on settings view page
        if ($request->is('configuration/settings/view') || 
            ($request->route() && $request->route()->getName() === 'seatcore::seat.settings.view')) {
            
            $content = $response->getContent();
            
            if ($content && is_string($content)) {
                $script = <<<'JAVASCRIPT'

<script type="text/javascript">
(function() {
    "use strict";
    
    function replaceSdeUrl() {
        var links = document.querySelectorAll('a[href*="fuzzwork.co.uk/dump"]');
        links.forEach(function(link) {
            link.href = "https://developers.eveonline.com/static-data";
            link.textContent = "https://developers.eveonline.com/static-data";
            link.setAttribute("title", "CCP Official Static Data Export");
        });
        if (links.length > 0) {
            console.log("[seat-local-sde] Replaced " + links.length + " Fuzzwork URL(s) with CCP official URL");
        }
    }
    
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", replaceSdeUrl);
    } else {
        replaceSdeUrl();
        setTimeout(replaceSdeUrl, 500);
    }
})();
</script>

JAVASCRIPT;
                
                // Try to inject before </body>
                if (strpos($content, '</body>') !== false) {
                    $content = str_replace('</body>', $script . "\n</body>", $content);
                } 
                // Fallback: inject before </html>
                elseif (strpos($content, '</html>') !== false) {
                    $content = str_replace('</html>', $script . "\n</html>", $content);
                }
                
                $response->setContent($content);
            }
        }

        return $response;
    }
}
