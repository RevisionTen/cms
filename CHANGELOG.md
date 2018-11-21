# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.8] - 2018-11-21
### Changed
- Added configuration option for login code expiration

## [1.3.7] - 2018-11-21
### Changed
- Updated dependencies and readme

## [1.3.6] - 2018-11-20
### Added
- Added logo

## [1.3.5] - 2018-11-14
### Changed
- Removed shm_key config option

## [1.3.4] - 2018-11-14
### Added
- Added menu aggregate subscriber
### Changed
- Updated example base template
- Code cleanup and bug fixes

## [1.3.3] - 2018-11-13
### Changed
- Fixed security forms

## [1.3.2] - 2018-11-12
### Changed
- Updated readme
- Fixed some problems introduced in 1.3.1

## [1.3.1] - 2018-11-12
### Added
- Added google site verification meta-tag
- Added website object to page templates (`{{ dump(website) }}`).

## [1.3.0] - 2018-11-09
### Added
- Added UserAggregate
- **Added command to migrate old users to new user aggregates**, run `bin/console cms:user:migrate`
- Added user password change console command
- Added user login command/event
- Added User password reset command
### Changed
- Renamed class User to UserRead
- Code cleanup

## [1.2.8] - 2018-11-06
### Changed
- Fixed sitemap.xml (absolute urls)

## [1.2.7] - 2018-11-02
### Added
- Added bundle version constant

## [1.2.6] - 2018-10-30
### Changed
- Added H2, H3 elements to trix build

## [1.2.5] - 2018-10-29
### Changed
- Fixed deprecations
- Code cleanup

## [1.2.4] - 2018-10-27
### Added
- Added editor contrast adjust button
### Changed
- Changed editor styling
- Grouped editor top bar buttons

## [1.2.3] - 2018-10-26
### Changed
- Changed preview icon

## [1.2.2] - 2018-10-26
### Changed
- Resizable columns

## [1.2.1] - 2018-10-26
### Changed
- Made modal resizable
### Added
- Add new column in editor after resizing the new column button
- Added jquery-ui to frontend editor, update **@cms/Admin/admin-scripts.html.twig** and **@cms/Admin/admin-styles.html.twig** If you have overwritten them.

## [1.2.0] - 2018-10-25
### Added
- Added a simple managed file overview and upload
- Added youtube element with opt-in
### Changed
- Better editor

## [1.1.15] - 2018-10-25
### Changed
- Bugfix missing EntityManager in ApiController

## [1.1.14] - 2018-10-23
### Changed
- Improved frontend editor
- Improved page preview

## [1.1.13] - 2018-10-18
### Added
- Bugfixes
- Added page order save command

## [1.1.12] - 2018-10-17
### Changed
- Fixed defaultLanguage = null bug in website entity

## [1.1.11] - 2018-10-16
### Changed
- Changed backend icons

## [1.1.10] - 2018-10-16
### Changed
- Fixed symfony icon size

## [1.1.9] - 2018-10-16
### Changed
- Use svg/js fontawesome version in frontend editor
- Added tree sorting back in

## [1.1.8] - 2018-10-16
### Changed
- Removed tree sorting temporarily because of performance issues

## [1.1.7] - 2018-10-16
### Added
- Added the ability to sort items in the tree viewer
- Fixed error in page delete function when qeued changes exist

## [1.1.6] - 2018-10-15
### Added
- Added Page Tree Viewer
- Replaced local fontawesome version with CDN version

## [1.1.5] - 2018-10-12
### Changed
- Improved backend css

## [1.1.4] - 2018-10-12
### Changed
- Send optional email with login data upon registration
- Do not send qrcode when "use_mail_codes" is true

## [1.1.3] - 2018-10-11
### Added
- Added "use_mail_codes" option to config, when set to true users will receive login codes via mail
- Added empty-element-notice to empty elements in the editor

## [1.1.2] - 2018-10-10
### Changed
- Added target="_blank" to button-link in file template

## [1.1.1] - 2018-10-10
### Changed
- Fixed a bug where null-locale was added in alias->getHost()

## [1.1.0] - 2018-10-09
### Added
- Added multisite and language support
- Added website and language fields to aliases **DATABASE UPDATE REQUIRED**
- Added create-alias dialog to page publish workflow
### Changed
- Changed backend styling, run **bin/console assets:install --symlink** 

## [1.0.9] - 2018-10-05
### Added
- Improved collaboration on pages by allowing users to see and commit the qeued changes of other users

## [1.0.8] - 2018-10-05
### Added
- Added trix wysiwyg editor form type (RevisionTen\CMS\Form\Types\TrixType)

## [1.0.7] - 2018-10-04
### Changed
- Added more info about events to the dashboard
- Removed dropshadow in backend
- Changed user info avatar size
- Code cleanup

## [1.0.6] - 2018-09-24
### Changed
- Docs formatting

## [1.0.5] - 2018-09-24
### Changed
- Code cleanup
### Added
- Added caching infos to docs

## [1.0.4] - 2018-09-21
### Added
- Added `shm_key` config parameter to cms config. This key must be an integer and must differ between sites on the same virtual host.

## [1.0.3] - 2018-09-21
### Added
- Added cache service

## [1.0.2] - 2018-09-21
### Changed
- Added asset version number to backend css
- Added `h-auto` bootstrap class to card image to fix aspect ration in internet explorer

## [1.0.1] - 2018-09-21
### Changed
- Fixed hidden dropdown in menu editor (css)

## [1.0.0] - 2018-09-20
### Added
- Changelog
- First major release
