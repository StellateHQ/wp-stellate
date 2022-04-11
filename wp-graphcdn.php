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

add_action('admin_init', function () {
  register_setting('graphcdn', 'graphcdn_service_name');
  register_setting('graphcdn', 'graphcdn_purging_token');
  register_setting('graphcdn', 'graphcdn_soft_purge');
});



/**
 * Add a "Settings" page for GraphCDN (part of the "GraphQL" Menu provided by WPGraphQL)
 */

add_action('admin_menu', function () {
  add_submenu_page(
    'graphiql-ide',
    __('WPGraphQL Settings', 'wp-graphql'),
    'Caching',
    'manage_options',
    'graphql-caching',
    'render_caching_page'
  );
});

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
            '<code>' . $_GET['failure'] . '</code>'
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

add_action('admin_post_graphcdn_purge_all', function () {
  $res = purge_all();
  $query = $res ? 'failure=' . urlencode($res) : 'success=true';
  wp_redirect('admin.php?page=graphql-caching&' . $query);
  exit;
});



/**
 * This global array stores all the stuff that we want to purge.
 */

$GLOBALS["gcdn_purges"] = [
  'purge_all' => [],
  'Post' => [],
  'Page' => [],
  'Category' => [],
  'Tag' => [],
  'Comment' => [],
  'Menu' => [],
  'MenuItem' => [],
  'User' => []
];



/**
 * Hook into all relevant database updates to populate the array above.
 */

function map_post_type_to_graphql_type(string $post_type)
{
  $map = [
    'post' => 'Post',
    'page' => 'Page',
    'nav_menu_item' => 'MenuItem'
  ];
  return $map[$post_type];
};

/**
 * This runs when inserting or updating any post type. This also includes 
 * pages and menu items.
 */
add_action('wp_insert_post', function (int $post_id, WP_Post $post, bool $update) {
  $type = map_post_type_to_graphql_type($post->post_type);
  if (!$type) return;

  if ($update) {
    /**
     * When a post or page has been updated, purge just this one post
     */
    $GLOBALS['gcdn_purges'][$type][] = $post_id;
  } else {
    /**
     * When a new post or page has been created, purge all things related to 
     * that entity
     */
    $GLOBALS['gcdn_purges']['purge_all'][] = $type;
  }

  /**
   * The "edit_category" action does not seem to be called when adding or 
   * removing a categories to posts. Same story for tags. But we do need 
   * to purge the cache for these types, because the count of linked posts
   * might have changed. So to be safe, we purge aggressively here.
   * 
   * TODO: Implement a more fine-grained purging for this case.
   */
  if ($type === 'Post') {
    $GLOBALS['gcdn_purges']['purge_all'][] = 'Category';
    $GLOBALS['gcdn_purges']['purge_all'][] = 'Tag';
  }
}, 10, 3);

/**
 * This runs when deleting a post type. This also includes pages and menu 
 * items.
 */
add_action('deleted_post', function (int $post_id, WP_Post $post) {
  $type = map_post_type_to_graphql_type($post->post_type);
  if (!$type) return;

  $GLOBALS['gcdn_purges'][$type][] = $post_id;
}, 10, 2);

/**
 * This runs when creating a new category.
 */
add_action('created_category', function () {
  $GLOBALS['gcdn_purges']['purge_all'][] = 'Category';
});

/**
 * This runs when updating an existing category.
 */
add_action('edited_category', function (int $category_id) {
  $GLOBALS['gcdn_purges']['Category'][] = $category_id;
});

/**
 * This runs when deleting a category.
 */
add_action('delete_category', function (int $category_id) {
  $GLOBALS['gcdn_purges']['Category'][] = $category_id;
});

/**
 * This runs when creating a new tag.
 */
add_action('created_post_tag', function () {
  $GLOBALS['gcdn_purges']['purge_all'][] = 'Tag';
});

/**
 * This runs when updating an existing tag.
 */
add_action('edited_post_tag', function (int $tag_id) {
  $GLOBALS['gcdn_purges']['Tag'][] = $tag_id;
});

/**
 * This runs when deleting a tag.
 */
add_action('delete_post_tag', function (int $tag_id) {
  $GLOBALS['gcdn_purges']['Tag'][] = $tag_id;
});

/**
 * This runs when a new comment is created.
 */
add_action('wp_insert_comment', function () {
  $GLOBALS['gcdn_purges']['purge_all'][] = 'Comment';
});

/**
 * This runs when the status of a comment is updated. This catches all of 
 * the following actions:
 * - Approving or un-approvind a comment
 * - Marking a comment as spam or not-spam
 * - Moving a comment to the trash
 * - Deleting a comment irreversibly
 */
add_action('wp_set_comment_status', function (int $comment_id) {
  $GLOBALS['gcdn_purges']['Comment'][] = $comment_id;
});

/**
 * This runs when the content of a comment is updated.
 */
add_action('edit_comment', function (int $comment_id) {
  $GLOBALS['gcdn_purges']['Comment'][] = $comment_id;
});

/**
 * This runs when the count of comments for a post is updated.
 */
add_action('wp_update_comment_count', function (int $post_id) {
  $GLOBALS['gcdn_purges']['Post'][] = $post_id;
});

