<?php
get_header();
echo '<main id="bb-native-main">';
while (have_posts()) {
    the_post();
    the_content();
}
echo '</main>';
get_footer();
