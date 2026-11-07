<?php
/**
 * Template Name: Elemacy Fixture Named Template
 *
 * Calls get_header()/get_footer() with an explicit name, so a real
 * header-custom.php/footer-custom.php is what locate_template() finds.
 */
get_header('custom');
while (have_posts()) {
    the_post();
    echo '<main id="fixture-host">';
    the_content();
    echo '</main>';
}
get_footer('custom');
