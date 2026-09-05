<?php

namespace Dynart\Disqus;

use Dynart\Micro\RouterInterface;
use Dynart\Micro\ViewInterface;
use Dynart\Dpress\Content\PageContext;
use Dynart\Dpress\Entity\Block;
use Dynart\Dpress\Service\ContentService;
use Dynart\Dpress\Service\SettingService;

/**
 * The comments, under whatever is being read
 *
 * A block in a place rather than a template override, so whether comments are under every post is
 * a decision somebody makes on the Blocks screen and can take back without editing a template or
 * touching a post.
 *
 * **It renders nothing more often than it renders something, and all of those are right:**
 *
 * | | |
 * |---|---|
 * | no shortname set | an enabled plugin nobody has configured is invisible, not broken |
 * | no page context | the front page, an archive, a category listing - there is no thread there |
 *
 * That second one is why `PageContext` had to exist. A block renderer is handed its own settings
 * and nothing else, and "which post am I under" is the only question this block asks.
 */
class DisqusBlock {

    public function __construct(
        protected ViewInterface $view,
        protected SettingService $settings,
        protected PageContext $page,
        protected Identifiers $identifiers,
        protected ContentService $content,
        protected RouterInterface $router,
    ) {}

    public function render(Block $block, array $settings): string {
        $shortname = Disqus::shortname($this->settings);
        $content = $this->page->content();
        if ($shortname === '' || $content === null) {
            return '';
        }
        return $this->view->fetch('disqus:block/comments', [
            // Everything the embed needs, as one attribute the script reads. Not an inline
            // `<script>` writing `disqus_config`: `html_input => 'strip'` means a document can
            // never carry a script tag, and a block should not be the one thing on the site that
            // can. A `data-` attribute is data, and `esc_attr` is the whole of the escaping.
            'config' => (string)json_encode([
                'shortname'  => $shortname,
                'identifier' => $this->identifiers->of($content),
                // Disqus uses this for the links in its notification emails and in the moderation
                // view, so a wrong one means every notification points at the wrong page even when
                // the thread is right. Absolute, and the canonical one - not whatever address the
                // reader happened to arrive on.
                'url'        => $this->router->url($this->content->publicPath($content)),
                'title'      => $content->title,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'onLoad' => Disqus::loadsOnLoad($this->settings),
            'label'  => trim((string)($settings['label'] ?? '')) ?: Disqus::DEFAULT_LABEL,
        ]);
    }
}
