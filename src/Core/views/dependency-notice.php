<?php

/**
 * @var array{title:string,message:string,action_url:?string,button_label:string} $data
 */

defined('ABSPATH') || exit;
?>
<div class="notice elemacy-dep-notice">
    <div class="elemacy-dep-notice__icon" aria-hidden="true">
        <svg width="26" height="26" viewBox="0 0 134 134" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M134 129L133.993 129.257C133.859 131.899 131.675 134 129 134H5C2.23858 134 0 131.761 0 129V86.8906H15V119H119V59.6719H134V129ZM129.257 0.00683594C131.899 0.14053 134 2.32472 134 5V46.6719H119V15H15V73.8906H0V5C1.99737e-06 2.32472 2.10111 0.140529 4.74316 0.00683594L5 0H129L129.257 0.00683594Z" fill="currentColor"/>
            <rect x="34" y="87.1912" width="67" height="13.7941" fill="currentColor"/>
            <rect x="34.9851" y="33" width="66.0147" height="12.8088" fill="currentColor"/>
            <rect x="34.9851" y="59.603" width="47.2941" height="13.5126" fill="currentColor"/>
            <rect x="34" y="33" width="16.9963" height="67.9853" fill="currentColor"/>
        </svg>
    </div>
    <div class="elemacy-dep-notice__body">
        <h2 class="elemacy-dep-notice__title"><?php echo esc_html($data['title']); ?></h2>
        <p class="elemacy-dep-notice__text"><?php echo esc_html($data['message']); ?></p>
    </div>
    <?php if ($data['action_url']) : ?>
        <a class="elemacy-dep-notice__button" href="<?php echo esc_url($data['action_url']); ?>">
            <?php echo esc_html($data['button_label']); ?>
        </a>
    <?php endif; ?>
</div>
