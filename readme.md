# WordPress Deployer

[![packagist package version](https://img.shields.io/packagist/v/itsahappymedium/wp-deployer.svg?style=flat-square)](https://packagist.org/packages/itsahappymedium/wp-deployer)
[![packagist package downloads](https://img.shields.io/packagist/dt/itsahappymedium/wp-deployer.svg?style=flat-square)](https://packagist.org/packages/itsahappymedium/wp-deployer)
[![license](https://img.shields.io/github/license/itsahappymedium/wp-deployer.svg?style=flat-square)](license.md)

Deploy WordPress websites with ease.

This package is basically a [Deployer](https://deployer.org) "recipe" for WordPress websites.


## Installation

```
composer require-dev itsahappymedium/wp-deployer
```

You will then want to create a `deploy.php` file at the root of your project with the following contents:

```php
<?php
require_once('./vendor/autoload.php');
new WP_Deployer();
?>
```

*You can optionally pass a file path as a parameter to the `WP_Deployer` class to use a file other than `config.yml`.*

After that you should have the `dep` command available to you. Run it to confirm a successful installation and view a list of commands you can use.

*Note: Deployer and WP-CLI will be installed as dependencies. Deployer can be used via `vendor/bin/dep` and WP-CLI can be used via `vendor/bin/wp`.*

One last thing, you'll want to make sure that the following is in your `.gitignore` file to avoid them being commited to your repo:

```
/config.yml
/db_backups
/tmp
```


## Configuration

Create a `config.yml` file at the root of your project. Use [config-example.yml](config-example.yml) as a starting point.

**Note: You will want to make sure that you add your `config.yml` file to your `.gitignore` file so that it is not committed to your repo as this file will contain sensitive credentials.**


### Variables

These variables can be set in your `config.yml` file and/or via the `set` function in your `deploy.php` file:


#### WP-Deployer Variables

These variables are custom to WP-Deployer.

| Variable             | Default Value        | Description                                                                                                                                  |
|----------------------|----------------------|----------------------------------------------------------------------------------------------------------------------------------------------|
| **application**      |                      | The name of the application.                                                                                                                 |
| **database**         |                      | Database information. An object with `database`, `host`, `username`, and `password` set.                                                     |
| db_backups_path      | db_backups           | The path where database exports are stored. (This path should be added to `.gitignore`)                                                      |
| extra_files          | (See below)          | An array of files (or file globs) that are not included in your repo and should be uploaded during a deployment (Such as compiled files).    |
| **public_path**      | /var/www/public      | The path where your server expects the website to be located and available to the public.                                                    |
| templates_path       | templates            | The path where template files are located.                                                                                                   |
| tmp_path             | tmp                  | The path where temporary files are stored. (This path should be added to `.gitignore`)                                                       |
| **theme_name**       |                      | The name of your active theme.                                                                                                               |
| **url**              | http://localhost     | The URL to your website.                                                                                                                     |
| wp_config_constants  |                      | An array containing keys and values of constants to declare in your `wp-config.php` file.                                                    |
| wp_content_dir       | wp-content/content   | The path to your `content` directory where themes and plugins are located.                                                                   |
| wp_site_url          | (The value of `url`) | The URL to where your `wordpress` directory/core files is located.                                                                           |
| **wp_email**         |                      | The e-mail address to use for the default user when generating a new site database.                                                          |
| **wp_user**          |                      | The username to use for the default user when generating a new site database.                                                                |

\* **Bold variable names are required to be set.**

<details>
<summary>extra_files default value</summary>

```
{{wp_content_dir}}/themes/{{theme_name}}/*.css
{{wp_content_dir}}/themes/{{theme_name}}/*.map
{{wp_content_dir}}/themes/{{theme_name}}/js/*.js
{{wp_content_dir}}/themes/{{theme_name}}/js/*.map
```
</details>


#### Deployer Variabes

These variables are built-in to Deployer. You can read more here: https://deployer.org/docs/6.x/configuration.

| Variable                 | Default Value           | Description                                                                                                              |
|--------------------------|-------------------------|--------------------------------------------------------------------------------------------------------------------------|
| **branch**               |                         | Branch to deploy.                                                                                                        |
| cleanup_use_sudo         | false                   | Whether to use `sudo` with cleanup task.                                                                                 |
| clear_paths              | *(See below)*           | List of paths which need to be deleted in release after updating code.                                                   |
| clear_use_sudo           | false                   | Use or not `sudo` with clear_paths.                                                                                      |
| composer_action          | install                 | Composer action.                                                                                                         |
| composer_options         |                         | Options for Composer.                                                                                                    |
| copy_dirs                |                         | List of files to copy between release.                                                                                   |
| default_stage            | *staging*               | The default stage.                                                                                                       |
| **deploy_path**          | */var/www/public_html*  | Where to deploy application on remote host.                                                                              |
| env                      |                         | Array of environment variables.                                                                                          |
| git_recursive            | true                    | Set the `--recursive` flag for git clone.                                                                                |
| git_tty                  | *true*                  | Allocate TTY for git clone command. This allow you to enter a passphrase for keys or add host to `known_hosts`.          |
| **hostname**             |                         | The host IP address.                                                                                                     |
| http_user                |                         | User the web server runs as. If this parameter is not configured, deployer try to detect it from the process list.       |
| keep_releases            | 5                       | Number of releases to keep. (`-1` for unlimited)                                                                         |
| **repository**           |                         | Git repository of the application.                                                                                       |
| shared_dirs              | *(See below)*           | List of shared dirs.                                                                                                     |
| shared_files             | *(See below)*           | List of shared files.                                                                                                    |
| ssh_multiplexing         | *true*                  | Use ssh multiplexing to speedup the native ssh client.                                                                   |
| **stage**                |                         | The stage deploy to.                                                                                                     |
| use_atomic_symlink       | true                    | Whether to use atomic symlinks. By default deployer will detect if system supports atomic symlinks and use them.         |
| use_relative_symlink     | true                    | Whether to use relative symlinks. By default deployer will detect if the system supports relative symlinks and use them. |
| **user**                 | (Current git user name) | User to SSH into the server as.                                                                                          |
| writable_chmod_mode      | 0755                    | Mode for setting `writable_mode` in `chmod`.                                                                             |
| writable_chmod_recursive | true                    | Whether to set `chmod` on directories recursively or not.                                                                |
| writable_dirs            |                         | List of directories which must be writable for web server.                                                               |
| writable_mode            | acl                     | Writable mode. Options are `acl`, `chmod`, `chown`, `chqrp`.                                                             |
| writable_use_sudo        | false                   | Whether to use `sudo` with writable command.                                                                             |

\* **Bold variable names are required to be set.**

\* *Italic default values are custom to WP-Deployer.*

<details>
<summary>clear_paths default value</summary>

```
.git
templates
.gitignore
.lando.yml
composer.json
composer.lock
config.yml
deploy.php
readme.md
```
</details>

<details>
<summary>shared_dirs default value</summary>

```
content/uploads
```
</details>

<details>
<summary>shared_files default value</summary>

```
.htaccess
robots.txt
wp-config.php
```
</details>


### Overwriting Variables

You can easily overwrite any of the above variables by defining them in your `config.yml` file. You can also overwrite or add to them by using [Deployer Functions](https://deployer.org/docs/6.x/api) in your `deploy.php` file like this:

```php
<?php
require_once('./vendor/autoload.php');
new WP_Deployer();

use function Deployer\{ add, set };

add('extra_files', array(
  'scripts.js',
  'lib/bootstrap/bootstrap.css'
));

set('ssh_multiplexing', false);
?>
```


### Templates

Template files are as follows: `.htaccess`, `robots.txt`, and `wp-config.php`. Default templates for these files are included with WP-Deployer however custom ones can be made by placing them in your `templates` directory. We use [Twig](https://twig.symfony.com) to render the templates. View the `templates` directory in this package for examples.

A template can be associated with a specific stage by putting that stage name before the `.twig` file extension (for example: `robots.txt.staging.twig`).

*(Note: The ability to add custom templates here will be coming in the future.)*


## Usage

Run `dep list` and/or `dep help <command>` to get more information on these commands.

### `dep deploy <stage>`

Deploys your website to the `stage` (which is defined in your `config.yml` file).


### `dep db:<push/pull> <stage>`

Exports and downloads/uploads the database to/from `stage` and then imports it.


### `dep uploads:<push/pull> <stage>`

Downloads/uploads the contents of your `content/uploads` directory to/from `stage`.


### `dep db:fix`

Extracts the local copy of your database and replaces all occurances of `utf8mb4_unicode_520_ci` to `utf8mb4_unicode_ci` and then re-imports it. This solves [the issue](https://stackoverflow.com/questions/42385099/1273-unknown-collation-utf8mb4-unicode-520-ci) where the server is using a different version of MySQL than your local.


### `dep setup:<local/remote> <stage>`

Generates the templates files and sets up the database for WordPress if it hasn't been already. If a new database is set up, the username and password (randomly generated) will be shown in the terminal. Keep in mind that a new `wp-config.php` file will be generated with newly generated secrets.

### `dep setup:init <stage>`

A convience command; Runs the following commands in order: `dep setup:local <stage>`, `dep uploads:pull <stage>`, `dep db:pull <stage>`.


## License

MIT. See the [license.md file](license.md) for more info.
