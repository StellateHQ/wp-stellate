<?php

/**
 * Plugin Name: GraphCDN
 * Plugin URI: https://github.com/graphcdn/wp-graphcdn
 * GitHub Plugin URI: https://github.com/graphcdn/wp-graphcdn
 * Description: GraphCDN for your WordPress GraphQL API
 * Author: GraphCDN
 * Author URI: https://graphcdn.io
 * Version: 0.1.0
 * Requires at least: 5.0
 * Tested up to: 5.9.3
 * Requires PHP: 7.1
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
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
    'graphcdn_render_caching_page'
  );
});

function graphcdn_render_caching_page()
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
              <input type="text" name="graphcdn_service_name" class="regular-text" value="<?php echo esc_attr($service_name) ?>" />
              <p><?php esc_attr_e('Enter the name of your GraphCDN service. Without this the GraphCDN plugin will do nothing.', 'WpAdminStyle'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row">Purging token</th>
            <td>
              <input type="password" name="graphcdn_purging_token" class="regular-text" value="<?php echo esc_attr($token) ?>" />
              <p><?php esc_attr_e('Enter a purging token created for the GraphCDN service entered above. Without this the GraphCDN plugin will do nothing.', 'WpAdminStyle'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row">Use soft purging</th>
            <td>
              <label>
                <input name="graphcdn_soft_purge" type="checkbox" <?php echo esc_attr($soft_purge) ?> />
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
            '<code>' . esc_html($_GET['failure']) . '</code>'
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
  $err = graphcdn_purge_all();
  if (!$err) do_action('graphcdn_purge', ['has_purged_all' => true]);
  $query = $err ? 'failure=' . urlencode($err) : 'success=true';
  wp_redirect('admin.php?page=graphql-caching&' . $query);
  exit;
});



/**
 * This global array stores all the stuff that we want to purge.
 */

$GLOBALS['gcdn_purges'] = [
  'has_purged_all' => false,
  'purged_types' => [],
  'Post' => [],
  'Page' => [],
  'Category' => [],
  'Tag' => [],
  'Comment' => [],
  'Menu' => [],
  'MenuItem' => [],
  'User' => []
];

$GLOBALS['gcdn_typename_map'] = [
  'post' => 'Post',
  'page' => 'Page',
  'nav_menu_item' => 'MenuItem'
];

$GLOBALS['gcdn_id_prefix_map'] = [
  'Post' => 'post',
  'Page' => 'post',
  'Category' => 'term',
  'Tag' => 'term',
  'Comment' => 'comment',
  'Menu' => 'term',
  'MenuItem' => 'post',
  'User' => 'user'
];

function graphcdn_add_purge_entity(string $key, $value)
{
  if (!in_array($value, $GLOBALS['gcdn_purges'][$key], true))
    $GLOBALS['gcdn_purges'][$key][] = $value;
}

add_action('registered_post_type', function (string $post_type, WP_Post_Type $post_type_object) {
  /**
   * Noting to do if the type is not exposed over GraphQL, or if the type 
   * names are not specified.
   */
  if (
    !isset($post_type_object->show_in_graphql)
    || !isset($post_type_object->graphql_single_name)
    || !$post_type_object->show_in_graphql
  ) return;

  /** Add an array to collect purges for this custom post type.  */
  $GLOBALS['gcdn_purges'][$post_type_object->graphql_single_name] = [];

  /** Extend the mapping from post type to GraphQL typename. */
  $GLOBALS['gcdn_typename_map'][$post_type] = $post_type_object->graphql_single_name;

  /** Extend the mapping from GraphQL typename to id prefix. */
  $GLOBALS['gcdn_id_prefix_map'][$post_type_object->graphql_single_name] = 'post';
}, 10, 2);

add_action('registered_taxonomy', function (string $taxonomy, $object_type, array $args) {
  /**
   * Noting to do if the type is not exposed over GraphQL, or if the type 
   * names are not specified.
   */
  if (
    !isset($args['show_in_graphql'])
    || !isset($args['graphql_single_name'])
    || !$args['show_in_graphql']
  ) return;

  /** Add an array to collect purges for this custom post type.  */
  $GLOBALS['gcdn_purges'][$args['graphql_single_name']] = [];

  /** Extend the mapping from post type to GraphQL typename. */
  $GLOBALS['gcdn_typename_map'][$taxonomy] = $args['graphql_single_name'];

  /** Extend the mapping from GraphQL typename to id prefix. */
  $GLOBALS['gcdn_id_prefix_map'][$args['graphql_single_name']] = 'term';

  /**
   * This runs when creating a new term.
   */
  add_action("created_{$taxonomy}", function () use ($args) {
    graphcdn_add_purge_entity('purged_types', $args['graphql_single_name']);
  });

  /**
   * This runs when updating an existing term.
   */
  add_action("edited_{$taxonomy}", function (int $term_id) use ($args) {
    graphcdn_add_purge_entity($args['graphql_single_name'], $term_id);
  });

  /**
   * This runs when deleting a term.
   */
  add_action("delete_${taxonomy}", function (int $term_id) use ($args) {
    graphcdn_add_purge_entity($args['graphql_single_name'], $term_id);
  });
}, 10, 3);

/**
 * This runs when inserting or updating any post type. This also includes 
 * pages and menu items.
 */
add_action('wp_insert_post', function (int $post_id, WP_Post $post, bool $update) {
  $type = $GLOBALS['gcdn_typename_map'][$post->post_type];
  if (!$type) return;

  if ($update) {
    /**
     * When a post or page has been updated, purge just this one post
     */
    graphcdn_add_purge_entity($type, $post_id);
  } else {
    /**
     * When a new post or page has been created, purge all things related to 
     * that entity
     */
    graphcdn_add_purge_entity('purged_types', $type);
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
    graphcdn_add_purge_entity('purged_types', 'Category');
    graphcdn_add_purge_entity('purged_types', 'Tag');
  }
}, 10, 3);

