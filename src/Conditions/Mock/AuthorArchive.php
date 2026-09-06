<?php

namespace Elemacy\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Conditions\MockCondition;

class AuthorArchive extends MockCondition
{
    public function get_name(): string
    {
        return 'archive/author';
    }

    public function get_type(): string
    {
        return 'archive';
    }

    public function get_label(): string
    {
        return __('Author Archive', 'elemacy');
    }
}
