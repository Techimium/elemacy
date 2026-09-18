<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

/**
 * Typed notification payload for a completed library write — not an
 * event-bus framework, just a plain value fired once, via
 * {@see \Elemacy\Core\Hooks::LIBRARY_CHANGED_ACTION}, after a create,
 * update, duplicate or delete is fully complete and its authoritative
 * metadata is in place. Listeners (cache invalidation and similar, under a
 * later phase) read this instead of each needing to know the internal
 * shape of three separate application services.
 */
final class LibraryChanged
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const DUPLICATED = 'duplicated';
    public const DELETED = 'deleted';

    public int $id;
    public string $group;
    public string $type;

    /** @var self::CREATED|self::UPDATED|self::DUPLICATED|self::DELETED */
    public string $action;

    public function __construct(int $id, string $group, string $type, string $action)
    {
        $this->id     = $id;
        $this->group  = $group;
        $this->type   = $type;
        $this->action = $action;
    }
}
