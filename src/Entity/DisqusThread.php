<?php

namespace Dynart\Disqus\Entity;

use Dynart\Micro\Entities\Attribute\Column;
use Dynart\Micro\Entities\Attribute\Table;
use Dynart\Micro\Entities\Entity;

/**
 * The identifier an existing Disqus thread is already keyed on, for one post
 *
 * Only for posts that had comments somewhere else. Anything written here is `dpress-<id>` and
 * needs no row - see `Identifiers`.
 *
 * A table of its own rather than a column on `content`: a plugin that adds columns to the CMS's
 * tables is a plugin you cannot uninstall.
 *
 * **The identifier is stored exactly as Disqus exported it** and is never rebuilt from parts. A
 * WordPress thread is keyed `<post id> <guid>`, and the guid is written at publish time and never
 * updated - so a post from before the site moved to HTTPS carries `http://`, and one from before a
 * domain change carries the old host. Rebuilding it from the id would be right for most of an
 * archive and silently wrong for the oldest posts, which are exactly the ones with the comments.
 */
#[Table(name: 'disqus_thread')]
class DisqusThread extends Entity {

    protected static string $eventName = 'disqus_thread';

    #[Column(type: Column::TYPE_INT, primaryKey: true, notNull: true)]
    public int $content_id = 0;

    /** Disqus allows up to 200 characters; a WordPress one is about 40 */
    #[Column(type: Column::TYPE_STRING, size: 200, notNull: true)]
    public string $identifier = '';
}
