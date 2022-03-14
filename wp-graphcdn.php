<?php

/**
 * Plugin Name: GraphCDN
 * Plugin URI: https://github.com/graphcdn/wp-graphcdn
 * GitHub Plugin URI: https://github.com/graphcdn/wp-graphcdn
 * Description: GraphCDN for your WordPress GraphQL API
 * Author: GraphCDN
 * Author URI: https://graphcdn.io
 * Version: 0.1.0
 *
 * @package  GraphCDN
 * @author   GraphCDN
 * @version  0.1.0
 */

/**
 * Exit if accessed directly.
 */

if (!defined('ABSPATH')) {
  exit;
}



/**
 * Register the settings
 */

add_action('admin_init', 'register_graphcdn_settings');

function register_graphcdn_settings()
{
  register_setting('graphcdn', 'graphcdn_service_name');
  register_setting('graphcdn', 'graphcdn_purging_token');
  register_setting('graphcdn', 'graphcdn_soft_purge');
}



/**
 * Add a "Settings" page for GraphCDN (part of the "GraphQL" Menu provided by WPGraphQL)
 */

add_action('admin_menu', 'add_caching_page');

function add_caching_page()
{
  add_submenu_page(
    'graphiql-ide',
    __('WPGraphQL Settings', 'wp-graphql'),
    'Caching',
    'manage_options',
    'graphql-caching',
    'render_caching_page'
  );
}

