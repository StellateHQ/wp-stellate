<?php

/**
 * Plugin Name: Stellate
 * Plugin URI: https://github.com/StellateHQ/wp-stellate
 * GitHub Plugin URI: https://github.com/StellateHQ/wp-stellate
 * Description: Stellate for your WordPress GraphQL API
 * Author: Stellate
 * Author URI: https://stellate.co
 * Version: 0.2.0
 * Requires at least: 5.0
 * Tested up to: 6.4.0
 * Requires PHP: 7.1
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package  Stellate
 * @author   Stellate
 * @version  0.2.0
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
  register_setting('stellate', 'stellate_service_name');
  register_setting('stellate', 'stellate_purging_token', [
    'sanitize_callback' => function ($token) {
      $hasChanged = (bool) $_POST["stellate_touched_purging_token"];

      return $hasChanged ? $token : get_option('stellate_purging_token');
    }
  ]);
  register_setting('stellate', 'stellate_soft_purge');
});



/**
 * Add a "Settings" page for Stellate (part of the "GraphQL" Menu provided by WPGraphQL)
 */

add_action('admin_menu', function () {
  add_submenu_page(
    'graphiql-ide',
    __('WPGraphQL Settings', 'wp-graphql'),
    'Caching',
    'manage_options',
    'graphql-caching',
    'stellate_render_caching_page'
  );
});

