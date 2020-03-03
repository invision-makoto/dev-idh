# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2020-03-03
### Added
- Adds a shorthand build for release command (apps:build)
- Ports over the Classmap command from Power Tools (apps:classmap)

### Fixed
- Resolved an issue with the "Rebuild development resources" apps command

### Changed
* The following environment variables have been renamed:
    - IDH_PATH has been renamed to IPS_PATH
    - IDH_BUILDS_PATH has been renamed to IPS_BUILDS_PATH
    - IDH_BACKUPS_PATH has been renamed to IPS_BACKUPS_PATH
- Build / backup paths are now dynamically generated from the IPS_PATH environment variable

## [2.0.0] - 2020-02-17
### Added
- Adds support for command-line IPS installation
- Various dependency updates and PHP 7.4 compatibility fixes

[1.0.0] - 2020-02-09
### Changed
- Updated dependencies and released the first official stable build

## [0.4.0a] - 2019-04-02
### Added
- Command for downloading the latest IPS release from the commandline
- Command for downloading the latest IPS development resources from the commandline

## [0.3.0a] - 2019-03-19
### Added
- A command for regenerating development resources (excluding e-mail templates)

## [0.2.0a] - 2019-03-14
### Added
- Basic Plugins command with support for printing Plugin information and enabling/disabling
- Console command using PsySh with support for assigning custom logged-in members
