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

## Releasing a new version

WordPress uses Subversion (SVN) for release management. A detailed guide for
how to work with SVN can be found in the [WordPress documentation](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/).

Make sure you have `svn` installed on your machine for the following.

First, checkout the subversion repository on your local machine:

```sh
svn checkout https://plugins.svn.wordpress.org/graphcdn my-folder
```

Then, copy the current plugin code that you want to release into the `trunk`
subfolder. It makes sense to first clean the `trunk` folder to make sure that
no files remain that have already been removed in git.

```sh
rm -rf my-folder/trunk/*
cp /path/to/wp-graphcdn/* my-folder/trunk
```

Before moving on, make sure that the stable version in the `readme.txt` file 
is set to the version you're about to create.

Move into the folder that contains the SVN repository and add all the files 
you just changed:

```sh
cd my-folder
svn add trunk/*
```

To create the new tag, copy the trunk into a new folder in `tags`. We
strive to use semantic versioning, so replace `x`, `y`, and `z` with 
the approproate numbers.

```sh
svn copy trunk tags/x.y.z
```

Now you can commit the changes. This will also push the commit to the remote
SVN repository, so you need to authorize. Pass the username `graphcdn` via the
`--username` flag and enter the password when promted. You can find these 
credentials in our shared 1Password.

```sh
svn commit -m 'tagging x.y.z' --username graphcdn
```

## Updating assets or readme.txt

You can also just update assets or content of the `readme.txt` in a similar
way like outlined above. You can skip copying the trunk into a new version
in this case and just commit the changes to the trunk and/or the assets.
