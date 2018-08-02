# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).

## [1.1.0] - UNRELEASED

### Added

- New option `streamFactory` to configure a custom factory implementing `Psr\Http\Message\StreamFactoryInterface`

### Changed

- Upgrade dev dependencies

## [1.0.0] - 2018-01-26

### Added

- Improved testing and added code coverage reporting
- Added tests for PHP 7.2

### Changed

- Upgraded to the final version of PSR-15 `psr/http-server-middleware`

### Fixed

- Updated license year
- Removed the temporary static variable `ImageManipulation::$currentSignatureKey` after the middleware execution

## [0.5.0] - 2017-11-13

### Changed

- Replaced `http-interop/http-middleware` with  `http-interop/http-server-middleware`.

### Removed

- Removed support for PHP 5.x.

## [0.4.4] - 2017-09-21

### Changed

- Append `.dist` suffix to phpcs.xml and phpunit.xml files
- Changed the configuration of phpcs and php_cs
- Upgraded phpunit to the latest version and improved its config file
- Updated to `http-interop/http-middleware#0.5`

## [0.3.4] - 2017-02-12

### Fixed

- Prevent to create files and directories starting with `.` in the JWT paths.

## [0.3.3] - 2017-02-06

### Changed

- Modified the way to create JWT paths to prevent parts like `/./`, causing the loss of the dots after resolving the path.

## [0.3.2] - 2016-12-31

### Changed

- Ensure a subdirectory is created for each dot in the JWT path, to improve the filesystem cache.

## [0.3.1] - 2016-12-31

### Fixed

- Split the path containing the JWT into more subdirectories to limit the maximum filename length to 200 characters in order to prevent "name too long" errors.

## [0.3.0] - 2016-12-26

### Changed

- Updated tests
- Updated to `http-interop/http-middleware#0.4`
- Updated `friendsofphp/php-cs-fixer#2.0`

## [0.2.0] - 2016-11-22

### Changed

- Updated to `http-interop/http-middleware#0.3`

## [0.1.1] - 2016-10-07

### Fixed

- Split the paths in subdirectories to prevent *filename too long* errors
- Prepend the path prefix to the resolved path if exists. For example `/images/[encoded-info]` is resolved to `/images/resolved-path`.

## 0.1.0 - 2016-10-07

First version


[1.1.0]: https://github.com/middlewares/image-manipulation/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/middlewares/image-manipulation/compare/v0.5.0...v1.0.0
[0.5.0]: https://github.com/middlewares/image-manipulation/compare/v0.4.4...v0.5.0
[0.4.4]: https://github.com/middlewares/image-manipulation/compare/v0.3.4...v0.4.4
[0.3.4]: https://github.com/middlewares/image-manipulation/compare/v0.3.3...v0.3.4
[0.3.3]: https://github.com/middlewares/image-manipulation/compare/v0.3.2...v0.3.3
[0.3.2]: https://github.com/middlewares/image-manipulation/compare/v0.3.1...v0.3.2
[0.3.1]: https://github.com/middlewares/image-manipulation/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/middlewares/image-manipulation/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/middlewares/image-manipulation/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/middlewares/image-manipulation/compare/v0.1.0...v0.1.1
