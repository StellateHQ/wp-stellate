# Development

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
