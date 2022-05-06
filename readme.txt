=== WPGraphQL ===
Tags: GraphCDN, GraphQL, WPGraphQL, API, Caching, Performance
Requires at least: 5.0
Requires PHP: 7.1
Stable tag: 0.1.0
License: GPL-3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

=== GraphCDN plugin for WordPress ===

This plugin is an addon for the popular [WPGraphQL](https://github.com/wp-graphql/wp-graphql)
plugin. It helps with setting up GraphCDN in front of a WordPress GraphQL API
by automatically taking care of invalidating the cache when content is updated
in WordPress. If works no matter the source of the update (WordPress admin 
panel, GraphQL mutation, REST API, etc.). It also supports custom post types 
and custom taxonomies that are exposed over the GraphQL API.

This plugin only works when you already have the WPGraphQL plugin installed.
After adding this plugin you'll see a new menu item named "Caching" in the
"GraphQL" section of the dashboard sidebar. Here you can:

- Add the name of your GraphCDN service and a purging token (without that the
  plugin will effectively do nothing).
- Toggle between soft and hard purging. (Soft purging means that the cache
  will continue serving stale data even after the purge while the data is
  revalidated in the background.)
- Purge the entire GraphCDN cache right from the WordPress dashboard.
