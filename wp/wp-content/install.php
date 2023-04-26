<?php

function wp_install($blog_title, $user_name, $user_email, $is_public, $deprecated = '', $user_password = '', $language = '') {
    if (!empty($deprecated)) {
        _deprecated_argument(__FUNCTION__, '2.6.0');
    }

    wp_check_mysql_version();
    wp_cache_flush();
    make_db_current_silent();
    populate_options();
    populate_roles();

    update_option('blogname', $blog_title);
    update_option('admin_email', $user_email);
    update_option('blog_public', $is_public);

    // Freshness of site - in the future, this could get more specific about actions taken, perhaps.
    update_option('fresh_site', 1);

    if ($language) {
        update_option('WPLANG', $language);
    }

    $guessurl = wp_guess_url();

    update_option('siteurl', $guessurl);

    // If not a public site, don't ping.
    if (!$is_public) {
        update_option('default_pingback_flag', 0);
    }

    /*
		 * Create default user. If the user already exists, the user tables are
		 * being shared among sites. Just set the role in that case.
		 */
    $user_id        = username_exists($user_name);
    $user_password  = trim($user_password);
    $email_password = false;
    $user_created   = false;

    if (!$user_id && empty($user_password)) {
        $user_password = wp_generate_password(12, false);
        $message       = __('<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.');
        $user_id       = wp_create_user($user_name, $user_password, $user_email);
        update_user_meta($user_id, 'default_password_nag', true);
        $email_password = true;
        $user_created   = true;
    } elseif (!$user_id) {
        // Password has been provided.
        $message      = '<em>' . __('Your chosen password.') . '</em>';
        $user_id      = wp_create_user($user_name, $user_password, $user_email);
        $user_created = true;
    } else {
        $message = __('User already exists. Password inherited.');
    }

    $user = new WP_User($user_id);
    $user->set_role('administrator');

    if ($user_created) {
        $user->user_url = $guessurl;
        wp_update_user($user);
    }

    wp_install_defaults($user_id);

    // We will skip this to speed up the install.
    // wp_install_maybe_enable_pretty_permalinks();
    if (!get_option('permalink_structure')) {
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');
    }

    flush_rewrite_rules();

    // We will skip this to speed up the install.
    // wp_new_blog_notification( $blog_title, $guessurl, $user_id, ( $email_password ? $user_password : __( 'The password you chose during installation.' ) ) );

    wp_cache_flush();

    /**
     * Fires after a site is fully installed.
     *
     * @since 3.9.0
     *
     * @param WP_User $user The site owner.
     */
    do_action('wp_install', $user);

    return array(
        'url'              => $guessurl,
        'user_id'          => $user_id,
        'password'         => $user_password,
        'password_message' => $message,
    );
}

