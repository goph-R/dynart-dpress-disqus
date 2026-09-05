<?php

namespace Dynart\Disqus;

use Dynart\Micro\EventServiceInterface;
use Dynart\Micro\Micro;
use Dynart\Dpress\Entity\Content;
use Dynart\Dpress\Form\AdminForms;
use Dynart\Dpress\Form\DpressForm;
use Dynart\Dpress\Form\FormFactory;
use Dynart\Dpress\Plugin\AbstractPlugin;
use Dynart\Dpress\Service\SettingFields;

/**
 * Comments through Disqus, and nothing of them stored here
 *
 * No comment table, no moderation screen, no cron, no email. What this keeps is **a shortname and
 * an identifier per post**, and that is the whole of its data - which is what makes the feature
 * small enough to be worth having. Rendering an embed is twenty lines; making sure the embed asks
 * for the *right thread* is the entire job, and that is `Identifiers`.
 *
 * A plugin rather than part of the CMS, and not only for tidiness: a CMS whose core cannot be
 * described without mentioning a third-party comment service has the wrong core. Somebody who
 * wants Commento, isso, Giscus or a `<form>` of their own writes another plugin and every seam
 * here serves them.
 *
 * **What it will not do**: moderate - that is disqus.com, and a queue in the admin would be a
 * second interface to somebody else's data. Work without JavaScript; nothing can, with a hosted
 * service. Survive Disqus - export regularly, which is the reason this is a reasonable choice and
 * not a lock-in.
 */
class DisqusPlugin extends AbstractPlugin {

    /** The box in the post editor, and the key its value arrives under */
    const IDENTIFIER_FIELD = 'disqus_identifier';

    public function services(): array {
        return [
            Identifiers::class => Identifiers::class,
            DisqusBlock::class => DisqusBlock::class,
        ];
    }

    public function entities(): array {
        return [Entity\DisqusThread::class];
    }

    public function migrations(): array {
        return [Migration\CreateDisqusThreadTable::class];
    }

    public function views(): array {
        return ['disqus' => dirname(__DIR__).'/views'];
    }

    /**
     * A block, so the site owner decides whether comments are under every post
     *
     * Putting one in `after_content` turns comments on for posts and pages alike; taking it out
     * again turns them off without editing a template or touching a post. A template override
     * would have been fewer lines and a decision nobody could reverse from a screen.
     */
    public function blocks(): array {
        return [
            'comments' => [
                'title'  => 'Comments',
                'render' => [DisqusBlock::class, 'render'],
                'fields' => [
                    'label' => ['type' => 'text', 'label' => 'Button text', 'required' => false,
                                'description' => 'What the button says while the comments are not'
                                    .' loaded yet. Empty is "'.Disqus::DEFAULT_LABEL.'".'],
                ],
            ],
        ];
    }

    /**
     * The loader, on the pages that have a thread on them
     *
     * `data-disqus` is the attribute the block writes and nothing else does, so a site with this
     * plugin enabled and the block in no place loads nothing at all - and neither does its front
     * page, which has no thread even when every post has one.
     */
    public function pageAssets(): array {
        return [
            'disqus.js'  => 'data-disqus',
            'disqus.css' => 'data-disqus',
        ];
    }

    /**
     * The two things a declaration cannot express: a setting, and a box in the post editor
     */
    public function register(): void {
        $this->registerSettings();
        $events = Micro::get(EventServiceInterface::class);
        // Micro callables, so none of this is built until an admin actually opens a content
        // editor. A visitor reading a post pays for none of it.
        $events->subscribe(
            FormFactory::eventName(AdminForms::CONTENT), [self::class, 'onContentForm']
        );
        $events->subscribe(
            FormFactory::eventName(AdminForms::CONTENT, 'after_process'), [self::class, 'onContentSaved']
        );
    }

