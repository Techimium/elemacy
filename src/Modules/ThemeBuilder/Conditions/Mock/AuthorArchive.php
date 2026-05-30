<?php

namespace Elemacy\Modules\ThemeBuilder\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Conditions\MockCondition;

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
