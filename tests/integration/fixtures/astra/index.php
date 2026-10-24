<?php
get_header();
while (have_posts()) {
    the_post();
    echo '<main id="fixture-host">';
    the_content();
    echo '</main>';
}
get_footer();
