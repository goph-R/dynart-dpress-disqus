<?php

namespace Dynart\Disqus\Test;

use Dynart\Disqus\DisqusPlugin;
use Dynart\Dpress\Entity\Content;
use Dynart\Dpress\Form\DpressForm;
use Dynart\Dpress\Form\FormFactory;
use Dynart\Micro\EventService;
use Dynart\Micro\Request;
use Dynart\Micro\Session;
use PHPUnit\Framework\TestCase;

/**
 * That the handlers can actually be called with what the events actually carry
 *
 * Written after `onContentSaved()` took a `bool $valid` for its second argument and the event hands
 * over the **save handler's return value** - which, from the content editor, is the `Content` that
 * was just written. Every unit test passed and every save of a post was a fatal, because nothing
 * called the handler the way the framework calls it.
 *
 * So these go through a real `DpressForm` with a real `EventService` and a real `handle()`: no
 * database, no container, no HTTP, and the signature is checked by PHP itself before the body of
 * the handler runs.
 *
 * @covers \Dynart\Disqus\DisqusPlugin
 */
class SubscriptionTest extends TestCase {

    protected function setUp(): void {
        $_REQUEST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void {
        $_REQUEST = [];
    }

    private function form(EventService $events, array $context): DpressForm {
        $form = new DpressForm(new Request(), new Session(), 'admin_content', false);
        $form->setEvents($events);
        $form->setContext($context);
        return $form;
    }

    private function page(): Content {
        $page = new Content();
        $page->id = 7;
        $page->type = Content::TYPE_PAGE;
        return $page;
    }

    /**
     * The one that was broken: `handle()` passes its callback's return value on, and the editor's
     * callback returns the `Content`
     */
    public function testTheSaveHandlerTakesWhatTheEventHandsIt(): void {
        $plugin = new DisqusPlugin();
        $events = new EventService();
        $events->subscribe(
            FormFactory::eventName('admin_content', 'after_process'), [$plugin, 'onContentSaved']
        );
        // a page, so the handler returns before it wants anything out of the container - the
        // signature is what is under test, and PHP checks that first
        $page = $this->page();
        $form = $this->form($events, ['content' => $page]);

        $this->assertSame($page, $form->handle(fn() => $page));
    }

    /**
     * And with nothing being edited at all, which is what a caller returning something else looks
     * like from in here
     */
    public function testItSurvivesAHandlerThatReturnsSomethingElse(): void {
        $plugin = new DisqusPlugin();
        $events = new EventService();
        $events->subscribe(
            FormFactory::eventName('admin_content', 'after_process'), [$plugin, 'onContentSaved']
        );
        $form = $this->form($events, []);

        $this->assertTrue($form->handle(fn() => true));
        $this->assertNull($form->handle(fn() => null));
    }

    /**
     * The form builder is called with `(DpressForm, array)`, and a page has no box - a page is not
     * a post and this plugin says so everywhere
     */
    public function testTheFormHandlerTakesWhatTheEventHandsItToo(): void {
        $plugin = new DisqusPlugin();
        $events = new EventService();
        $events->subscribe(
            FormFactory::eventName('admin_content'), [$plugin, 'onContentForm']
        );
        $form = $this->form($events, ['content' => $this->page()]);
        $events->emit(FormFactory::eventName('admin_content'), [$form, ['content' => $this->page()]]);

        $this->assertArrayNotHasKey(DisqusPlugin::IDENTIFIER_FIELD, $form->fields());
    }
}
