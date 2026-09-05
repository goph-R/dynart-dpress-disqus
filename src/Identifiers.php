<?php

namespace Dynart\Disqus;

use Dynart\Micro\Entities\EntityManager;
use Dynart\Dpress\Entity\Content;
use Dynart\Disqus\Entity\DisqusThread;

/**
 * Which Disqus thread a post is
 *
 * **The one decision here that cannot be taken back.** Disqus keys a thread on `page.identifier`,
 * and whatever is chosen on day one is what every comment is attached to forever - change it later
 * and the threads are not lost, they are simply never found by the page again.
 *
 * So: **never the URL.** A slug edit, moving off a subfolder, or `www` appearing would each detach
 * every thread on the site. dpress refuses to store a URL anywhere for exactly this reason
 * (`media#12`, `post#42`), and this is the same rule one level out.
 *
 * `dpress-<id>` for anything written here, because the id is the one thing about a post that never
 * changes. And whatever the *old* site used for a post that already has comments, which is the
 * whole of `DisqusThread`.
 */
class Identifiers {

    /** What an unmapped post is, and what every post written here will be */
    const PREFIX = 'dpress-';

    public function __construct(private EntityManager $em) {}

    /**
     * The identifier for one post
     */
    public function of(Content $content): string {
        return $this->forId((int)$content->id);
    }

    public function forId(int $contentId): string {
        if ($contentId <= 0) {
            return '';
        }
        $stored = $this->stored($contentId);
        return $stored !== '' ? $stored : self::PREFIX.$contentId;
    }

    /**
     * The mapped identifier for a post, or '' when it has none
     */
    public function stored(int $contentId): string {
        if ($contentId <= 0) {
            return '';
        }
        $row = $this->em->findById(DisqusThread::class, $contentId);
        return $row instanceof DisqusThread ? trim($row->identifier) : '';
    }

    /**
     * Records the identifier an existing thread uses, or forgets it when given nothing
     *
     * Emptying the box is a real answer - "this post has no old thread after all" - and it has to
     * mean the row goes rather than becoming an empty identifier, which would key the thread on
     * nothing at all.
     *
     * The value is stored as typed, trimmed and no further. It came out of Disqus and the whole
     * point of it is to match what is there.
     */
    public function set(int $contentId, string $identifier): void {
        if ($contentId <= 0) {
            return;
        }
        $identifier = trim($identifier);
        $row = $this->em->findById(DisqusThread::class, $contentId);
        if ($identifier === '') {
            if ($row instanceof DisqusThread) {
                $this->em->deleteById(DisqusThread::class, $contentId);
            }
            return;
        }
        if (!$row instanceof DisqusThread) {
            $row = new DisqusThread();
            $row->content_id = $contentId;
        }
        $row->identifier = mb_substr($identifier, 0, 200);
        $this->em->save($row);
    }
}
