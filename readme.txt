=== Stellate ===
Tags: Stellate, GraphQL, WPGraphQL, API, Caching, Edge, Performance
Requires at least: 5.0
Tested up to: 6.4.0
Requires PHP: 7.1
Stable tag: 0.1.8
License: GPL-3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

=== Stellate plugin for WordPress ===

This plugin is an addon for the popular [WPGraphQL](https://github.com/wp-graphql/wp-graphql) plugin. It helps set up [Stellate](https://stellate.co) in front of a WordPress GraphQL API by automatically invalidating the cache when content is updated in WordPress. It works no matter the source of the update, whether that is via the WordPress admin panel, a GraphQL mutation, the REST API, or another method. It also supports custom post types and custom taxonomies that are exposed over the GraphQL API.

This plugin only works when you already have the WPGraphQL plugin installed. After adding this plugin, you'll see a new menu item named "Caching" in the "GraphQL" section of the dashboard sidebar. Here you can:

- Add the name of your Stellate service and a purging token (without that, the plugin will do nothing).
- Toggle between soft and hard purging. (Soft purging means the cache will continue serving stale data even after the purge while Stellate updates the data in the background.)
- Purge the entire Stellate cache right from the WordPress dashboard.

For more information, check out our [documentation](https://stellate.co/docs/integrations/wordpress-plugin).