    /**
     * Three settings, each in one call
     *
     * `SettingFields::add()` takes the form field along with the type, which is what makes this a
     * setting that is actually *written* - before dpress 0.62.0 a plugin could add the field to
     * the form and watch it silently not be saved.
     */
    /**
     * There is deliberately **no counts setting**
     *
     * Counts need `data-disqus-identifier` on a link in every listing row, and a listing row is
     * a theme's markup - there is no seam a plugin reaches it through today. A checkbox that
     * rendered and did nothing would be the same mistake `SettingFields` was built to fix: an
     * extension point that appears to work is worse than one that is missing, because the
     * missing one sends somebody looking for another way.
     */
    protected function registerSettings(): void {
        $fields = Micro::get(SettingFields::class);
        $fields->add(Disqus::SHORTNAME, 'string', [
            'type' => 'text', 'label' => 'Disqus shortname', 'required' => false,
            'description' => 'The name of your Disqus site: for `gopherlab.disqus.com`, `gopherlab`.'
                .' Nothing is rendered and nothing is loaded until this is set.',
        ]);
        $fields->add(Disqus::LOAD, 'string', [
            'type' => 'select', 'label' => 'Load comments', 'required' => false,
            'options' => [
                Disqus::LOAD_CLICK => 'When a reader asks for them',
                Disqus::LOAD_PAGE  => 'With the page',
            ],
            'description' => 'Disqus is a script that loads more scripts, sets cookies and knows'
                .' who is reading. On click, a visitor who never presses the button is never'
                .' announced to it.',
        ]);
    }

    /**
     * The box for a thread that already exists somewhere else
     *
     * Optional, and empty is the ordinary answer: anything written here is `dpress-<id>` and needs
     * no row. It is for the posts that came from somewhere with comments already on them.
     */
    public function onContentForm(DpressForm $form, array $context): void {
        $content = $context['content'] ?? null;
        if ($content === null || !$content->isPost()) {
            return;
        }
        $form->addFields(self::identifierField((int)$content->id), false);
        $form->addValues([
            self::IDENTIFIER_FIELD => Micro::get(Identifiers::class)->stored((int)$content->id),
        ]);
    }

    /**
     * The box itself, apart from where its value comes from
     *
     * Static and pure so it can be read and tested without a container behind it - the lookup
     * beside it is one line and the wording is the part that matters. **"Paste it exactly"** is
     * not politeness: rebuilding an identifier from the id is right for most of an archive and
     * silently wrong for the oldest posts, which are the ones with the comments on them.
     *
     * @return array the one field, in the shape `Form::addFields()` takes
     */
    public static function identifierField(int $contentId): array {
        return [
            self::IDENTIFIER_FIELD => [
                'type' => 'text', 'label' => 'Disqus identifier', 'required' => false,
                'description' => 'Only for a post that already has comments somewhere else.'
                    .' Paste it exactly as Disqus exported it - a WordPress one looks like'
                    .' `573 https://example.com/?p=573`, and rebuilding it from the id gets'
                    .' the oldest posts wrong. Empty means `'.Identifiers::PREFIX.$contentId.'`.',
            ],
        ];
    }

    /**
     * Writes it, after the editor saved the rest
     *
     * `after_process` rather than a content event, because a content event carries the `Content`
     * and this value never reaches it - the field belongs to a plugin and `contentData()` names
     * the columns it will write, deliberately.
     *
     * **The second argument is whatever the save handler returned**, not a bool.
     * `DpressForm::handle()` passes its callback's return value straight through, and the
     * content editor's callback returns the `Content` it just wrote. There is no "did it
     * validate" to check here either: `handle()` is only reached inside `if ($form->process())`,
     * so a form that failed never gets this far.
     */
    public function onContentSaved(DpressForm $form, mixed $result, array $context): void {
        // what was just written, and the context as the fallback for a caller that returns
        // something else
        $content = $result instanceof Content ? $result : ($context['content'] ?? null);
        if (!$content instanceof Content || !$content->isPost()) {
            return;
        }
        $values = $form->values();
        if (!array_key_exists(self::IDENTIFIER_FIELD, $values)) {
            return;
        }
        Micro::get(Identifiers::class)->set(
            (int)$content->id, (string)$values[self::IDENTIFIER_FIELD]
        );
    }
}
