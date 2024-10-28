<?php
/**
 * Plugin Name: Add Internal Links Lite
 * Plugin URI: https://farm6media.com/resources/wp-plugins/add-internal-links-plugin/
 * Description: Add internal links to the end of a selected article's content or all articles in a selected category.
 * Version: 1.0.0
 * Author: Jeremy Shantz - Come visit me at Farm 6 Media!
 * Author URI: https://farm6media.com/
 */

// Add a submenu page under the Settings menu in the WordPress admin dashboard.
function internal_links_plugin_menu() {
    $hook_suffix = add_submenu_page(
        'options-general.php',
        'Add Internal Links',
        'Add Internal Links',
        'manage_options',
        'internal-links-plugin',
        'internal_links_plugin_admin_page'
    );

    // Use the 'load-{hook_suffix}' action hook to call the form submission handling function.
    add_action("load-{$hook_suffix}", 'internal_links_plugin_add_links');
}
add_action('admin_menu', 'internal_links_plugin_menu');

// Render the admin page for the plugin with dropdown lists of published posts and categories.
function internal_links_plugin_admin_page() {
    // Get all published posts (for other parts of the form).
    $posts = get_posts(['post_status' => 'publish', 'posts_per_page' => -1]);
    // Get all categories (for other parts of the form).
    $categories = get_categories();
    
    // Get all published pages sorted by title.
    $pages = get_posts([
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'post_type' => 'page',
        'orderby' => 'title',
        'order' => 'ASC'
    ]);

    // Get all published posts sorted by title.
    $posts_for_dropdown = get_posts([
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'post_type' => 'post',
        'orderby' => 'title',
        'order' => 'ASC'
    ]);

    // Concatenate pages and posts to create a single list.
    $posts_and_pages = array_merge($pages, $posts_for_dropdown);

    // Begin HTML rendering for the admin page.
    ?>
    <div class="wrap">
        <h1>Add Internal Links</h1>
        <form method="post" action="">
            <?php wp_nonce_field('internal_links_plugin_add_links'); ?>
            <label for="post_id">Select an article:</label>
            <select name="post_id" id="post_id">
                <option value="">-- Select an article --</option>
                <?php foreach ($posts as $post) : ?>
    <option value="<?php echo esc_attr($post->ID); ?>"><?php echo esc_html($post->post_title); ?></option>
<?php endforeach; ?>

            </select>
            <br><br>
            <label for="category_id">Or select a category:</label>
            <select name="category_id" id="category_id">
                <option value="">-- Select a category --</option>
                <?php foreach ($categories as $category) : ?>
    <option value="<?php echo esc_attr($category->term_id); ?>"><?php echo esc_html($category->name); ?></option>
<?php endforeach; ?>

            </select>
            <br><br>
            <label for="heading_text">Heading Text:</label>
            <input type="text" name="heading_text" id="heading_text" value="<?php echo 'More From ' . esc_attr(get_bloginfo('name')); ?>" required>
            <br><br>
            <label for="include_pillar">Include Pillar Post/Page:</label>
            <input type="checkbox" name="include_pillar" id="include_pillar" value="1">
            <br><br>
            <label for="pillar_post_id">Select a Pillar Post/Page:</label>
            <select name="pillar_post_id" id="pillar_post_id">
                <option value="">-- Select a Pillar Post/Page --</option>
                <?php foreach ($posts_and_pages as $post_or_page) : ?>
    <option value="<?php echo esc_attr($post_or_page->ID); ?>"><?php echo esc_html($post_or_page->post_title); ?></option>
<?php endforeach; ?>

            </select>
            <br><br>
            <!-- Add a donate button linking to the PayPal donation page -->
            <div class="donate-section" style="margin-top: 20px;">
                <a href="https://www.paypal.com/donate/?hosted_button_id=RRN3SPBMJZT4G" target="_blank" class="button button-secondary">
                    Donate and help me feed my puppies
                </a>
            </div>
            <br><br>
            <button type="submit" class="button button-primary">Add Links Now</button>
        </form>
    </div>
<?php
}

// Handle the form submission and add the custom HTML to the selected article or all articles in the selected category.
function internal_links_plugin_add_links() {
    if ((isset($_POST['post_id']) || isset($_POST['category_id'])) && check_admin_referer('internal_links_plugin_add_links')) {
        // Get values from form submission.
        $post_id = intval($_POST['post_id']);
        $category_id = intval($_POST['category_id']);
        $heading_text = sanitize_text_field($_POST['heading_text']);
        $include_pillar = isset($_POST['include_pillar']) ? intval($_POST['include_pillar']) : 0;
        $pillar_post_id = intval($_POST['pillar_post_id']);

        if ($post_id > 0) {
            // Add links to a single post.
            internal_links_plugin_add_links_to_post($post_id, $heading_text, $include_pillar, $pillar_post_id);
        } elseif ($category_id > 0) {
            // Add links to all posts in the selected category.
            $posts_in_category = get_posts(['category' => $category_id, 'posts_per_page' => -1]);
            foreach ($posts_in_category as $post) {
                internal_links_plugin_add_links_to_post($post->ID, $heading_text, $include_pillar, $pillar_post_id);
            }
        }
        // Redirect to the same page with a success message.
        wp_redirect(add_query_arg('success', '1', menu_page_url('internal-links-plugin', false)));
        exit; // Ensure the redirection takes place.
    }
}

// Function to add links to a specific post.
function internal_links_plugin_add_links_to_post($post_id, $heading_text, $include_pillar, $pillar_post_id) {
    $post = get_post($post_id);
    if ($post) {
        $category_ids = wp_get_post_categories($post_id);
        $related_posts = get_posts([
            'category__in' => $category_ids,
            'exclude' => [$post_id],
            'posts_per_page' => 6,
            'orderby' => 'rand'
        ]);

        $html = '<h3>' . esc_html($heading_text) . '</h3><ul>';
        // Include the selected Pillar Post/Page as a bolded item if the on/off checkbox is checked.
        if ($include_pillar && $pillar_post_id > 0) {
            $pillar_post = get_post($pillar_post_id);
            if ($pillar_post) {
                $html .= sprintf(
                    '<li><strong><a href="%s">%s</a></strong></li>',
                    esc_url(get_permalink($pillar_post->ID)),
                    esc_html($pillar_post->post_title)
                );
            }
        }

        // Continue with adding the list of randomly selected articles.
        foreach ($related_posts as $related_post) {
            $html .= sprintf(
                '<li><a href="%s">%s</a></li>',
                esc_url(get_permalink($related_post->ID)),
                esc_html($related_post->post_title)
            );
        }
        $html .= '</ul>';

        // Append the HTML to the post content and update the post.
        $post->post_content .= $html;
        wp_update_post($post);
    }
}

// Display a success message after adding links.
function internal_links_plugin_admin_notices() {
    // Only display the success message on the plugin's settings page.
    if (isset($_GET['success']) && isset($_GET['page']) && $_GET['page'] === 'internal-links-plugin') {
        echo '<div class="notice notice-success is-dismissible"><p>Internal links have been added successfully.</p></div>';
    }
}
add_action('admin_notices', 'internal_links_plugin_admin_notices');
?>