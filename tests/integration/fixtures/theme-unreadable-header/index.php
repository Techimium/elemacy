<?php
get_header();
echo '<main id="generic-native-main">';
while (have_posts()) {
    the_post();
    the_content();
}
echo '</main>';
get_sidebar();
get_footer();