/**
 * This runs when deleting a post type. This also includes pages and menu 
 * items.
 */
add_action('deleted_post', function (int $post_id, WP_Post $post) {
  $type = $GLOBALS['gcdn_typename_map'][$post->post_type];
  if (!$type) return;
  graphcdn_add_purge_entity($type, $post_id);
}, 10, 2);

/**
 * This runs when creating a new category.
 */
add_action('created_category', function () {
  graphcdn_add_purge_entity('purged_types', 'Category');
});

/**
 * This runs when updating an existing category.
 */
add_action('edited_category', function (int $category_id) {
  graphcdn_add_purge_entity('Category', $category_id);
});

/**
 * This runs when deleting a category.
 */
add_action('delete_category', function (int $category_id) {
  graphcdn_add_purge_entity('Category', $category_id);
});

/**
 * This runs when creating a new tag.
 */
add_action('created_post_tag', function () {
  graphcdn_add_purge_entity('purged_types', 'Tag');
});

/**
 * This runs when updating an existing tag.
 */
add_action('edited_post_tag', function (int $tag_id) {
  graphcdn_add_purge_entity('Tag', $tag_id);
});

/**
 * This runs when deleting a tag.
 */
add_action('delete_post_tag', function (int $tag_id) {
  graphcdn_add_purge_entity('Tag', $tag_id);
});

/**
 * This runs when a new comment is created.
 */
add_action('wp_insert_comment', function () {
  graphcdn_add_purge_entity('purged_types', 'Comment');
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
  graphcdn_add_purge_entity('Comment', $comment_id);
});

/**
 * This runs when the content of a comment is updated.
 */
add_action('edit_comment', function (int $comment_id) {
  graphcdn_add_purge_entity('Comment', $comment_id);
});

/**
 * This runs when the count of comments for a post is updated.
 */
add_action('wp_update_comment_count', function (int $post_id) {
  graphcdn_add_purge_entity('Post', $post_id);
});

/**
 * This runs when a new menu is created.
 */
add_action('wp_create_nav_menu', function () {
  graphcdn_add_purge_entity('purged_types', 'Menu');
});

/**
 * This runs when a menu is deleted.
 */
add_action('wp_delete_nav_menu', function (int $menu_id) {
  graphcdn_add_purge_entity('Menu', $menu_id);
});

/**
 * This runs when a new user is created.
 */
add_action('user_register', function () {
  graphcdn_add_purge_entity('purged_types', 'User');
});

/**
 * This runs when an existing user is updated.
 */
add_action('profile_update', function (int $user_id) {
  graphcdn_add_purge_entity('User', $user_id);
});

/**
 * This runs when a user is deleted.
 */
add_action('delete_user', function (int $user_id) {
  graphcdn_add_purge_entity('User', $user_id);
});



/**
 * When all is done, call the admin API and purge all the entities that we
 * collected previously.
 */

function graphcdn_encode_ids(array $ids, string $type_prefix)
{
  return array_map(function ($id) use ($type_prefix) {
    return base64_encode($type_prefix . ':' . $id);
  }, $ids);
};

add_action('shutdown', function () {
  $variable_definitions = '$soft: Boolean';
  $selection_set = '';
  $variable_values = [];
  foreach ($GLOBALS['gcdn_purges'] as $key => $value) {
    switch ($key) {
      case 'has_purged_all':
        if ($value) {
          $selection_set .= '_purgeAll(soft: $soft)';
        }
        break;
      case 'purged_types':
        /** Handle types where all entities should by purged. */
        foreach ($value as $type) {
          $selection_set .= "purge{$type}(soft: \$soft)\n";
        }
        break;
      default:
        if (count($value) > 0) {
          /** Handle purging individual entities by their id. */
          $variable_name = "\${$key}Ids";
          $variable_definitions .= " {$variable_name}: [ID!]";
          $selection_set .= "purge{$key}ById: purge{$key}(soft: \$soft, id: {$variable_name})\n";
          $variable_values[$variable_name] = graphcdn_encode_ids($value, $GLOBALS['gcdn_id_prefix_map'][$key]);
        }
        break;
    }
  }

  /** Skip sending any request if there is nothing to purge. */
  if ($selection_set === '') return;

  $query = "mutation WPGraphCDNIntegration({$variable_definitions}) {
    {$selection_set}
  }";
  $err = graphcdn_call_admin_api($query, $variable_values);

  if ($err) {
    // Something went wrong, fall back to purging everything
    $GLOBALS['gcdn_purges']['has_purged_all'] = true;
    graphcdn_purge_all();
  }

  do_action('graphcdn_purge', $GLOBALS['gcdn_purges']);
});



/**
 * Helper functions for purging via admin-api
 */

/**
 * @return null|string Returns `null` on success, an error message on failure
 */
function graphcdn_purge_all()
{
  return graphcdn_call_admin_api('mutation ($soft: Boolean) { _purgeAll(soft: $soft) }', []);
}

/**
 * @param string $query      The GraphQL operation
 * @param array $variables   The variables passed with the operation
 * @return null|string       Returns `null` on success, an error message on failure
 */
function graphcdn_call_admin_api($query, $variables)
{
  $service_name = get_option('graphcdn_service_name');
  $token = get_option('graphcdn_purging_token');

  // If we don't have service name and token, we can't invalidate
  if (!$service_name || !$token) return false;

  $variables['soft'] = get_option('graphcdn_soft_purge') !== 'off';

  $res = wp_remote_post('https://admin.graphcdn.io/' . $service_name, array(
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