/**
 * This runs when a new menu is created.
 */
add_action('wp_create_nav_menu', function () {
  $GLOBALS['gcdn_purges']['purge_all'][] = 'Menu';
});

/**
 * This runs when a menu is deleted.
 */
add_action('wp_delete_nav_menu', function (int $menu_id) {
  $GLOBALS['gcdn_purges']['Menu'][] = $menu_id;
});

/**
 * This runs when a new user is created.
 */
add_action('user_register', function () {
  $GLOBALS['gcdn_purges']['purge_all'][] = 'User';
});

/**
 * This runs when an existing user is updated.
 */
add_action('profile_update', function (int $user_id) {
  $GLOBALS['gcdn_purges']['User'][] = $user_id;
});

/**
 * This runs when a user is deleted.
 */
add_action('delete_user', function (int $user_id) {
  $GLOBALS['gcdn_purges']['User'][] = $user_id;
});



/**
 * When all is done, call the admin API and purge all the entities that we
 * collected previously.
 */

function encode_ids(array $ids, string $type_prefix)
{
  return array_map(function ($id) use ($type_prefix) {
    return base64_encode($type_prefix . ':' . $id);
  }, $ids);
};

add_action('shutdown', function () {
  /**
   * Check if there is anything to purge.
   */
  if (
    count($GLOBALS['gcdn_purges']['purge_all']) === 0
    && count($GLOBALS['gcdn_purges']['Post']) === 0
    && count($GLOBALS['gcdn_purges']['Page']) === 0
    && count($GLOBALS['gcdn_purges']['Category']) === 0
    && count($GLOBALS['gcdn_purges']['Tag']) === 0
    && count($GLOBALS['gcdn_purges']['Comment']) === 0
    && count($GLOBALS['gcdn_purges']['Menu']) === 0
    && count($GLOBALS['gcdn_purges']['MenuItem']) === 0
    && count($GLOBALS['gcdn_purges']['User']) === 0
  ) return;

  /**
   * Note that we don't deduplicate at all in the following. The admin api 
   * will take care of that.
   */
  $query = '
    mutation WPGraphCDNIntegration(
      $soft: Boolean
      $postIds: [ID!]
      $pageIds: [ID!]
      $categoryIds: [ID!]
      $tagIds: [ID!]
      $commentIds: [ID!]
      $menuIds: [ID!]
      $menuItemIds: [ID!]
      $userIds: [ID!]
    ) { 
      purgePostById: purgePost(soft: $soft, id: $postIds)
      purgePageById: purgePage(soft: $soft, id: $pageIds)
      purgeCategoryById: purgeCategory(soft: $soft, id: $categoryIds)
      purgeTagById: purgeTag(soft: $soft, id: $tagIds)
      purgeCommentById: purgeComment(soft: $soft, id: $commentIds)
      purgeMenuById: purgeMenu(soft: $soft, id: $menuIds)
      purgeMenuItemById: purgeMenuItem(soft: $soft, id: $menuItemIds)
      purgeUserById: purgeUser(soft: $soft, id: $userIds)
  ';

  foreach ($GLOBALS['gcdn_purges']['purge_all'] as $type)
    $query .= 'purge' . $type . '(soft: $soft)' . "\n";

  $query .= '}';

  $res = call_admin_api($query, [
    'postIds' => encode_ids($GLOBALS['gcdn_purges']['Post'], 'post'),
    'pageIds' => encode_ids($GLOBALS['gcdn_purges']['Page'], 'post'),
    'categoryIds' => encode_ids($GLOBALS['gcdn_purges']['Category'], 'term'),
    'tagIds' => encode_ids($GLOBALS['gcdn_purges']['Tag'], 'term'),
    'commentIds' => encode_ids($GLOBALS['gcdn_purges']['Comment'], 'comment'),
    'menuIds' => encode_ids($GLOBALS['gcdn_purges']['Menu'], 'term'),
    'menuItemIds' => encode_ids($GLOBALS['gcdn_purges']['MenuItem'], 'post'),
    'userIds' => encode_ids($GLOBALS['gcdn_purges']['User'], 'user'),
  ]);

  if ($res) {
    // Something went wrong, fall back to purging everything
    purge_all();
  }
});



/**
 * Helper functions for purging via admin-api
 */

/**
 * @return null|string Returns `null` on success, an error message on failure
 */
function purge_all()
{
  return call_admin_api('mutation ($soft: Boolean) { _purgeAll(soft: $soft) }', []);
}

/**
 * @param string $query      The GraphQL operation
 * @param array $variables   The variables passed with the operation
 * @return null|string       Returns `null` on success, an error message on failure
 */
function call_admin_api($query, $variables)
{
  $service_name = get_option('graphcdn_service_name');
  $token = get_option('graphcdn_purging_token');

  // If we don't have service name and token, we can't invalidate
  if (!$service_name || !$token) return false;

  $variables['soft'] = get_option('graphcdn_soft_purge') !== 'off';

  $res = wp_remote_post('https://admin-dev.graphcdn.io/' . $service_name, array(
    'headers' => array(
      'content-type' => 'application/json',
      'graphcdn-token' => $token
    ),
    'body' => json_encode(array('query' => $query, 'variables' => $variables))
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
