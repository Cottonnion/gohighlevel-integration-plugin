<?php
/**
 * Plugin Name: Syncly Load Test Generator
 * Description: Generates synthetic Syncly queue workloads for volume testing.
 * Version: 1.0.0
 *
 * Copy this file to wp-content/mu-plugins/ on a development or staging site.
 */

defined('ABSPATH') || exit;

add_action('admin_menu', 'syncly_load_test_add_tools_page');
add_action('admin_post_syncly_load_test_generate', 'syncly_load_test_generate');

/**
 * Register the load generator below the WordPress Tools menu.
 *
 * @return void
 */
function syncly_load_test_add_tools_page(): void
{
    add_management_page(
        'Syncly Load Test',
        'Syncly Load Test',
        'manage_options',
        'syncly-load-test',
        'syncly_load_test_render_page'
    );
}

/**
 * Render the load generator form and latest result.
 *
 * @return void
 */
function syncly_load_test_render_page(): void
{
    if (! current_user_can('manage_options') ) {
        wp_die(esc_html__('You do not have permission to access this page.', 'syncly'));
    }

    $events = [
    'user_register'  => 'User registration',
    'user_login'     => 'User login',
    'login_sync'     => 'Login sync',
    'profile_update' => 'Profile update',
    'woocommerce'    => 'WooCommerce order',
    'tag_sync'       => 'Tag sync',
    'mixed'          => 'Mixed workload',
    ];
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Syncly Load Test Generator', 'syncly'); ?></h1>
        <p><?php echo esc_html__('This tool adds synthetic pending queue items only. It does not call GoHighLevel while generating data.', 'syncly'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="syncly_load_test_generate">
    <?php wp_nonce_field('syncly_load_test_generate'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="syncly-load-test-event"><?php echo esc_html__('Event type', 'syncly'); ?></label></th>
                    <td>
                        <select id="syncly-load-test-event" name="event_type">
                            <?php foreach ( $events as $value => $label ) : ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="syncly-load-test-count"><?php echo esc_html__('Number of events', 'syncly'); ?></label></th>
                    <td><input id="syncly-load-test-count" name="count" type="number" min="1" max="5000" value="100" class="small-text"></td>
                </tr>
            </table>
    <?php submit_button(__('Generate queue workload', 'syncly')); ?>
        </form>
    <?php if (isset($_GET['generated']) ) : ?>
            <div class="notice notice-success is-dismissible"><p>
        <?php
        printf(
        /* translators: 1: number generated, 2: event type */
            esc_html__('Generated %1$d pending %2$s queue item(s).', 'syncly'),
            absint($_GET['generated']),
            esc_html(sanitize_key(wp_unslash($_GET['event_type'] ?? '')))
        );
        ?>
            </p></div>
    <?php endif; ?>
    </div>
    <?php
}

/**
 * Generate synthetic queue records for a selected workload.
 *
 * @return void
 */
function syncly_load_test_generate(): void
{
    if (! current_user_can('manage_options') ) {
        wp_die(esc_html__('You do not have permission to perform this action.', 'syncly'));
    }

    check_admin_referer('syncly_load_test_generate');

    $event_type  = isset($_POST['event_type']) ? sanitize_key(wp_unslash($_POST['event_type'])) : 'mixed';
    $count       = isset($_POST['count']) ? min(5000, max(1, absint($_POST['count']))) : 100;
    $event_types = [ 'user_register', 'user_login', 'login_sync', 'profile_update', 'woocommerce', 'tag_sync', 'mixed' ];

    if (! in_array($event_type, $event_types, true) ) {
        $event_type = 'mixed';
    }

    if (! class_exists('\Syncly\Sync\QueueManager') ) {
        wp_die(esc_html__('Syncly must be active before generating a workload.', 'syncly'));
    }

    $queue_manager = \Syncly\Sync\QueueManager::get_instance();
    $generated     = 0;

    for ( $index = 1; $index <= $count; $index++ ) {
        $type_action = syncly_load_test_event_action($event_type, $index);
        $payload     = [
        '_syncly_load_test' => true,
        'email'             => 'syncly-load-test-' . $index . '@example.test',
        'first_name'        => 'Load',
        'last_name'         => 'Test ' . $index,
        'tags'              => [ 'syncly_load_test' ],
        ];

        $queue_id = $queue_manager->add_to_queue(
            $type_action['item_type'],
            100000000 + $index,
            $type_action['action'],
            $payload
        );

        if (false !== $queue_id ) {
            $generated++;
        }
    }

    $url = add_query_arg(
        [
        'page'       => 'syncly-load-test',
        'generated'  => $generated,
        'event_type' => $event_type,
        ],
        admin_url('tools.php')
    );

    wp_safe_redirect($url);
    exit;
}

/**
 * Resolve a workload name to the queue item type and action it represents.
 *
 * @param  string $event_type Selected workload.
 * @param  int    $index      Current item number.
 * @return array{item_type: string, action: string}
 */
function syncly_load_test_event_action( string $event_type, int $index ): array
{
    if ('mixed' === $event_type ) {
        $event_type = [ 'user_register', 'user_login', 'login_sync', 'profile_update', 'woocommerce', 'tag_sync' ][ ( $index - 1 ) % 6 ];
    }

    $events = [
    'user_register'  => [ 'item_type' => 'user', 'action' => 'user_register' ],
    'user_login'     => [ 'item_type' => 'user', 'action' => 'login' ],
    'login_sync'     => [ 'item_type' => 'user', 'action' => 'login_sync' ],
    'profile_update' => [ 'item_type' => 'user', 'action' => 'profile_update' ],
    'woocommerce'    => [ 'item_type' => 'wc_customer', 'action' => 'order_created' ],
    'tag_sync'       => [ 'item_type' => 'user', 'action' => 'add_tags' ],
    ];

    return $events[ $event_type ] ?? $events['user_register'];
}
