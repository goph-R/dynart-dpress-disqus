# Disqus comments

A [dpress](https://github.com/goph-R/dynart-dpress) plugin: comments through Disqus, **loaded when
a reader asks for them and not before**.

The site stores no comments. What this keeps is a shortname and, for posts that already had a
thread somewhere else, one identifier each — and that is the whole of its data.

## Installing

```bash
git clone https://github.com/goph-R/dynart-dpress-disqus.git plugins/disqus
vendor/bin/dpress plugin:enable -name disqus
vendor/bin/dpress upgrade            # it brings one table
```

Then, in the admin:

1. **Settings → Disqus shortname.** For `gopherlab.disqus.com`, that is `gopherlab`. Nothing is
   rendered and nothing is loaded until this is set, so an enabled plugin nobody has configured is
   invisible rather than broken.
2. **Blocks → new block, type Comments, place `after_content`.** That is what puts them under
   posts, and taking the block out again takes them off — without editing a template or touching a
   post.

Requires **dpress 0.62.0** or newer: `PageContext`, the `after_content` place and settings as a
registry all arrived there, and this plugin is unwritable without them.

## Third-party JavaScript, on a front end that has none

This is the part that shaped the plugin.

dpress ships **zero JavaScript** to a reader. Disqus is a script that loads more scripts, sets
cookies and knows who is reading. Putting it on the page at load time means **every visitor to
every post is announced to a third party**, whether or not they ever scroll far enough to see a
comment.

So the default is **click to load**:

```
┌──────────────────────────────┐
│  [ Show comments ]           │   nothing loaded, nothing sent
└──────────────────────────────┘
```

The embed script is appended on the click and not before. A reader who never presses it loads
nothing and is told about nothing; one who wants the comments gets them a beat later.

**Settings → Load comments → With the page** turns that off. A site that already carries analytics
has no such property to protect and may reasonably prefer the comments simply be there.

The script is added **once** per page, and the button removes itself when pressed — a second
`embed.js` is what produces Disqus's *"we were unable to load Disqus"* banner.

## Identifiers: the one decision you cannot take back

Disqus keys a thread on `page.identifier`. **Whatever a post is keyed on on day one is what every
comment is attached to forever** — change it later and the comments are not lost, the page simply
never finds them again.

- **Never the URL.** A slug edit, moving off a subfolder, or `www` appearing would each detach
  every thread on the site. dpress refuses to store a URL anywhere for the same reason
  (`media#12`, `post#42`).
- **`dpress-<id>`** for anything written here. The id is the one thing about a post that never
  changes.
- **Whatever the old site used**, for a post that arrived with comments already on it. That is the
  **Disqus identifier** box in the post editor, and the `disqus_thread` table behind it.

`page.url` is also sent, as the absolute canonical URL — Disqus uses it for the links in
notification emails and in the moderation view, so a wrong one means every notification points at
the wrong page even when the thread is right.

## Bringing existing comments across

If the old site used Disqus, **reuse its shortname** and the threads are already there — they just
need to be found. In the Disqus admin, Community → Export (or look at the moderation list) and
write down the identifier each post uses. A WordPress one looks like:

```
573 https://example.com/?p=573
```

Paste that into **Disqus identifier** on the matching post here. That is the whole migration.

> **Copy it exactly; never rebuild it from the id.** The second half is the WordPress *guid*, which
> is written at publish time and never updated — so a post from before the site moved to HTTPS
> carries `http://`, and one from before a domain change carries the old host. `"$id
> https://example.com/?p=$id"` is right for most of an archive and silently wrong for the oldest
> posts, which are exactly the ones with the comments on them.

If the old site never used Disqus, its comments are inside the WordPress WXR export and Disqus
imports that file directly — do that **on the old site, while the old URLs still resolve**, before
anything else.

Leave the box empty on everything written here. Disqus's URL Mapper is for anything left over
afterwards; it is a cleanup, not the mechanism.

## Comment counts

**Not in 1.0, and deliberately not a setting that does nothing.** Counts need
`data-disqus-identifier` on a link in every listing row, and a listing row is a theme's markup —
there is no seam a plugin reaches it through today. A checkbox that rendered and had no effect
would be the same mistake dpress 0.62.0 exists to have fixed: an extension point that *appears* to
work is worse than one that is missing.

Doing it properly means getting the identifier into a listing row, which is a small piece of core
work — not something to fake with a map of every thread dumped into every page.

## Styling it

`assets/disqus.css` is **shape only** — the box, the button's outline, the spacing — and it lands
in the head *after* the theme's own stylesheet, so a theme should style the button by saying things
this file does not:

```css
.disqus-button { font-family: var(--display); font-weight: 700; }
```

Both files load only on a page that has a thread on it. The needle is `data-disqus`, the attribute
the block writes, so a front page pays nothing even when every post has comments.

## What this will not do

- **Moderate.** That is disqus.com, and a queue in the admin would be a second interface to
  somebody else's data.
- **Work without JavaScript.** Nothing can, with a hosted service. There is a `<noscript>` line
  saying where the comments are.
- **Survive Disqus.** Export regularly. The exports are the reason this is a reasonable choice and
  not a lock-in.

Worth saying plainly: **the free plan puts ads on your blog.** That is how it is paid for.

## Tests

```bash
composer install
php vendor/bin/phpunit --stderr
```

**Nothing here touches the network.** There is nothing to test about an embed script; what is worth
testing is all local — the identifier for a post with a mapping and one without, a shortname that
is not one rendering nothing at all, and what an empty box means.

## Licence

MIT.