function render_caching_page()
{
  $service_name = get_option('graphcdn_service_name');
  $token = get_option('graphcdn_purging_token') ? "******" : "";
  $soft_purge = get_option('graphcdn_soft_purge') === 'on' ? 'checked' : '';
?>
  <div class="wrap">
    <h2>Caching with GraphCDN</h2>
    <h3>Settings</h3>
    <form action="options.php" method="POST" autocomplete="off">
      <?php
      settings_fields('graphcdn');
      do_settings_sections('graphcdn');
      ?>
      <table class="form-table" role="presentation">
        <tbody>
          <tr>
            <th scope="row">Service name</th>
            <td>
              <input type="text" name="graphcdn_service_name" class="regular-text" value="<?= $service_name ?>" />
              <p><?php esc_attr_e('Enter the name of your GraphCDN service. Without this the GraphCDN plugin will do nothing.', 'WpAdminStyle'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row">Purging token</th>
            <td>
              <input type="password" name="graphcdn_purging_token" class="regular-text" value="<?= $token ?>" />
              <p><?php esc_attr_e('Enter a purging token created for the GraphCDN service entered above. Without this the GraphCDN plugin will do nothing.', 'WpAdminStyle'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row">Use soft purging</th>
            <td>
              <label>
                <input name="graphcdn_soft_purge" type="checkbox" <?= $soft_purge ?> />
                <span><?php esc_attr_e('When using soft purging, the cache continues to serve stale data while it is revalidated in the background.', 'WpAdminStyle'); ?></span>
              </label>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button() ?>
    </form>
    <h3>Purge the entire cache</h3>
    <p>By clicking the following button you purge all contents from the cache of your GraphCDN service.</p>
    <form action="admin-post.php" method="POST">
      <input type="hidden" name="action" value="graphcdn_purge_all" />
      <?php submit_button('Purge', 'secondary') ?>
    </form>
    <?php if ($_GET['success']) { ?>
      <div class="notice notice-success inline">
        <p>Purging succeeeded!</p>
      </div>
    <?php } ?>
    <?php if ($_GET['failure']) { ?>
      <div class="notice notice-error inline">
        <p>
          <?php
          printf(
            esc_attr__('Purging did not succeed: %1$s', 'WpAdminStyle'),
            '<code>' . $_GET['failure'] . '</code>',
          );
          ?>
        </p>
      </div>
    <?php } ?>
  </div>
<?php
}



/**
 * Handle submitting the form to purge the entire cache (part of the settings page)
 */

add_action('admin_post_graphcdn_purge_all', 'post_purge_all');

function post_purge_all()
{
  $res = purge_all();
  $query = $res ? 'failure=' . urlencode($res) : 'success=true';
  wp_redirect('admin.php?page=graphql-caching&' . $query);
  exit;
}



/**
 * Manually invalidate when handling a mutation that adds entities
 */

add_action('graphql_after_resolve_field', 'invalidate', 10, 9);

/**
 * @param mixed $source
 * @param array $args
 * @param WPGraphQL\AppContextAppContext $context
 * @param GraphQL\Type\Definition\ResolveInfo $info
 * @param mixed $field_resolver
 * @param string $type_name
 * @param string $field_key
 * @param GraphQL\Type\Definition\FieldDefinition $field
 * @param mixed $result
 */
function invalidate(
  $source,
  $args,
  $context,
  $info,
  $field_resolver,
  $type_name,
  $field_key,
  $field,
  $result
) {
  // If it's not a mutation we don't have do invalidate anything
  if ($type_name !== "RootMutation") return;

  // Otherwise we invalidate, depending on which mutation was executed
  switch ($field_key) {
    case 'createCategory':
      purge_category();
      break;
    case 'createComment':
      purge_comment();
      break;
    case 'createMediaItem':
      purge_media_item();
      break;
    case 'createPage':
      purge_page();
      break;
    case 'createPost':
      purge_post();
      break;
    case 'createPostFormat':
      purge_post_format();
      break;
    case 'createTag':
      purge_tag();
      break;
    case 'createUser':
      purge_user();
      break;
    case 'restoreComment':
      purge_comment();
      break;
  }
}



/**
 * Invalidate affected types when altering content via the WordPress dashboard
 * (see https://codex.wordpress.org/Plugin_API/Action_Reference)
 */

add_action('create_category', 'purge_category');
add_action('delete_category', 'purge_category');
add_action('edit_category', 'purge_category');

add_action('comment_post', 'purge_comment');
add_action('edit_comment', 'purge_comment');
add_action('deleted_comment', 'purge_comment');
add_action('spammed_comment', 'purge_comment');
add_action('trashed_comment', 'purge_comment');
add_action('unspammed_comment', 'purge_comment');
add_action('untrashed_comment', 'purge_comment');
add_action('wp_insert_comment', 'purge_comment');

add_action('deleted_post', 'purge_page');
add_action('edit_post', 'purge_page');
add_action('trashed_post', 'purge_page');
add_action('untrashed_post', 'purge_page');
add_action('wp_insert_post', 'purge_page');

add_action('deleted_post', 'purge_post');
add_action('edit_post', 'purge_post');
add_action('trashed_post', 'purge_post');
add_action('untrashed_post', 'purge_post');
add_action('wp_insert_post', 'purge_post');

add_action('delete_user', 'purge_user');
add_action('profile_updated', 'purge_user');
add_action('user_register', 'purge_user');



/**
 * Helper functions for purging via admin-api
 */

/**
 * @return null|string Returns `null` on success, an error message on failure
 */
function purge_all()
{
  return call_admin_api('mutation ($soft: Boolean) { _purgeAll(soft: $soft) }');
}

/**
 * @return null|string Returns `null` on success, an error message on failure
 */
function purge_category()
{
  return call_admin_api('mutation ($soft: Boolean) { purgeCategory(soft: $soft) }');
}

/**
 * @return null|string Returns `null` on success, an error message on failure
 */
function purge_comment()
{
  return call_admin_api('mutation ($soft: Boolean) { purgeComment(soft: $soft) }');
}

/**
 * @return null|string Returns `null` on success, an error message on failure
 */
function purge_media_item()
{
  return call_admin_api('mutation ($soft: Boolean) { purgeMediaItem(soft: $soft) }');
}

/**
 * @return null|string Returns `null` on success, an error message on failure
 */
function purge_page()
{
  return call_admin_api('mutation ($soft: Boolean) { purgePage(soft: $soft) }');
}

/**
 * @return null|string Returns `null` on success, an error message on failure
 */
function purge_post()
{
  return call_admin_api('mutation ($soft: Boolean) { purgePost(soft: $soft) }');
}

/**
 * @return null|string Returns `null` on success, an error message on failure
 */
function purge_post_format()
{
  return call_admin_api('mutation ($soft: Boolean) { purgePostFormat(soft: $soft) }');
}

/**
 * @return null|string Returns `null` on success, an error message on failure
 */
function purge_tag()
{
  return call_admin_api('mutation ($soft: Boolean) { purgeTag(soft: $soft) }');
}

/**
 * @return null|string Returns `null` on success, an error message on failure
 */
function purge_user()
{
  return call_admin_api('mutation ($soft: Boolean) { purgeUser(soft: $soft) }');
}

/**
 * @param string $query   The GraphQL operation
 * @return null|string    Returns `null` on success, an error message on failure
 */
function call_admin_api($query)
{
  $service_name = get_option('graphcdn_service_name');
  $token = get_option('graphcdn_purging_token');
  $soft = get_option('graphcdn_soft_purge') !== 'off';

  // If we don't have service name and token, we can't invalidate
  if (!$service_name || !$token) return false;

  $res = wp_remote_post('https://admin-dev.graphcdn.io/' . $service_name, array(
    'headers' => array(
      'content-type' => 'application/json',
      'graphcdn-token' => $token
    ),
    'body' => json_encode(array('query' => $query, 'variables' => array('soft' => $soft)))
  ));
  if ($res instanceof WP_Error) {
    return $res->get_error_message();
  }
  $is_success = $res['response']['code'] < 300;

  $body = json_decode($res['body']);
  if ($body === null) {
    // Body contains no JSON, success depends on the status code
    return $is_success ? null : $res['body'];
  }

  if (property_exists($body, 'errors')) {
    // return the first error
    return $body->errors[0]->message;
  }

  if (!$is_success) {
    // Not successful, but also no errors in body
    return "Unknown error";
  }

  return null;
}
