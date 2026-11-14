<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\PostCard\Parts\AuthorPart;
use Elemacy\Modules\LoopBuilder\PostCard\Parts\ButtonPart;
use Elemacy\Modules\LoopBuilder\PostCard\Parts\CustomFieldPart;
use Elemacy\Modules\LoopBuilder\PostCard\Parts\ExcerptPart;
use Elemacy\Modules\LoopBuilder\PostCard\Parts\ImagePart;
use Elemacy\Modules\LoopBuilder\PostCard\Parts\MetaPart;
use Elemacy\Modules\LoopBuilder\PostCard\Parts\TermsPart;
use Elemacy\Modules\LoopBuilder\PostCard\Parts\TitlePart;

return [
    ImagePart::class,
    TitlePart::class,
    MetaPart::class,
    ExcerptPart::class,
    TermsPart::class,
    AuthorPart::class,
    ButtonPart::class,
    CustomFieldPart::class,
];
