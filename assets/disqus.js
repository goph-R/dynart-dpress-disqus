/* The embed, appended when it is asked for.
 *
 * The whole reason this file exists rather than a `<script src="…/embed.js">` in the block: on the
 * default setting nothing is loaded until a reader presses the button, so a visitor who never
 * presses it is never announced to Disqus. The front end ships no other JavaScript, and that is a
 * property worth a few lines to keep.
 *
 * Loaded only on a page that has a thread on it - the needle is `data-disqus`, which is the
 * attribute the block writes. */
(function () {
    'use strict';

    var loaded = false;

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    /* Disqus reads a global `disqus_config` when its script runs, and the script may be added only
     * once per page - a second `embed.js` is what produces their "we were unable to load Disqus"
     * banner. So: one guard, and the button goes away with the first press rather than staying to
     * be pressed twice. */
    function load(box) {
        if (loaded) {
            return;
        }
        loaded = true;

        var config;
        try {
            config = JSON.parse(box.getAttribute('data-disqus') || '{}');
        } catch (e) {
            return;
        }
        if (!config.shortname || !config.identifier) {
            return;
        }

        window.disqus_config = function () {
            this.page.identifier = config.identifier;
            this.page.url = config.url;
            this.page.title = config.title;
        };

        var script = document.createElement('script');
        script.src = 'https://' + config.shortname + '.disqus.com/embed.js';
        script.setAttribute('data-timestamp', String(Date.now()));
        script.async = true;
        (document.head || document.body).appendChild(script);
    }

    ready(function () {
        var box = document.querySelector('[data-disqus]');
        if (!box) {
            return;
        }
        if (box.hasAttribute('data-disqus-on-load')) {
            load(box);
            return;
        }
        var button = box.querySelector('[data-disqus-load]');
        if (!button) {
            return;
        }
        button.addEventListener('click', function () {
            button.remove();
            load(box);
        });
    });
}());
