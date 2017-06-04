# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [2.5.1] - 2017-06-04
### Added-
- sending pass_reason with page requested activities
- sending risk_rtt on block/page_activity

## [2.5.0] - 2017-04-20
### Added
- sending cookie's original value when decrypt fails

## [2.4.1] - 2017-04-18
### Added
- sending cookie's original value when decrypt fails

## [2.4.0] - 2017-03-13
### Added
- javascript challenge support
- sending cookie's hamc on page requested for cookie replay detection

### Modified
- changed collector's server urls
- redesigned default block/captcha pages + added the ability to inject css, js and logo files to the pages
- more tests coverage

## [2.3.0] - 2016-11-29
### Added
- Cookie v3 support (recomended action exposed on context)
- Moved the risk v2 API

## [2.2.3] - 2016-11-29
### Added
- Added TravisCI integration and badge

### Modified
- Fixed tests
- Changed PHPUnit version in composer.json


## [2.2.2] - 2016-11-29
### Modified
- Reverted per app id server ip

## [2.2.1] - 2016-11-29
### Modified
- Minor fixes


## [2.2.0] - 2016-11-22
### Added
- Support UUID for captcha on Risk API requests
- Passing Risk cookie on page activities

### Modified
- Fixed tests
- Updated Documentation


## [2.1.0] - 2016-11-03
### Added
- Support for user Reset
- UTF-8 encode support

### Modified
- Flow enhancements
- Updated Documentation


## [2.0.0] - 2016-11-03
### Added
- PSR-3 logger.
- Additional activities handler to expose request context.
- PHPUnit tests

### Modified
- Fixed dependencies
- Updated Documentation


##[1.3.15] - 2016-10-20
### Added
- `Authorization` header to activity api calls

### Modified
- Updated composer version to 1.3.15


[2.2.3]: https://github.com/PerimeterX/perimeterx-php-sdk/tree/v2.2.3
[2.2.2]: https://github.com/PerimeterX/perimeterx-php-sdk/tree/v2.2.2
[2.2.1]: https://github.com/PerimeterX/perimeterx-php-sdk/tree/v2.2.1
[2.2.0]: https://github.com/PerimeterX/perimeterx-php-sdk/tree/v2.2.0
[2.1.0]: https://github.com/PerimeterX/perimeterx-php-sdk/tree/v2.1.0
[2.0.0]: https://github.com/PerimeterX/perimeterx-php-sdk/releases/tag/v2.0.0
[1.3.15]: https://github.com/PerimeterX/perimeterx-php-sdk/releases/tag/v1.3.15
