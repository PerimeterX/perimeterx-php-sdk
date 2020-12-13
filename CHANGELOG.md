# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [3.5.3] - 2020-12-13

### Fixed

-   rename of `PerimeterxOriginalTokenValidator`

## [3.5.2] - 2020-04-01

### Fixed

-   Added check for false value for `openssl_decrypt`

## [3.5.1] - 2020-03-19

### Fixed

-   Validation for cookie iterations count.

## [3.5.0] - 2020-02-02

### Added

-   Support for `defer_activities`.

## [3.4.0] - 2020-01-15

### Added

-   support for `activities_timeout` and `activities_connect_timeout`.

## [3.3.1] - 2020-01-09

### Fixed

-   Emprty $port check before using strpos

## [3.3.0] - 2019-12-15

### Added

-   Support for setting an handler for Guzzle.
-   Support for custom block url.

### Fixed

-   http_method not sent on async activities (page_requested/block).
-   refactor to `pxReset` method.

## [3.2.1] - 2019-09-15

### Fixed

-   Better handling of x-px-original-token validation

## [3.2.0] - 2019-08-13

### Fixed

-   Better handling for getting px/px3 cookies
-   Refactor to enrich custom parameters function

## [3.1.0] - 2019-03-09

### Added

-   Support for testing blocking flow in monitor mode

### Fixed

-   Error handling for HTTP client calls

## [3.0.3] - 2019-01-16

### Fixed

-   PXHD - set cookie without encoding

## [3.0.2] - 2019-01-13

### Fixed

-   PXHD related fix

## [3.0.1] - 2019-01-06

### Fixed

-   PXHD cookie path

## [3.0.0] - 2018-12-25

### Added

-   First-Party fallback for block templates
-   Support for PXHD cookies
-   Enrich Custom Parameters support for async activities

## [2.10.0] - 2018-06-28

### Added

-   Added data enrichment support
-   Removed mcrypt dependecy

## [2.9.0] - 2018-06-11

### Added

-   Handle original token for mobile
-   Simulated_block property on Risk API call
-   Ratelimit support
-   Enrich Custom Parameters support
-   Captcha v2 support

### Fixed

-   Replaced mcrypt with openssl

## [2.8.0] - 2017-12-04

### Changed

-   Enhanced module logs

### Fixed

-   Headers extraction fix
-   Fixed debug_mode flag in relation to log output

## [2.7.0] - 2017-11-05

### Added

-   Support funCaptcha
-   Support new captcha flow
-   Support mobile sdk pinning error

### Fixed

-   Mobile sdks flow

### Changed

-   Default block socre is set to 100 instead of 70
-   Default module_mode is set to $MONITOR_MODE
   In order to get the module to blocking mode, set `module_mode => Perimeterx::$ACTIVE_MODE`
    Examples can be found on README.md or in the examples directory

## [2.6.2] - 2017-06-04

### Added

-   New s2s_call_reason for mobile sdk connection error

### Fixed

-   Fixed collectorUrl for mobile sdk response

## [2.6.1] - 2017-06-04

### Changed

-   Removed perimeterx snippet from mobile app block pages

### Fixed

-   Added missing host-url parameter

## [2.6.0] - 2017-06-04

### Added

-   Sending real uuid in page_requested
-   Support for Mobile SDK

### Changed

-   Real IP headers are now a list instead of single value (single
-   Sending page activities by default

### Fixed

-   On blocked, status code is 403

## [2.5.1] - 2017-06-04

### Added

-   sending pass_reason with page requested activities
-   sending risk_rtt on block/page_activity

## [2.5.0] - 2017-04-20

### Added

-   sending cookie's original value when decrypt fails

## [2.4.1] - 2017-04-18

### Added

-   sending cookie's original value when decrypt fails

## [2.4.0] - 2017-03-13

### Added

-   javascript challenge support
-   sending cookie's hamc on page requested for cookie replay detection

### Modified

-   changed collector's server urls
-   redesigned default block/captcha pages + added the ability to inject css, js and logo files to the pages
-   more tests coverage

## [2.3.0] - 2016-11-29

### Added

-   Cookie v3 support (recomended action exposed on context)
-   Moved the risk v2 API

## [2.2.3] - 2016-11-29

### Added

-   Added TravisCI integration and badge

### Modified

-   Fixed tests
-   Changed PHPUnit version in composer.json

## [2.2.2] - 2016-11-29

### Modified

-   Reverted per app id server ip

## [2.2.1] - 2016-11-29

### Modified

-   Minor fixes

## [2.2.0] - 2016-11-22

### Added

-   Support UUID for captcha on Risk API requests
-   Passing Risk cookie on page activities

### Modified

-   Fixed tests
-   Updated Documentation

## [2.1.0] - 2016-11-03

### Added

-   Support for user Reset
-   UTF-8 encode support

### Modified

-   Flow enhancements
-   Updated Documentation

## [2.0.0] - 2016-11-03

### Added

-   PSR-3 logger.
-   Additional activities handler to expose request context.
-   PHPUnit tests

### Modified

-   Fixed dependencies
-   Updated Documentation

##[1.3.15] - 2016-10-20

### Added

-   `Authorization` header to activity api calls

### Modified

-   Updated composer version to 1.3.15

[2.2.3]: https://github.com/PerimeterX/perimeterx-php-sdk/tree/v2.2.3
[2.2.2]: https://github.com/PerimeterX/perimeterx-php-sdk/tree/v2.2.2
[2.2.1]: https://github.com/PerimeterX/perimeterx-php-sdk/tree/v2.2.1
[2.2.0]: https://github.com/PerimeterX/perimeterx-php-sdk/tree/v2.2.0
[2.1.0]: https://github.com/PerimeterX/perimeterx-php-sdk/tree/v2.1.0
[2.0.0]: https://github.com/PerimeterX/perimeterx-php-sdk/releases/tag/v2.0.0
[1.3.15]: https://github.com/PerimeterX/perimeterx-php-sdk/releases/tag/v1.3.15
