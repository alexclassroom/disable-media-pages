<?php

namespace NPX;

use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

class DisableMediaPages
{
    private static $instance = null;
    public $plugin_file = null;

    public static function get_instance()
    {
        if (self::$instance == null)
        {
            self::$instance = new DisableMediaPages();
        }

        return self::$instance;
    }

    public function init()
    {
        add_filter('wp_unique_post_slug', [$this, 'unique_slug'], 10, 6);
        add_filter('template_redirect', [$this, 'set_404']);
        add_filter('redirect_canonical', [$this, 'set_404'], 0);
        add_filter('attachment_link', [$this, 'change_attachment_link'], 10, 2);
        add_filter('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_filter(
            'plugin_action_links_' . plugin_basename($this->plugin_file),
            [$this, 'plugin_action_links']
        );
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('rest_api_init', [$this, 'rest_api_init']);
    }

    public function __construct()
    {
        add_filter('init', [$this, 'init']);
    }

    public static function debug(...$messages)
    {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log(print_r($messages, true));
        }
    }

    function set_404()
    {
        if (is_attachment()) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
        }
    }

    function change_attachment_link($url, $id)
    {
        $attachment_url = wp_get_attachment_url($id);
        if ($attachment_url) {
            return $attachment_url;
        }
        return $url;
    }

    function unique_slug($slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug)
    {
        if ($post_type === 'attachment') {
            return $this->generate_uuid_v4();
        }
        return $slug;
    }

    function admin_enqueue_scripts()
    {
        $plugin_data = get_plugin_data($this->plugin_file);
        $version = $plugin_data['Version'];
        $url = plugin_dir_url($this->plugin_file);
        $path = plugin_dir_path($this->plugin_file);

        $current_screen = get_current_screen();

        if (empty($current_screen)) {
            return;
        }

        if ($current_screen->id !== 'settings_page_disable-media-pages') {
            return;
        }

        wp_enqueue_script(
            'dmp-script',
            "{$url}dist/script.js",
            [],
            WP_DEBUG ? md5_file($path . 'dist/script.js') : $version
        );

        wp_localize_script(
            'dmp-script',
            'disable_media_pages',
            [
                'root' => rest_url(),
                'token' => wp_create_nonce('wp_rest'),
                'i18n' => [
                    'plugin_title' => __('Disable Media Pages', 'disable-media-pages'),
                    'tab_status' => __('Plugin status', 'disable-media-pages'),
                    'tab_mangle' => __('Mangle existing slugs', 'disable-media-pages'),
                    'tab_restore' => __('Restore media slugs', 'disable-media-pages'),
                    'mangle_title' => __('Mangle existing slugs', 'disable-media-pages'),
                    'mangle_subtitle' => __('Existing media slug mangling tool', 'disable-media-pages'),
                    'mangle_description' => __("This tool will let you change all existing post slugs to unique ids so they won't conflict with your page titles", 'disable-media-pages'),
                    'mangle_button' => __('Start mangling process', 'disable-media-pages'),
                    'mangle_progress_title' => __('Mangling existing media slugs...', 'disable-media-pages'),
                    'mangle_progress_description' => __('Progress %d%%', 'disable-media-pages'),
                    'mangle_success_title' => __('All media slugs mangled', 'disable-media-pages'),
                    'mangle_success_button' => __('Start over', 'disable-media-pages'),
                    'restore_title' => __('Restore media slugs', 'disable-media-pages'),
                    'restore_subtitle' => __('Media slug restoration tool', 'disable-media-pages'),
                    'restore_description' => __("This tool allows you to restore media slugs from UUID4 format to a slug based on the post title.", 'disable-media-pages'),
                    'restore_button' => __('Start restoring process', 'disable-media-pages'),
                    'restore_progress_title' => __('Restoring media slugs...', 'disable-media-pages'),
                    'restore_progress_description' => __('Progress %d%%', 'disable-media-pages'),
                    'restore_success_title' => __('All media slugs restored', 'disable-media-pages'),
                    'restore_success_button' => __('Start over', 'disable-media-pages'),
                    'tool_progress_subtitle' => __('Processed %s out of %s attachments', 'disable-media-pages'),
                    'status_title' => __('Plugin status', 'disable-media-pages'),
                    'status_loading_title' => __('Loading status', 'disable-media-pages'),
                    'status_loading_description' => __('Please wait while we fetch the plugin status…', 'disable-media-pages'),
                    'status_non_unique_count_singular' => __('There is %d attachment with a non-unique slug.', 'disable-media-pages'),
                    'status_non_unique_count_plural' => __('There are %d attachments with non-unique slugs.', 'disable-media-pages'),
                    'status_non_unique_description' => __("With the plugin active, users can't access these pages. However, these attachments may accidentally reserve slugs from your pages. It's recommended to run the mangle attachments tool to prevent any potential issues in the future.", 'disable-media-pages'),
                    'status_no_issues_title' => __("No issues found", 'disable-media-pages'),
                    'status_no_issues_description' => __("All attachments have unique slugs. There's not risk of attachments accidentally reserving slugs from your pages.", 'disable-media-pages'),
                    'status_open_tool_button' => __("Open Tool", 'disable-media-pages'),
                ],
            ]
        );

        wp_enqueue_style(
            'dmp-style',
            "{$url}dist/style.css",
            [],
            WP_DEBUG ? md5_file($path . 'dist/style.css') : $version
        );
    }

    public function plugin_action_links($links)
    {
        $settings_link =
            '<a href="options-general.php?page=disable-media-pages">' .
            __('Settings', 'disable-media-pages') .
            '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function admin_menu()
    {
        add_submenu_page(
            'options-general.php',
            __(
                'Disable Media Pages',
                'disable-media-pages'
            ),
            __(
                'Disable Media Pages',
                'disable-media-pages'
            ),
            'manage_options',
            'disable-media-pages',
            [$this, 'settings_page']
        );
    }

    public function settings_page()
    {
        echo '<div id="disable-media-pages"><disable-media-pages></disable-media-pages></div>';
    }

    public function rest_api_init()
    {
        // Status
        register_rest_route(
            'disable-media-pages/v1',
            '/get_status',
            [
                'methods' => 'GET',
                'callback' => [$this, 'rest_api_get_status'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ]
        );

        // Mangle
        register_rest_route(
            'disable-media-pages/v1',
            '/get_all_attachments',
            [
                'methods' => 'GET',
                'callback' => [$this, 'rest_api_get_all_attachments'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ]
        );
        register_rest_route(
            'disable-media-pages/v1',
            '/process/(?P<id>\d+)',
            [
                'methods' => 'POST',
                'callback' => [$this, 'rest_api_process_attachment'],
                'args' => [
                    'id' => [
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        },
                    ],
                ],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ]
        );

        // Restore
        // TODO: move this into its own file
        register_rest_route(
            'disable-media-pages/v1',
            '/get-attachments-to-restore',
            [
                'methods' => 'GET',
                'callback' => [$this, 'rest_api_get_attachments_to_restore'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ]
        );

        register_rest_route(
            'disable-media-pages/v1',
            '/restore/(?P<id>\d+)',
            [
                'methods' => 'POST',
                'callback' => [$this, 'rest_api_restore_attachment'],
                'args' => [
                    'id' => [
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        },
                    ],
                ],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ]
        );
    }

    public function rest_api_get_status(WP_REST_Request $data)
    {
        global $wpdb;

        $result = $wpdb->get_var(
            "SELECT COUNT(ID) FROM  $wpdb->posts WHERE post_type = 'attachment' AND post_name NOT RLIKE '^[a-f0-9]{8}-?[a-f0-9]{4}-?4[a-f0-9]{3}-?[89ab][a-f0-9]{3}-?[a-f0-9]{12}$'"
        );

        $json = [
            'non_unique_count' => (int) $result,
        ];

        return new WP_REST_Response($json);
    }

    public function rest_api_get_all_attachments(WP_REST_Request $data)
    {
        global $wpdb;

        $result = $wpdb->get_col(
            "SELECT ID FROM  $wpdb->posts WHERE post_type = 'attachment' AND post_name NOT RLIKE '^[a-f0-9]{8}-?[a-f0-9]{4}-?4[a-f0-9]{3}-?[89ab][a-f0-9]{3}-?[a-f0-9]{12}$'"
        );

        $json = [
            'posts' => $result,
            'total' => count($result),
            'result' => $result,
        ];

        return new WP_REST_Response($json);
    }

    public function rest_api_process_attachment(WP_REST_Request $data)
    {
        $attachment = get_post($data->get_param('id'));
        $slug = $attachment->post_name;

        $is_uuid = $this->isUuid($slug);

        if (!$is_uuid) {
            $new_attachment = [
                'ID' => $attachment->ID,
                'post_name' => $this->generate_uuid_v4(),
            ];

            wp_update_post($new_attachment);
        }

        return new WP_REST_Response([]);
    }

    public function rest_api_get_attachments_to_restore(WP_REST_Request $data)
    {
        global $wpdb;

        $result = $wpdb->get_col(
            "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_name RLIKE '^[a-f0-9]{8}-?[a-f0-9]{4}-?4[a-f0-9]{3}-?[89ab][a-f0-9]{3}-?[a-f0-9]{12}$' ORDER BY post_date ASC;"
        );

        $json = [
            'posts' => $result,
            'total' => count($result),
            'result' => $result,
        ];

        return new WP_REST_Response($json);
    }

    public function rest_api_restore_attachment(WP_REST_Request $data)
    {
        $post_id = $data->get_param('id');
        $attachment = get_post($post_id);
        $slug = $attachment->post_name;

        $is_uuid = $this->isUuid($slug);

        if ($is_uuid) {
            $new_slug = sanitize_title($attachment->post_title);

            // Remove our filter so we get a real slug instead of UUID
            remove_filter('wp_unique_post_slug', [$this, 'unique_slug'], 10);

            $new_attachment = [
                'ID' => $attachment->ID,
                'post_name' => $new_slug,
            ];
            wp_update_post($new_attachment);
        }

        return new WP_REST_Response([]);
    }


    /**
     * @return string|string[]
     */
    public function generate_uuid_v4()
    {
        return str_replace('-', '', wp_generate_uuid4());
    }

    /**
     * @param string $slug
     * @return bool
     */
    private function isUuid(string $slug): bool
    {
        $is_uuid = (bool)preg_match(
            '^/[0-9a-f]{8}[0-9a-f]{4}4[0-9a-f]{3}[89ab][0-9a-f]{3}[0-9a-f]{12}/$',
            $slug
        );
        return $is_uuid;
    }

}
