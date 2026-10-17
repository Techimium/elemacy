<?php

namespace Elemacy\Core\Constants;

defined('ABSPATH') || exit;

class DateTimeFormats
{
    public const DB_DATE = 'Y-m-d';

    public const DB_MONTH_OF_YEAR = 'Y-m';

    public const DB_DATETIME = 'Y-m-d H:i:s';

    public const HUMAN_READABLE_DATE = 'M j, Y';

    public const HUMAN_READABLE_DAY_OF_MONTH = 'M j';

    public const HUMAN_READABLE_MONTH_OF_YEAR = 'M Y';

    public const PER_DAY_STEP = 'P1D';

    public const THREE_DAY_STEP = 'P3D';

    public const PER_MONTH_STEP = 'P1M';

    public const THREE_MONTH_STEP = 'P3M';

    public const PER_WEEK_STEP = 'P7D';

    public const THREE_WEEK_STEP = 'P21D';

    public const FIRST_DAY_OF_CURRENT_MONTH = 'Y-m-01';

    public const LAST_DAY_OF_CURRENT_MONTH = 'Y-m-t';
}
