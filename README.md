_Note, this has been decommissioned in favor of [https://github.com/woocommerce/sift-for-woocommerce](https://github.com/woocommerce/sift-for-woocommerce)_

# Sift Decisions

This plugin will integrate with Sift Science's fraud detection suite and WooCommerce's decisions API.

## Folder Structure

This plugin has the following folder structure:

```
sift-decisions/
├── languages/
├── src/
│   ├ ...
│   └── inc/
└── sift-decisions.php
```

- languages: Contains the translation files for your plugin.
- src: A folder for organizing the plugin's main PHP classes or code components, such as integrations with other plugins or services. These classes should be organized into subfolders following the [PSR-4](https://www.php-fig.org/psr/psr-4/) convention. `Composer` will handle the autoloading for these classes.
- plugin-name.php: The main PHP file containing the plugin header and bootstraping functionality.

## Documentation

As you develop your plugin, update the README.md file with detailed information about your plugin's features, usage, installation, and any other pertinent information.

## Local Development

1. `npm install`
2. `composer install`
3. `npx wp-env start` -- This starts a local WordPress environment available at <http://localhost:8888>
4. `npm test`

### Alternative Testing

You can run the tests using the local PHP environment by running the database on docker.

1. `docker run -d --rm -p 3306:3306 -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=wordpress_tests --name db mariadb`
2. `bin/install-wp-tests.sh wordpress_tests root root 127.0.0.1 latest true`
3. Install woocommerce somewhere on your machine and set the WOO_TEST_DIR environment variable to point to it.
4. `WOO_TEST_DIR=~/.wp-env/d4a3fd8a8a0a78829498afef8ce99c2c/woocommerce vendor/bin/phpunit`

### Using XDEBUG

Start environment with XDEBUG enabled: `npx wp-env start --xdebug`.  Configure your IDE to listen for XDEBUG connections on port 9003. When you browse in the browser, XDEBUG should connect to your IDE.

To get tests working with XDEBUG, it requires a little more work.  Configure your IDE server name to be something like `XDEBUG_OMATTIC` and then launch the tests by running `npx wp-env run tests-cli --env-cwd=wp-content/plugins/sift-decisions bash`. At the new prompt you need to run: `PHP_IDE_CONFIG=serverName=XDEBUG_OMATTIC vendor/bin/phpunit`.

### Troubleshooting:

#### `Error response from daemon: error while creating mount source path`

Restart docker.
