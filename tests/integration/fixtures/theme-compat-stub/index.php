<?php
get_header();
echo '<main id="compat-main">';
while (have_posts()) {
    the_post();
    the_content();
}
echo '</main>';
get_footer();
