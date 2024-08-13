# WP Bulk Task

[![Readme Standard Spec Badge](https://img.shields.io/badge/readme%20style-standard-brightgreen.svg?style=flat-square)](https://github.com/RichardLitt/standard-readme)

A library to assist with running performant bulk tasks against WordPress objects.

## Background

This package provides a library to make it easier to run bulk tasks against a
WordPress database in a performant way. It includes functionality to search
through a WordPress database for posts, terms, and users, and using WP_Query-style arguments and keeps
a cursor of its location within the database in case it is interrupted and needs
to start again.

## Releases

This package is released via Packagist for installation via Composer. It follows
semantic versioning conventions.

### Install

Requires Composer and PHP >= `8.1`.

### Use

Install this package via Composer:

```sh
composer require alleyinteractive/wp-bulk-task
```

Ensure that the Composer autoloader is loaded into your project:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

Then use the class in your custom CLI command:

```php
class My_Custom_CLI_Command extends WP_CLI_Command {
	use Bulk_Task_Side_Effects;

	/**
	 * Replace all instances of 'apple' with 'banana' in post content.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : If present, no updates will be made.
	 *
	 * [--rewind]
	 * : Resets the cursor so the next time the command is run it will start from the beginning.
	 *
	 * ## EXAMPLES
	 *
	 *     # Bananaify links.
	 *     $ wp my-custom-cli-command bananaify
	 */
	public function bananaify( $args, $assoc_args ) {
		$bulk_task = new \Alley\WP_Bulk_Task\Bulk_Task(
			'bananaify',
			new \Alley\WP_Bulk_Task\Progress\PHP_CLI_Progress_Bar(
				__( 'Bulk Task: remove_broken_links', 'my-textdomain' )
			)
		);

		// Handle rewind requests.
		if ( ! empty( $assoc_args['rewind'] ) ) {
			$bulk_task->cursor->reset();
			WP_CLI::log( __( 'Rewound the cursor. Run again without the --rewind flag to process posts.', 'my-textdomain' ) );
			return;
		}

		$this->pause_side_effects();

		// Set up and run the bulk task.
		$dry_run = ! empty( $assoc_args['dry-run'] );
		$bulk_task->run(
			[
				'post_status' => 'publish',
				'post_type'   => 'post',
				'tax_query'   => [
					[
						'field'    => 'slug',
						'taxonomy' => 'category',
						'terms'    => 'fruit',
					],
				],
			],
			function( $post ) use ( $dry_run ) {
				if ( false !== strpos( $post->post_content, 'apple' ) ) {
					$new_value = str_replace( 'apple', 'banana', $post->post_content );
					if ( $dry_run ) {
						WP_CLI::log( 'Old post_content: ' . $post->post_content );
						WP_CLI::log( 'New post_content: ' . $new_value );
					} else {
						$post->post_content = $new_value;
						wp_update_post( $post );
					}
				}
			}
		);

		$this->resume_side_effects();
	}
}
```

For more information on usage,
[visit the wiki](https://github.com/alleyinteractive/wp-bulk-task/wiki).


### From Source

To work on this project locally, first add the repository to your project's
`composer.json`:

```json
{
	"repositories": [
		{
			"type": "path",
			"url": "../path/to/wp-bulk-task",
			"options": {
				"symlink": true
			}
		}
	]
}
```

Next, add the local development files to the `require` section of
`composer.json`:

```json
{
	"require": {
		"alleyinteractive/wp-bulk-task": "@dev"
	}
}
```

Finally, update composer to use the local copy of the package:

```sh
composer update alleyinteractive/wp-bulk-task --prefer-source
```

### Changelog

This project keeps a [changelog](CHANGELOG.md).

## Development Process

See instructions above on installing from source. Pull requests are welcome from
the community and will be considered for inclusion. Releases follow semantic
versioning and are shipped on an as-needed basis.

### Contributing

See [our contributor guidelines](CONTRIBUTING.md) for instructions on how to
contribute to this open source project.

## Project Structure

This is a Composer package that is published to
[Packagist](https://packagist.org/).

Classes must be autoloadable using
`alleyinteractive/composer-wordpress-autoloader` and live in the `src`
directory, following standard WordPress naming conventions for classes.

## Related Efforts

- [WP_CLI](https://github.com/wp-cli/wp-cli)

## Maintainers

- [Alley](https://github.com/alleyinteractive)

![Alley logo](https://avatars.githubusercontent.com/u/1733454?s=200&v=4)

### Contributors

Thanks to all of the [contributors](CONTRIBUTORS.md) to this project.

## License

This project is licensed under the
[GNU Public License (GPL) version 2](LICENSE) or later.
