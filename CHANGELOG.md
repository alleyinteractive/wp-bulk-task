# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.4] Unpublished

### Added

- [#27](https://github.com/alleyinteractive/wp-bulk-task/issues/27): feat: Pass the current query object to the callback.

## [0.2.3] - 2024-04-08

### Added

- Support for CSV files in bulk tasks.
- Support for user queries in bulk tasks.
- Support for PHPStan.

## [0.2.2] - 2024-03-12

### Changed

- Fixed incorrect usage of `@global`.
- Improve typecasting of the `filter__terms_where` and `filter__posts_where` methods.
- Support for `declare( strict_types=1 );`, to return any PHP errors.

### Added

- Adds compatibility with Edit Flow plugin; prevents notifications during bulk tasks.
- Stop `Cursor` option from autoloading its option.

## [0.2.1] - 2024-01-18

### Changed

- Upgrade to WPCS/VIPCS 3.0 (See https://github.com/alleyinteractive/alley-coding-standards)

### Added

- A new trait, `Bulk_Task_Side_Effects`, to optionally disable common integrations.
- Support for term queries.
- Disable common Elasticsearch integrations (ElasticPress or VIP Search) by default.

## [0.2.0] - 2023-12-13

- Add `Null_Progress_Bar`.
- Fix logic to reset runtime cache when using Object Cache Pro.

## [0.1.2] - 2022-12-23

- Remove `posts_where` filter after task is run.

## [0.1.1] - 2022-11-16

- Fix package type, use Composer default of "library" instead of "project"

## [0.1.0] - 2022-11-16

- Initial creation of the package
- Add a class to handle running bulk tasks
- Add a class to handle keeping a cursor
