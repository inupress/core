<?php

declare(strict_types=1);

namespace Inupress\Core\Support;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\RichEditor;
use Inupress\Contracts\HookRegistry;
use Inupress\Contracts\Hooks;
use UnexpectedValueException;

/**
 * The admin field a block of content is edited with.
 *
 * The built-in editor is Filament's `RichEditor`, which is TipTap underneath. A plugin replaces
 * it by filtering `content.editor` — `inupress/editor-ckeditor` answers with a CKEditor field:
 *
 * ```php
 * $hooks->addFilter(Hooks::CONTENT_EDITOR, function (Field $field, string $name, string $source): Field {
 *     return CKEditor::make($name);
 * });
 * ```
 *
 * The caller configures the field that comes back, so a replacement keeps `required()`,
 * `columnSpanFull()` and everything else the call site asks for. Installing an editor plugin is
 * the switch: there is no setting and no env key ([decision 0006](../../../docs/decisions/0006-composer-only-plugins.md)).
 *
 * A filter that returns something other than a field is a broken plugin, and saying so beats
 * leaving the admin with a field that explains nothing.
 */
final class ContentEditor
{
    /** Who asks for the field: the first-party callers, as passed to `$source`. */
    public const SOURCE_PAGES = 'pages';

    public const SOURCE_WIDGETS = 'widgets';

    public static function field(string $name, string $source): Field
    {
        $replacement = app(HookRegistry::class)->applyFilters(
            Hooks::CONTENT_EDITOR,
            RichEditor::make($name),
            $name,
            $source,
        );

        if (! $replacement instanceof Field) {
            throw new UnexpectedValueException(sprintf(
                'The %s filter must return a %s, %s given.',
                Hooks::CONTENT_EDITOR,
                Field::class,
                get_debug_type($replacement),
            ));
        }

        return $replacement;
    }
}