function stellate_render_caching_page()
{
  $service_name = get_option('stellate_service_name');
  $concealed_token = str_repeat('*', strlen(get_option('stellate_purging_token')));
  $soft_purge = get_option('stellate_soft_purge') === 'on' ? 'checked' : '';
?>
  <?php echo get_option('stellate_purging_token') ?>
  <div class="wrap">
    <h2>GraphQL Edge Caching with Stellate</h2>

    <?php
    $smart_cache_active = stellate_is_smart_cache_active();
    $smart_cache_object_cache_on = stellate_smart_cache_object_cache_enabled();
    $smart_cache_settings_url = admin_url('admin.php?page=graphql-settings');
    $smart_cache_install_url = admin_url('plugin-install.php?tab=plugin-information&plugin=wpgraphql-smart-cache');
    ?>

    <?php if ($smart_cache_active && $smart_cache_object_cache_on): ?>
      <div class="notice notice-warning">
        <p>
          <strong>⚠️ Smart Cache object cache is enabled.</strong>
          Your WordPress origin is caching GraphQL responses that Stellate's
          edge already caches — this adds memory pressure on your server
          without any speed benefit.
        </p>
        <p>
          Open the
          <a href="<?php echo esc_url($smart_cache_settings_url); ?>">WPGraphQL settings</a>
          and turn off Smart Cache's <em>"Use Object Cache"</em> option.
          The invalidation events that Stellate relies on will keep working.
        </p>
      </div>
    <?php elseif ($smart_cache_active): ?>
      <div class="notice notice-success is-dismissible">
        <p>
          <strong>✓ Smart Cache adapter active.</strong>
          Using WPGraphQL Smart Cache for comprehensive event detection
          (post meta, term relationships, comments, custom fields, and more).
          Stellate handles caching at the edge.
        </p>
      </div>
    <?php else: ?>
      <div class="notice notice-info">
        <p>
          <strong>💡 Recommended: install WPGraphQL Smart Cache</strong>
          for richer invalidation coverage — ACF / custom field changes,
          taxonomy assignments, comment lifecycle, author archive refresh,
          and smart filtering of autosaves and drafts.
        </p>
        <p>
          Once installed and activated, Stellate detects it automatically and
          switches to adapter mode. <strong>After activating</strong>, open
          <a href="<?php echo esc_url($smart_cache_settings_url); ?>">WPGraphQL settings</a>
          and turn off Smart Cache's <em>"Use Object Cache"</em> option —
          Stellate's edge replaces that layer.
        </p>
        <?php if (current_user_can('install_plugins')): ?>
          <p>
            <a href="<?php echo esc_url($smart_cache_install_url); ?>" class="button button-primary">
              Install Smart Cache
            </a>
          </p>
        <?php else: ?>
          <p>
            <em>Ask a site administrator with plugin-install permissions to add WPGraphQL Smart Cache.</em>
          </p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <h3>Settings</h3>
    <form action="options.php" method="POST" autocomplete="off">
      <?php
      settings_fields('stellate');
      do_settings_sections('stellate');
      ?>
      <table class="form-table" role="presentation">
        <tbody>
          <tr>
            <th scope="row">Service name</th>
            <td>
              <input type="text" name="stellate_service_name" class="regular-text" value="<?php echo esc_attr($service_name) ?>" />
              <p><?php esc_attr_e('Enter the name of your Stellate service. Without this the Stellate plugin will do nothing.', 'WpAdminStyle'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row">Purging token</th>
            <td>
              <input type="password" name="stellate_purging_token" class="regular-text" value="<?php echo esc_attr($concealed_token) ?>" />
              <input type="hidden" name="stellate_touched_purging_token" value="0" />
              <noscript>
                <input type="hidden" name="stellate_touched_purging_token" value="1" />
              </noscript>
              <p><?php esc_attr_e('Enter a purging token created for the Stellate service entered above. Without this the Stellate plugin will do nothing.', 'WpAdminStyle'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row">Use soft purging</th>
            <td>
              <label>
                <input name="stellate_soft_purge" type="checkbox" <?php echo esc_attr($soft_purge) ?> />
                <span><?php esc_attr_e('When using soft purging, the cache continues to serve stale data while it is revalidated in the background.', 'WpAdminStyle'); ?></span>
              </label>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button() ?>
    </form>
    <script>
      document.querySelector('input[name="stellate_purging_token"]').addEventListener('input', function() {
        document.querySelector('input[name="stellate_touched_purging_token"]').value = 1;
      });
    </script>

    <h3>Purge the entire cache</h3>
    <p>By clicking the following button you purge all contents from the cache of your Stellate service.</p>
    <form action="admin-post.php" method="POST">
      <input type="hidden" name="action" value="stellate_purge_all" />
      <?php submit_button('Purge', 'secondary') ?>
    </form>
    <?php if (isset($_GET['success'])) { ?>
      <div class="notice notice-success inline">
        <p>Purging succeeded!</p>
      </div>
    <?php } ?>
    <?php if (isset($_GET['failure'])) { ?>
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

add_action('admin_post_stellate_purge_all', function () {
  $err = stellate_purge_all();
  if (!$err) do_action('stellate_purge', ['has_purged_all' => true]);
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

function stellate_add_purge_entity(string $key, $value)
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
    stellate_add_purge_entity('purged_types', $args['graphql_single_name']);
  });

  /**
   * This runs when updating an existing term.
   */
  add_action("edited_{$taxonomy}", function (int $term_id) use ($args) {
    stellate_add_purge_entity($args['graphql_single_name'], $term_id);
  });

  /**
   * This runs when deleting a term.
   */
  add_action("delete_${taxonomy}", function (int $term_id) use ($args) {
    stellate_add_purge_entity($args['graphql_single_name'], $term_id);
  });
}, 10, 3);



/**
 * Register the legacy event listeners that translate WordPress events
 * directly into Stellate purge mutations. Used as the default mode when
 * the WPGraphQL Smart Cache plugin is NOT installed.
 *
 * When Smart Cache IS installed, the adapter mode (defined below) handles
 * event translation instead, and these listeners are not registered.
 */
function stellate_register_legacy_hooks()
{

/**
 * This runs when inserting or updating any post type. This also includes
 * pages and menu items.
 */
add_action('wp_insert_post', function (int $post_id, WP_Post $post, bool $update) {
  if (!array_key_exists($post->post_type, $GLOBALS['gcdn_typename_map'])) return;
  $type = $GLOBALS['gcdn_typename_map'][$post->post_type];

  if ($update) {
    /**
     * When a post or page has been updated, purge just this one post
     */
    stellate_add_purge_entity($type, $post_id);
  } else {
    /**
     * When a new post or page has been created, purge all things related to
     * that entity
     */
    stellate_add_purge_entity('purged_types', $type);
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
    stellate_add_purge_entity('purged_types', 'Category');
    stellate_add_purge_entity('purged_types', 'Tag');
  }
}, 10, 3);

/**
 * This runs when deleting a post type. This also includes pages and menu
 * items.
 */
add_action('deleted_post', function (int $post_id, WP_Post $post) {
  if (!array_key_exists($post->post_type, $GLOBALS['gcdn_typename_map'])) return;
  $type = $GLOBALS['gcdn_typename_map'][$post->post_type];
  stellate_add_purge_entity($type, $post_id);
}, 10, 2);

/**
 * This runs when creating a new category.
 */
add_action('created_category', function () {
  stellate_add_purge_entity('purged_types', 'Category');
});

/**
 * This runs when updating an existing category.
 */
add_action('edited_category', function (int $category_id) {
  stellate_add_purge_entity('Category', $category_id);
});

/**
 * This runs when deleting a category.
 */
add_action('delete_category', function (int $category_id) {
  stellate_add_purge_entity('Category', $category_id);
});

/**
 * This runs when creating a new tag.
 */
add_action('created_post_tag', function () {
  stellate_add_purge_entity('purged_types', 'Tag');
});

/**
 * This runs when updating an existing tag.
 */
add_action('edited_post_tag', function (int $tag_id) {
  stellate_add_purge_entity('Tag', $tag_id);
});

/**
 * This runs when deleting a tag.
 */
add_action('delete_post_tag', function (int $tag_id) {
  stellate_add_purge_entity('Tag', $tag_id);
});

/**
 * This runs when a new comment is created.
 */
add_action('wp_insert_comment', function () {
  stellate_add_purge_entity('purged_types', 'Comment');
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
  stellate_add_purge_entity('Comment', $comment_id);
});

/**
 * This runs when the content of a comment is updated.
 */
add_action('edit_comment', function (int $comment_id) {
  stellate_add_purge_entity('Comment', $comment_id);
});

/**
 * This runs when the count of comments for a post is updated.
 */
add_action('wp_update_comment_count', function (int $post_id) {
  stellate_add_purge_entity('Post', $post_id);
});

/**
 * This runs when a new menu is created.
 */
add_action('wp_create_nav_menu', function () {
  stellate_add_purge_entity('purged_types', 'Menu');
});

/**
 * This runs when a menu is deleted.
 */
add_action('wp_delete_nav_menu', function (int $menu_id) {
  stellate_add_purge_entity('Menu', $menu_id);
});

/**
 * This runs when a new user is created.
 */
add_action('user_register', function () {
  stellate_add_purge_entity('purged_types', 'User');
});

/**
 * This runs when an existing user is updated.
 */
add_action('profile_update', function (int $user_id) {
  stellate_add_purge_entity('User', $user_id);
});

/**
 * This runs when a user is deleted.
 */
add_action('delete_user', function (int $user_id) {
  stellate_add_purge_entity('User', $user_id);
});

} // end of stellate_register_legacy_hooks



/**
 * Smart Cache adapter mode.
 *
 * When the WPGraphQL Smart Cache plugin is active, we delegate event detection
 * to it. Smart Cache fires `do_action('graphql_purge', $key, $event, $hostname)`
 * with one of four key shapes:
 *
 *   1. Relay global ID (base64 of "<type>:<id>", e.g. "cG9zdDoxMjM=" = "post:123")
 *   2. "list:<lowercase_type>"     — when a list-shaped query goes stale
 *   3. "skipped:<lowercase_type>"  — header-overflow fallback
 *   4. "graphql:Query"             — purge everything
 *
 * Our translator decodes each key and queues an equivalent entry into
 * $GLOBALS['gcdn_purges'], which the existing shutdown handler then sends to
 * Stellate's admin API as a single batched mutation.
 *
 * Smart Cache provides what wp-stellate does not on its own: post meta tracking
 * (ACF, custom fields), term relationship tracking, comprehensive filters
 * (autosaves, private meta, draft-to-draft), comment lifecycle, menu visibility,
 * and interface type expansion.
 */

function stellate_is_smart_cache_active()
{
  // Smart Cache defines this constant unconditionally at the top of its main
  // plugin file, so it's available before any 'init' or 'plugins_loaded'
  // hooks fire — more reliable than checking for an autoloaded class.
  return defined('WPGRAPHQL_SMART_CACHE_VERSION');
}

/**
 * Check whether Smart Cache's GraphQL object cache layer is enabled. When it
 * is, the WordPress origin caches GraphQL responses that Stellate's edge is
 * already caching — wasted memory with no speed benefit, since Stellate is
 * still going to serve from the edge.
 *
 * Returns false safely if Smart Cache is missing or its API surface changed.
 */
function stellate_smart_cache_object_cache_enabled()
{
  if (!stellate_is_smart_cache_active()) return false;
  if (!class_exists('\WPGraphQL\SmartCache\Admin\Settings')) return false;
  if (!method_exists('\WPGraphQL\SmartCache\Admin\Settings', 'caching_enabled')) return false;
  return (bool) \WPGraphQL\SmartCache\Admin\Settings::caching_enabled();
}

function stellate_register_adapter_hooks()
{
  add_action('graphql_purge', 'stellate_translate_graphql_purge_key', 10, 3);
}

function stellate_translate_graphql_purge_key($key, $event = '', $hostname = '')
{
  // Purge everything
  if ($key === 'graphql:Query') {
    $GLOBALS['gcdn_purges']['has_purged_all'] = true;
    return;
  }

  // list:<type> — purge all responses of this type (catches empty-list staleness)
  if (strpos($key, 'list:') === 0) {
    $type = stellate_resolve_graphql_type(substr($key, 5));
    if ($type !== null) {
      stellate_ensure_purge_bucket($type);
      stellate_add_purge_entity('purged_types', $type);
    }
    return;
  }

  // skipped:<type> — Smart Cache emits these as a safety net for CDN
  // architectures with HTTP header size limits (X-GraphQL-Keys caps at
  // 4000 bytes). When a response is tagged with too many node IDs to fit
  // in the header, the truncated portion is represented by 'skipped:<type>'
  // so the CDN can broadly invalidate that type.
  //
  // Stellate's edge does its own server-side tagging without header limits
  // — the specific Relay IDs that fire alongside every 'skipped:<type>'
  // are sufficient for precise invalidation. Translating 'skipped:*' here
  // would only cause over-purging (e.g., one category change purging all
  // Categories, Tags, PostFormats, etc.).
  if (strpos($key, 'skipped:') === 0) {
    return;
  }

  // Otherwise, treat as Relay global ID: base64("<type>:<id>")
  $decoded = base64_decode($key, true);
  if ($decoded === false || strpos($decoded, ':') === false) return;

  list($type_prefix, $id) = explode(':', $decoded, 2);
  if (!is_numeric($id)) return;

  // 'term' is a generic Smart Cache prefix for any taxonomy term — resolve
  // it to the actual taxonomy's GraphQL type via a term lookup.
  if ($type_prefix === 'term') {
    $term = get_term((int) $id);
    if (!is_wp_error($term) && $term instanceof WP_Term) {
      if (isset($GLOBALS['gcdn_typename_map'][$term->taxonomy])) {
        $type = $GLOBALS['gcdn_typename_map'][$term->taxonomy];
        stellate_ensure_purge_bucket($type);
        stellate_add_purge_entity($type, (int) $id);
      }
    }
    return;
  }

  $type = stellate_resolve_graphql_type($type_prefix);
  if ($type === null) return;

  stellate_ensure_purge_bucket($type);
  stellate_add_purge_entity($type, (int) $id);
}

/**
 * Resolve a lowercase type slug (from Smart Cache) to the GraphQL typename
 * used as a bucket key in $GLOBALS['gcdn_purges'].
 *
 * Smart Cache uses lowercase slugs like "post", "page", "category". Our
 * typename map gets populated with whatever WPGraphQL set as graphql_single_name
 * — typically lowercase too. We also handle the capitalized hardcoded init
 * buckets ('Post', 'Page', etc.) as a fallback.
 */
function stellate_resolve_graphql_type($lowercase_type)
{
  // Direct match (most common case)
  if (array_key_exists($lowercase_type, $GLOBALS['gcdn_purges'])) {
    return $lowercase_type;
  }

  // Case-insensitive scan of the typename map
  foreach ($GLOBALS['gcdn_typename_map'] as $graphql_type) {
    if (strtolower($graphql_type) === strtolower($lowercase_type)) {
      return $graphql_type;
    }
  }

  // Capitalized fallback (matches hardcoded init buckets)
  $capitalized = ucfirst($lowercase_type);
  if (array_key_exists($capitalized, $GLOBALS['gcdn_purges'])) {
    return $capitalized;
  }

  return null;
}

/**
 * Ensure a bucket exists for the type in $GLOBALS['gcdn_purges']. Smart Cache
 * may emit purges for types whose registered_post_type / registered_taxonomy
 * callback didn't run with show_in_graphql=true (e.g. cross-request flows).
 */
function stellate_ensure_purge_bucket($type)
{
  if (!isset($GLOBALS['gcdn_purges'][$type])) {
    $GLOBALS['gcdn_purges'][$type] = [];
  }
}



/**
 * On plugins_loaded (priority 20, after Smart Cache loads), decide which mode
 * we're in and register the appropriate hooks. Exactly one mode runs.
 */
add_action('plugins_loaded', function () {
  if (stellate_is_smart_cache_active()) {
    stellate_register_adapter_hooks();
  } else {
    stellate_register_legacy_hooks();
  }
}, 20);



/**
 * When all is done, call the admin API and purge all the entities that we
 * collected previously.
 */
function stellate_encode_ids_as_key_field_inputs(string $key, array $ids)
{
  $type_prefix = $GLOBALS['gcdn_id_prefix_map'][$key];
  $type = ucfirst($key);

  return array_map(function ($id) use ($type_prefix) {
    $encoded_id = base64_encode($type_prefix . ':' . $id);
    return array("name"=>"id", "value"=>$encoded_id);
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
          $uppercase_type = ucfirst($type);
          $selection_set .= "purge{$uppercase_type}: _purgeType(type: \"{$uppercase_type}\", soft: \$soft)\n";
        }
        break;
      default:
        if (count($value) > 0) {
          /** Handle purging individual entities by their id. */
          $uppercase_key = ucfirst($key);
          $variable_name = "{$key}Ids";
          $variable_definitions .= " \${$variable_name}: [KeyFieldInput!]";
          $selection_set .= "purge{$uppercase_key}ById: _purgeType(type: \"{$uppercase_key}\", soft: \$soft, keyFields: \${$variable_name})\n";
          $variable_values[$variable_name] = stellate_encode_ids_as_key_field_inputs($key, $value);
        }
        break;
    }
  }

  /** Skip sending any request if there is nothing to purge. */
  if ($selection_set === '') return;

  $query = "mutation WPStellateIntegration({$variable_definitions}) {
    {$selection_set}
  }";
  $err = stellate_call_admin_api($query, $variable_values);

  if ($err) {
    // Something went wrong, fall back to purging everything
    $GLOBALS['gcdn_purges']['has_purged_all'] = true;
    stellate_purge_all();
  }

  do_action('stellate_purge', $GLOBALS['gcdn_purges']);
});



/**
 * Helper functions for purging via admin-api
 */

/**
 * @return null|string Returns `null` on success, an error message on failure
 */
function stellate_purge_all()
{
  return stellate_call_admin_api('mutation WPStellateIntegration_PurgeAll ($soft: Boolean) { _purgeAll(soft: $soft) }', []);
}

/**
 * @param string $query      The GraphQL operation
 * @param array $variables   The variables passed with the operation
 * @return null|string       Returns `null` on success, an error message on failure
 */
function stellate_call_admin_api($query, $variables)
{
  $service_name = get_option('stellate_service_name');
  $token = get_option('stellate_purging_token');

  // If we don't have service name and token, we can't invalidate
  if (!$service_name || !$token) return false;

  $variables['soft'] = get_option('stellate_soft_purge') === 'on';

  $res = wp_remote_post('https://admin.stellate.co/' . $service_name, array(
    'headers' => array(
      'content-type' => 'application/json',
      'stellate-token' => $token
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
