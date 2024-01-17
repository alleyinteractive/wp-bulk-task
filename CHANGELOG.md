# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.1]

- Upgrade to WPCS/VIPCS 3.0 (See https://github.com/alleyinteractive/alley-coding-standards)
- Disable common Elasticsearch integrations (ElasticPress or VIP Search) by default.
- A new trait, `Bulk_Task_Side_Effects`, to optionally disable common integrations.

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
