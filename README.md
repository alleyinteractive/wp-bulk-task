# WP-CLI Bulk Task

[![Readme Standard Spec Badge](https://img.shields.io/badge/readme%20style-standard-brightgreen.svg?style=flat-square)](https://github.com/RichardLitt/standard-readme)

A library to assist with running performant bulk tasks against posts via WP-CLI.

## Background

This package provides a library to make it easier to run bulk tasks against a
WordPress database using WP-CLI in a performant way. It includes functionality
to search through a WordPress database for posts that match provided search
criteria and keeps a cursor of its location within the database in case it is
interrupted and needs to start again.


## Releases

This package is released via Packagist for installation via Composer. It follows
semantic versioning conventions.


### Install

Requires Composer and PHP >= 8.0.


### Use

Install this package via Composer:

```sh
composer require alleyinteractive/wp-cli-bulk-task
```

Ensure that the Composer autoloader is loaded into your project:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

Then include the trait and use it in your custom CLI command:

```php
class My_Custom_CLI_Command extends WP_CLI_Command {
	use Alley_Interactive\WP_CLI_Bulk_Task\Bulk_Task;

	/**
	 * A more performant search-replace for post content and post excerpts.
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
	 *     wp my-custom-cli-command search-replace
	 *
	 * @subcommand search-replace
	 */
	public function search_replace( $args, $assoc_args ) {
		list( $search, $replace ) = $args;
		$dry_run                  = ! empty( $assoc_args['dry-run'] );
		$query_builder            = $this->get_bulk_task_query_builder();
		$this->bulk_task(
			$query_builder->,
			function( $post_id ) use ( $dry_run, $replace, $search ) {
				$post = get_post( $post_id );
				foreach ( [ 'post_content', 'post_excerpt' ] as $property ) {
					if ( false !== strpos( $post->$property, $search ) ) {
						$new_value = str_replace( $search, $replace, $post->$property );
						if ( $dry_run ) {
							WP_CLI::log( 'Old ' . $property . ': ' . $post->$property );
							WP_CLI::log( 'New ' . $property . ': ' . $new_property );
						} else {
							$post->$property = $new_property;
						}
					}
				}
				if ( ! $dry_run ) {
					wp_update_post( $post );
				}
			},
			$assoc_args
		)
	}
}
```


### From Source

TODO: Continue filling out README from here.

This section describes how a developer should install or use this project from this repository. For compiled/built projects, use of a [makefile](https://www.gnu.org/software/make/manual/make.html) is encouraged, but not required. If you don't provide orchestration via a makefile or build script, include the code required to start the project from scratch in this section, eg:

```sh
$ git clone my-package
$ ...
```


### Changelog

This section should link to a `CHANGELOG.md` indicating the major version progress and changes.

## Development Process

Here, supply an overview of the development cycles, processes, or patterns this project subscribes to. If this is an Alley-only project for now, or if pull requests are welcome, generalize that here and leave the specifics to the contributing section below.

### Contributing

If this project is open source, link to the `CONTRIBUTING.md` here and outline whether issues, pull requests, etc. are welcome and how to go about it, eg:

> Feel free to dive in! [Open an issue](https://github.com/RichardLitt/standard-readme/issues/new) or submit PRs.
> Standard Readme follows the [Contributor Covenant](http://contributor-covenant.org/version/1/3/0/) Code of Conduct.


## Project Structure

Here, provide an overview of the project structure and link to internal READMEs or wikis the team has setup that provide more detailed or nuanced project architecture.

If the project is intricate, consider including graphics. If you assume structural conventions, state them here, eg:

- We store code we don't want to deploy in `/no-deploy`
- We manage all third-party plugins via composer dependencies


## Third-Party Dependencies

Here, provide and link third-party dependencies outside those covered by a simple [install](#install) above. These might include links to a third-party SSO provider, a required license, or specific packages or libraries. For every dependency, include an overview of the purpose and instructions to manage or learn more about the integration. This is fine to summarize or link internally (eg. to a GitHub [wiki](https://docs.github.com/en/communities/documenting-your-project-with-wikis/about-wikis)), but be certain to include whether these are optional or required.


## Related Efforts

If your project requires, depends, extends, or competes with alternate projects worth noting, link them here, eg:

- Built with 💌  via the [mantle](https://mantle.alley.com/) framework


## Maintainers

For open source projects (public repositories), include GitHub handles of individuals or teams, or include a generic link to the [Alley Interactive](https://github.com/alleyinteractive) organization with a hashtag `#campteam`, eg:

- [Alley Interactive](https://github.com/alleyinteractive) #mops

It is also acceptable to break out responsibilities to individual GitHub handles or organizations if appropriate, eg:

- Deployment [@benpbolton](https://github.com/benpbolton)

Unless otherwise directed, include an Alley branded logo block for internal and external marketing.

![Alley logo](https://avatars.githubusercontent.com/u/1733454?s=200&v=4)

### Contributors

This optional section thanks all the people who contribute, perhaps by linking to the GitHub contributors page, perhaps by direct mention.


## License

If licensed or copyrighted, indicate that here with a link to the license or copyright.
