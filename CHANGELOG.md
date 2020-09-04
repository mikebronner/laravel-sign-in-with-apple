# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [0.4.4] - 2020-09-04
### Changed
- storing of `access_token` to `id_token`.

## [0.4.3] - 2020-03-06
### Udpated
- dependencies.

## [0.4.2] - 2020-02-30
### Fixed
- name resolution from Apple's response. Thanks @poldixd!

## [0.4.1] - 2020-02-29
### Fixed
- service provider class name.

## [0.4.0] - 2020-02-29
### Added
- Laravel 7 compatibility.

## [0.3.0] - 2019-10-14
### Removed
- user resolution and persistence functionality and extracted it to a generic
    package that works with all socialite providers.

## [0.2.1] - 2019-10-13
### Added
- optional user persistence and automatic login functionality.

## [0.2.0] - 2019-10-13
### Changed
- blade directive for button from `@signInWithAppleButton(...)` to `@signInWithApple(...)`.

## [0.1.2] - 2019-10-13
### Fixed
- missing user's name is now being returned along with their email address.

## [0.1.1] - 2019-10-12
### Fixed
- env variable registration.
- config merging.

## [0.1.0] - 2019-10-12
### Added
- initial functionality.
