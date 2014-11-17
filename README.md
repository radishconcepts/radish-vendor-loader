# Radish Vendor Loader
This enables WordPress to load plugins from the `vendor-plugins` directory, which can then be ignored in the version control system.

**Warning:** This is not really a stable build and should probably not be used in a production environment yet. We are using this in our development environments already and are improving it as we go. Please [report issues](https://github.com/radishconcepts/radish-vendor-loader/issues) if you come across any, or suggest changes via a pull request.

## How to install
There are a couple ways to install this plugin in your WordPress site.

### Install as Composer dependency
You can either load this plugin as a Composer dependency (loading it in `mu-plugins`, so it's always on, but you need to use a [proxy PHP loader file](http://codex.wordpress.org/Must_Use_Plugins#Caveats) in there as well):

```
"repositories": {
	"radish-vendor-loader": {
		"type": "vcs",
		"url":  "git@github.com:radishconcepts/radish-vendor-loader.git"
	},
},
"extra": {
        "installer-paths": {
            "wp-content/mu-plugins/radish-vendor-loader/": ["radishconcepts/radish-vendor-loader"]
        }
    },
"require": {
		"radishconcepts/radish-vendor-loader": "0.1.*"
    }
```

### Install manually
Alternatively you can checkout this repository in your `wp-content/plugins` (or `mu-plugins`) and run `composer install` to install the autoloader and dependencies.

### Install via WordPress.org
We have submitted this plugin to the [WordPress.org plugins directory](https://wordpress.org/plugins/), but it hasn't been approved there yet. When it does get approved there, you can simply install the plugin as you would install any plugin.

## Credits
This plugin is based on the [WP-Plugin-Directories plugin](https://github.com/chrisguitarguy/WP-Plugin-Directories) by Christopher Davis, Franz Josef Kaiser and Julien Chaumond, but has been changed to support our own workflow better.