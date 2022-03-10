# GraphCDN plugin for WordPress

This plugin is an addon for the popular [WPGraphQL](https://github.com/wp-graphql/wp-graphql)
plugin. It helps with setting up GraphCDN in front of a WordPress GraphQL API:

- Automatically invalidates the cache when adding, editing, or deleting content
  via the WordPress dashboard.
- Invalidates the cache when adding content via GraphQL mutations to avoid
  stale data in lists (see [here](https://docs.graphcdn.io/docs/how-to-invalidate-lists)).

This plugin only works when you already have the WPGraphQL plugin installed.
After adding this plugin you'll see a new menu item named "Caching" in the
"GraphQL" section of the dashboard sidebar. Here you can:

- Add the name of your GraphCDN service and a purging token (without that the
  plugin will effectively do nothing).
- Toggle between soft and hard purging. (Soft purging means that the cache
  will continue serving stale data even after the purge while the data is
  revalidated in the background.)
- Purge the entire GraphCDN cache right from the WordPress dashboard.

## Install

This plugin is not quite ready for production use yet. Once it is you can
install it like any other WordPress plugin:

- Download the zip-file for the latest [release](https://github.com/graphcdn/wp-graphcdn/releases)
- In your WordPress dashboard, go to "Plugins" -> "Add New" -> "Upload Plugin"
- Choose the zip-file you just downloaded and click "Install Now"

## Development

A very easy way to set up WordPress locally is [Local](https://localwp.com/).
Once you have a WordPress website running, you can link this folder into the
plugin folder by executing the following command in the folder where the local
WordPress site is located:

```sh
ln -s /path/to/this/repo/wp-graphcdn ./wp-content/plugins/wp-graphcdn
```

It's convenient to develop the plugin while having it installed and activated
in an actual WordPress site where you can play around with it!

TODO: Figure out how to ideomatically do local development for plugins.
