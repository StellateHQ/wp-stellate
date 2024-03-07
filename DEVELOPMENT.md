# Development

A very easy way to set up WordPress locally is [Local](https://localwp.com/).
It's convenient to develop the plugin while having it installed and activated
in an actual WordPress site where you can play around with it!

> **Note**
> You don't need to create an account to be able to create a new local Wordpress instance

Once you have a WordPress website running, click "Go to site folder" to open your installation in Finder.
You can drag & drop this folder into your shell of choice to get the path pasted. Then navigate to the actual WordPress installation in `app/public`

```sh
cd <your site installation path>
cd app/public/
```

You can now link this folder into the plugin folder by executing the following command in the folder where the local
WordPress site is located:

> **Note**
> An easy way to obtain the path where this repo lives, is running `pwd`

```sh
ln -s /path/to/this/repo/wp-stellate ./wp-content/plugins/wp-stellate
```

Next, open up the WP Admin panel and log in with the credentials you entered during WordPress installation.
On the left, navigate to "Plugins" and add a new one. Search for "GraphQL" and install the "WPGraphQL" plugin and activate it.

Now you should see in the left navigation a "GraphQL" section that contains a sub-item called "Caching". In there, configure the service name and purging token to use.

Debugging in WordPress can be hard, you can try your luck with some plugins, or stay oldschool:

```php
function stellate_log( $msg, $name = '' )
{
    // Print the name of the calling function if $name is left empty
    $trace=debug_backtrace();
    $name = ( '' == $name ) ? $trace[1]['function'] : $name;

    $error_dir = '/path/to/wordpress/installation/app/public/wp-content/stellate-plugin.log';
    $msg = print_r( $msg, true );
    $log = $name . "  |  " . $msg . "\n";
    error_log( $log, 3, $error_dir );
}

stellate_log('something you want to see printed');
```

## Releasing a new version

WordPress uses Subversion (SVN) for release management. A detailed guide for
how to work with SVN can be found in the [WordPress documentation](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/).

Make sure you have `svn` installed on your machine for the following.

First, checkout the subversion repository on your local machine:

```sh
svn checkout https://plugins.svn.wordpress.org/stellate my-folder
```

Then, copy the current plugin code that you want to release into the `trunk`
subfolder. It makes sense to first clean the `trunk` folder to make sure that
no files remain that have already been removed in git.

```sh
rm -rf my-folder/trunk/*
cp /path/to/wp-stellate/* my-folder/trunk
```

Before moving on, make sure that the stable version in the `readme.txt` file
is set to the version you're about to create.

Move into the folder that contains the SVN repository and add all the files
you just changed:

```sh
cd my-folder
svn add trunk/*
```

> **Note**
> This command will likely "fail" for all files that did already exist in a prior version.
> We run this command to make sure that any newly added files are known to the SVN system.
> There is no "staging area" like in git and all changes to existing files will be synced in the command below by copying to a new tag.

To create the new tag, copy the trunk into a new folder in `tags`. We
strive to use semantic versioning, so replace `x`, `y`, and `z` with
the approproate numbers.

```sh
svn copy trunk tags/x.y.z
```

Now you can commit the changes. This will also push the commit to the remote
SVN repository, so you need to authorize. Pass the username `stellatehq` via
the `--username` flag and enter the password when promted. You can find these
credentials in our shared 1Password.

```sh
svn commit -m 'tagging x.y.z' --username stellatehq
```

## Updating assets or readme.txt

You can also just update assets or content of the `readme.txt` in a similar
way like outlined above. You can skip copying the trunk into a new version
in this case and just commit the changes to the trunk and/or the assets.
