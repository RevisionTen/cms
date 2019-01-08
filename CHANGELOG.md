# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.12] - 2019-01-08
### Added
- Added solarium/solarium dependency
- Added solr configuration and index functionality
- Added `solr_port` & `solr_collection` configuration
- Added `cms:solr:index` command that indexes pages
- Added `solr_serializer` option to `page_templates` configuration
- Added serializer interface `RevisionTen\CMS\Interface\SolrSerializerInterface`
- Added IndexService and SearchService
- Added example solr collection `conf` folder (solr 6 compatible) in Resources/solr_conf_example
- Added fulltext search to controller element

## [1.4.11] - 2019-01-08
### Added
- Added `multiple` and `expanded` option to DoctrineType
- Added `alias_prefix` option to `page_templates` configuration, see `cms/Resources/config/cms.yaml` on how to use
### Changed
- Fixed file extension guessing in UploadType
- Added event.preventDefault() to editor control buttons

## [1.4.10] - 2019-01-07
### Changed
- Improved form_theme
- Added current website filter option to doctrineType

## [1.4.9] - 2018-12-21
### Changed
- Improved admin menu sub-items appearance
- Improved form_theme

## [1.4.8] - 2018-12-19
### Changed
- Create default website when installing roles if no website exists
- Added cms.admin_menu configuration which lets you add easy_admin menu items to the predefined sections of the menu
- Changed default action link appearance

## [1.4.7] - 2018-12-17
### Added
- Fixed some image and checkbox form styling problems

## [1.4.6] - 2018-12-17
### Added
- Added a configurable CKEditorType
- Added search permission (update your roles accordingly)

## [1.4.5] - 2018-12-14
### Added
- Added documentation for permissions
### Changed
- The configurations for page elements are now aggegrated instead of overriden, which means you don't need to copy the whole page_elements configuration from the config reference anymore to add new elements or change them.

## [1.4.4] - 2018-12-14
### Added
- Added page publish/unpublish/submit-changes permission
### Changed
- Fixed permission check in templates for generic entities

## [1.4.3] - 2018-12-14
### Changed
- Fixed btn color bug in editor
- Fixed unsaved-changes-box appearance

## [1.4.2] - 2018-12-14
### Changed
- Fixed bug in current website listener
- Made checkbox labels render as raw again

## [1.4.1] - 2018-12-14
### Changed
- Fixed bug in install roles command

## [1.4.0] - 2018-12-14
### Added
- Added menu read model
- Added file read model
- Added device preview
- Added user editing
- Added roles and permissions
### Changed
- Upgraded backend to EasyAdmin 2
- **IMPORTANT:** Update your database schema and run the following commands:
  - **`bin/console cms:menu:migrate`** To migrate your menus
  - **`bin/console cms:file:migrate`** To migrate your files
  - **`bin/console cms:install:roles`** To add default roles
  - **`bin/console assets:install --symlink`** To update the cms assets
- **IMPORTANT:** Assign a website to all alias entities in your database, otherwise they wont be listed in the backend
- **IMPORTANT:** Please pass the page language and website to the renderMenu call in your page template. `{{ render(controller('RevisionTen\\CMS\\Controller\\MenuController::renderMenu', {name: 'Main Menu', alias: alias, language: page.language, website: page.website})) }}`
- Improved youtube id parsing in youtube element
- Sorted and renamed admin templates
- Added "cms_*" block prefix to element form types
- Added language and website fields to menus
- Use modal for add-element dialog instead of dropdown

## [1.3.10] - 2018-11-22
### Added
- Added config option "menus"
- Added config info for `config:dump-reference`
### Changed
- Fixed spelling of "menus"
### Removed
- Deprecated config option "page_menues", use "menus" instead

## [1.3.9] - 2018-11-22
### Added
- Added image gallery element and basic ImageType
### Changed
- Made CollectionType sortable

## [1.3.8] - 2018-11-21
### Added
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
