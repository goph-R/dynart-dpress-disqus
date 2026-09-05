<?php

namespace Dynart\Disqus\Test;

use Dynart\Disqus\Disqus;
use Dynart\Disqus\Entity\DisqusThread;
use Dynart\Disqus\Identifiers;
use Dynart\Dpress\Entity\Content;
use Dynart\Dpress\Service\SettingService;
use Dynart\Micro\Entities\EntityManager;
use PHPUnit\Framework\TestCase;

/**
 * Which thread a post is, and whether anything is loaded at all
 *
 * The identifier is the one decision here that cannot be taken back: whatever a post is keyed on
 * on day one is what every comment is attached to forever, and changing it later does not lose the
 * comments - it just means the page never finds them again. So it gets the tests.
 *
 * **Nothing here touches the network.** There is nothing to test about an embed script; what is
 * worth testing is all local.
 *
 * @covers \Dynart\Disqus\Identifiers
 * @covers \Dynart\Disqus\Disqus
 */
class IdentifiersTest extends TestCase {

    /**
     * @param array<int, string> $stored content id => identifier
     */
    private function identifiers(array $stored = []): Identifiers {
        $em = $this->createMock(EntityManager::class);
        $em->method('findById')->willReturnCallback(function (string $class, mixed $id) use ($stored) {
            if (!isset($stored[(int)$id])) {
                return null;
            }
            $row = new DisqusThread();
            $row->content_id = (int)$id;
            $row->identifier = $stored[(int)$id];
            return $row;
        });
        return new Identifiers($em);
    }

    private function post(int $id): Content {
        $post = new Content();
        $post->id = $id;
        $post->type = Content::TYPE_POST;
        return $post;
    }

    // --- the default ---

    /**
     * The id is the one thing about a post that never changes, which is the whole reason to key on
     * it. A slug edit, moving off a subfolder or `www` appearing would each detach every thread on
     * a site keyed on the URL.
     */
    public function testAPostWrittenHereIsKeyedOnItsId(): void {
        $this->assertSame('dpress-38', $this->identifiers()->of($this->post(38)));
    }

    public function testAPostWithNoIdIsNoThread(): void {
        $this->assertSame('', $this->identifiers()->forId(0));
        $this->assertSame('', $this->identifiers()->forId(-1));
    }

    // --- and the exception, which is the point of the table ---

    public function testAPostThatAlreadyHasAThreadKeepsIt(): void {
        $identifiers = $this->identifiers([38 => '573 https://gopherlab.net/?p=573']);
        $this->assertSame('573 https://gopherlab.net/?p=573', $identifiers->of($this->post(38)));
    }

    /**
     * The stored value is used exactly as it was exported, `http://` and all
     *
     * A WordPress thread is keyed `<post id> <guid>`, and the guid is written at publish time and
     * never updated - so a post from before the site moved to HTTPS carries `http://`. Normalising
     * it here would be a thread nobody ever finds again.
     */
    public function testAnOldIdentifierIsNotTidiedUp(): void {
        $identifiers = $this->identifiers([2 => '412 http://gopherlab.net/?p=412']);
        $this->assertSame('412 http://gopherlab.net/?p=412', $identifiers->of($this->post(2)));
    }

    /**
     * A row holding nothing is not a thread keyed on nothing - it falls back like any other post
     */
    public function testARowWithAnEmptyIdentifierIsNoMapping(): void {
        $identifiers = $this->identifiers([9 => '   ']);
        $this->assertSame('dpress-9', $identifiers->of($this->post(9)));
        $this->assertSame('', $identifiers->stored(9));
    }

    // --- the shortname ---

    private function settings(array $values): SettingService {
        $settings = $this->createMock(SettingService::class);
        $settings->method('get')->willReturnCallback(
            fn(string $name, mixed $default = null) => $values[$name] ?? $default
        );
        $settings->method('getBool')->willReturnCallback(
            fn(string $name, bool $default = false) => (bool)($values[$name] ?? $default)
        );
        return $settings;
    }

    public function testNoShortnameIsNoComments(): void {
        $this->assertSame('', Disqus::shortname($this->settings([])));
        $this->assertSame('', Disqus::shortname($this->settings([Disqus::SHORTNAME => '   '])));
    }

    /**
     * It goes into a script URL, so it is validated rather than escaped - the same rule the Ko-fi
     * page name follows, and for the same reason
     */
    public function testAShortnameThatIsNotOneIsNothingAtAll(): void {
        foreach (['gopher lab', 'gopher.lab', '../evil', 'a"b', str_repeat('x', 65)] as $bad) {
            $this->assertSame(
                '', Disqus::shortname($this->settings([Disqus::SHORTNAME => $bad])),
                "'$bad' should not be usable as a shortname"
            );
        }
    }

    public function testAShortnameIsLowercasedAndTrimmed(): void {
        $this->assertSame(
            'gopherlab', Disqus::shortname($this->settings([Disqus::SHORTNAME => ' GopherLab ']))
        );
    }

    // --- when it loads ---

    /**
     * Click to load is the default, and it is the reason this plugin is worth writing rather than
     * pasting the embed into a template: a visitor who never presses the button is never announced
     * to a third party.
     */
    public function testNothingLoadsUntilAReaderAsksUnlessTheSiteSaysOtherwise(): void {
        $this->assertFalse(Disqus::loadsOnLoad($this->settings([])));
        $this->assertFalse(Disqus::loadsOnLoad($this->settings([Disqus::LOAD => Disqus::LOAD_CLICK])));
        $this->assertTrue(Disqus::loadsOnLoad($this->settings([Disqus::LOAD => Disqus::LOAD_PAGE])));
    }
}