function wp_install_defaults( $user_id ) {
    global $wpdb, $wp_rewrite, $table_prefix;

    // Default category.
    $cat_name = __( 'Uncategorized' );
    /* translators: Default category slug. */
    $cat_slug = sanitize_title( _x( 'Uncategorized', 'Default category slug' ) );

    $cat_id = 1;

    $wpdb->insert(
        $wpdb->terms,
        array(
            'term_id'    => $cat_id,
            'name'       => $cat_name,
            'slug'       => $cat_slug,
            'term_group' => 0,
        )
    );
    $wpdb->insert(
        $wpdb->term_taxonomy,
        array(
            'term_id'     => $cat_id,
            'taxonomy'    => 'category',
            'description' => '',
            'parent'      => 0,
            'count'       => 1,
        )
    );
    $cat_tt_id = $wpdb->insert_id;

    // First post.
    $now             = current_time( 'mysql' );
    $now_gmt         = current_time( 'mysql', 1 );
    $first_post_guid = get_option( 'home' ) . '/?p=1';

    if ( is_multisite() ) {
        $first_post = get_site_option( 'first_post' );

        if ( ! $first_post ) {
            $first_post = "<!-- wp:paragraph -->\n<p>" .
            /* translators: First post content. %s: Site link. */
            __( 'Welcome to %s. This is your first post. Edit or delete it, then start writing!' ) .
            "</p>\n<!-- /wp:paragraph -->";
        }

        $first_post = sprintf(
            $first_post,
            sprintf( '<a href="%s">%s</a>', esc_url( network_home_url() ), get_network()->site_name )
        );

        // Back-compat for pre-4.4.
        $first_post = str_replace( 'SITE_URL', esc_url( network_home_url() ), $first_post );
        $first_post = str_replace( 'SITE_NAME', get_network()->site_name, $first_post );
    } else {
        $first_post = "<!-- wp:paragraph -->\n<p>" .
        /* translators: First post content. %s: Site link. */
        __( 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!' ) .
        "</p>\n<!-- /wp:paragraph -->";
    }

    $wpdb->insert(
        $wpdb->posts,
        array(
            'post_author'           => $user_id,
            'post_date'             => $now,
            'post_date_gmt'         => $now_gmt,
            'post_content'          => $first_post,
            'post_excerpt'          => '',
            'post_title'            => __( 'Hello world!' ),
            /* translators: Default post slug. */
            'post_name'             => sanitize_title( _x( 'hello-world', 'Default post slug' ) ),
            'post_modified'         => $now,
            'post_modified_gmt'     => $now_gmt,
            'guid'                  => $first_post_guid,
            'comment_count'         => 1,
            'to_ping'               => '',
            'pinged'                => '',
            'post_content_filtered' => '',
        )
    );

    if ( is_multisite() ) {
        update_posts_count();
    }

    $wpdb->insert(
        $wpdb->term_relationships,
        array(
            'term_taxonomy_id' => $cat_tt_id,
            'object_id'        => 1,
        )
    );

    // Privacy Policy page.
    if ( is_multisite() ) {
        // Disable by default unless the suggested content is provided.
        $privacy_policy_content = get_site_option( 'default_privacy_policy_content' );
    } else {
        if ( ! class_exists( 'WP_Privacy_Policy_Content' ) ) {
            include_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
        }

        $privacy_policy_content = WP_Privacy_Policy_Content::get_default_content();
    }

    if ( ! empty( $privacy_policy_content ) ) {
        $privacy_policy_guid = get_option( 'home' ) . '/?page_id=3';

        $wpdb->insert(
            $wpdb->posts,
            array(
                'post_author'           => $user_id,
                'post_date'             => $now,
                'post_date_gmt'         => $now_gmt,
                'post_content'          => $privacy_policy_content,
                'post_excerpt'          => '',
                'comment_status'        => 'closed',
                'post_title'            => __( 'Privacy Policy' ),
                /* translators: Privacy Policy page slug. */
                'post_name'             => __( 'privacy-policy' ),
                'post_modified'         => $now,
                'post_modified_gmt'     => $now_gmt,
                'guid'                  => $privacy_policy_guid,
                'post_type'             => 'page',
                'post_status'           => 'draft',
                'to_ping'               => '',
                'pinged'                => '',
                'post_content_filtered' => '',
            )
        );
        $wpdb->insert(
            $wpdb->postmeta,
            array(
                'post_id'    => 3,
                'meta_key'   => '_wp_page_template',
                'meta_value' => 'default',
            )
        );
        update_option( 'wp_page_for_privacy_policy', 3 );
    }

    // Set up default widgets for default theme.
    update_option(
        'widget_block',
        array(
            2              => array( 'content' => '<!-- wp:search /-->' ),
            3              => array( 'content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2>' . __( 'Recent Posts' ) . '</h2><!-- /wp:heading --><!-- wp:latest-posts /--></div><!-- /wp:group -->' ),
            4              => array( 'content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2>' . __( 'Recent Comments' ) . '</h2><!-- /wp:heading --><!-- wp:latest-comments {"displayAvatar":false,"displayDate":false,"displayExcerpt":false} /--></div><!-- /wp:group -->' ),
            5              => array( 'content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2>' . __( 'Archives' ) . '</h2><!-- /wp:heading --><!-- wp:archives /--></div><!-- /wp:group -->' ),
            6              => array( 'content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2>' . __( 'Categories' ) . '</h2><!-- /wp:heading --><!-- wp:categories /--></div><!-- /wp:group -->' ),
            '_multiwidget' => 1,
        )
    );
    update_option(
        'sidebars_widgets',
        array(
            'wp_inactive_widgets' => array(),
            'sidebar-1'           => array(
                0 => 'block-2',
                1 => 'block-3',
                2 => 'block-4',
            ),
            'sidebar-2'           => array(
                0 => 'block-5',
                1 => 'block-6',
            ),
            'array_version'       => 3,
        )
    );

    if ( ! is_multisite() ) {
        update_user_meta( $user_id, 'show_welcome_panel', 1 );
    } elseif ( ! is_super_admin( $user_id ) && ! metadata_exists( 'user', $user_id, 'show_welcome_panel' ) ) {
        update_user_meta( $user_id, 'show_welcome_panel', 2 );
    }

    if ( is_multisite() ) {
        // Flush rules to pick up the new page.
        $wp_rewrite->init();
        $wp_rewrite->flush_rules();

        $user = new WP_User( $user_id );
        $wpdb->update( $wpdb->options, array( 'option_value' => $user->user_email ), array( 'option_name' => 'admin_email' ) );

        // Remove all perms except for the login user.
        $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix . 'user_level' ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix . 'capabilities' ) );

        // Delete any caps that snuck into the previously active blog. (Hardcoded to blog 1 for now.)
        // TODO: Get previous_blog_id.
        if ( ! is_super_admin( $user_id ) && 1 != $user_id ) {
            $wpdb->delete(
                $wpdb->usermeta,
                array(
                    'user_id'  => $user_id,
                    'meta_key' => $wpdb->base_prefix . '1_capabilities',
                )
            );
        }
    }
}