<?php

namespace Dynart\Disqus;

use Dynart\Dpress\Service\SettingService;

/**
 * The three settings, their names and what an empty one means
 *
 * One place, because the block asks two of these and the plugin registers all three, and a setting
 * name spelled twice is a setting that reads back empty on the day one of them is corrected.
 */
class Disqus {

    const SHORTNAME = 'disqus_shortname';
    const LOAD = 'disqus_load';

    /** `on click` is the default and the reason to see §7 of the plan before changing it */
    const LOAD_CLICK = 'click';
    const LOAD_PAGE = 'load';

    const DEFAULT_LABEL = 'Show comments';

    /**
     * The site's Disqus shortname, or '' when nobody has set one
     *
     * Validated the way a page name is in the Ko-fi block and for the same reason: it goes into a
     * script URL. Disqus shortnames are lowercase letters, digits and hyphens, and anything that
     * is not one is treated as nothing at all rather than embedded and hoped for.
     */
    public static function shortname(SettingService $settings): string {
        $name = strtolower(trim((string)$settings->get(self::SHORTNAME, '')));
        return preg_match('/^[a-z0-9-]{1,64}$/', $name) === 1 ? $name : '';
    }

    /**
     * Whether the embed goes in on page load rather than waiting to be asked
     *
     * **Off unless a site says otherwise.** The front end ships no JavaScript at all; Disqus is a
     * script that loads more scripts, sets cookies and knows who is reading. Loading it on page
     * load announces every visitor to every post to a third party whether or not they ever scroll
     * far enough to see a comment - the thing `youtube-nocookie.com` and the vendored highlighter
     * were both chosen to avoid.
     *
     * A site that already carries analytics has no such property to protect and may reasonably
     * prefer the comments simply be there, which is what the setting is for.
     */
    public static function loadsOnLoad(SettingService $settings): bool {
        return (string)$settings->get(self::LOAD, self::LOAD_CLICK) === self::LOAD_PAGE;
    }
}
