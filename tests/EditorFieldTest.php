<?php

namespace Dynart\Disqus\Test;

use Dynart\Disqus\DisqusPlugin;
use PHPUnit\Framework\TestCase;

/**
 * The box a post's old identifier is pasted into
 *
 * @covers \Dynart\Disqus\DisqusPlugin
 */
class EditorFieldTest extends TestCase {

    public function testItIsOptionalAndPlainText(): void {
        $field = DisqusPlugin::identifierField(38)[DisqusPlugin::IDENTIFIER_FIELD] ?? [];
        $this->assertSame('text', $field['type'] ?? null);
        $this->assertFalse($field['required'] ?? true, 'a post that needs no mapping must still save');
    }

    /**
     * Folded away with the weight and the CSS: it is for a handful of imported posts, once
     */
    public function testItLivesInTheEditorsAdvancedSection(): void {
        $field = DisqusPlugin::identifierField(38)[DisqusPlugin::IDENTIFIER_FIELD] ?? [];
        $this->assertSame(\Dynart\Dpress\Form\AdminForms::SECTION_ADVANCED, $field['section'] ?? null);
    }

    /**
     * The description names what the post would be keyed on with the box left empty, because
     * "empty means the default" is only useful if you can see what the default is
     */
    public function testItSaysWhatAnEmptyBoxMeansForThisPost(): void {
        $field = DisqusPlugin::identifierField(38)[DisqusPlugin::IDENTIFIER_FIELD];
        $this->assertStringContainsString('dpress-38', $field['description']);
        $this->assertStringContainsString(
            'exactly', $field['description'],
            'the description has to say to paste it as exported - rebuilding it from the id is'
                .' right for most of an archive and wrong for the oldest posts'
        );
    }
}
