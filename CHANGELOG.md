# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.3.26] - 2021-05-20
### Changed
- CollectionType Javascript bugfix

## [2.3.25] - 2021-05-17
### Changed
- CollectionType Javascript bugfix

## [2.3.24] - 2021-04-26
### Changed
- Added `solr_username` and `solr_password` options to CMS config.

## [2.3.23] - 2021-02-24
### Changed
- Added missing `symfony/mime` dependency

## [2.3.22] - 2021-02-10
### Changed
- Typo

## [2.3.21] - 2021-02-10
### Added
- Added file delete button
- Added `file_delete` permission
- **Update your database schema**

## [2.3.20] - 2021-01-26
### Added
- Added `RevisionTen\CMS\Event\PageRenderEvent` event that is dispatched each time a page is displayed

## [2.3.19] - 2021-01-26
### Added
- Added vimeo video element

## [2.3.18] - 2020-12-10
### Changed
- Read global `gregwar_image_web_dir` parameter. Set this param if you want to overwrite the default `gregwar_image.web_dir` config

## [2.3.17] - 2020-12-03
### Changed
- Fixed Card element

## [2.3.16] - 2020-11-10
### Changed
- CollectionType template and file upload template bugfixes

## [2.3.15] - 2020-10-21
### Changed
- Bugfix

## [2.3.14] - 2020-10-16
### Changed
- Bugfixes

## [2.3.13] - 2020-10-14
### Added
- Added `RevisionTen\CMS\Form\Admin\PageStreamReadType` which can be used instead of a regular `EntityType` to provide a list of page choices. Choices are filtered by website automatically and archived pages are marked.
### Changed
- Added a pagination to the file picker dialog.

## [2.3.12] - 2020-09-25
### Changed
- Bugfix for the `allow_delete` option in conjunction with the `file_with_meta_data` option of the `UploadType`

## [2.3.11] - 2020-09-14
### Changed
- Fixed cloning pages with scheduled tasks
- Added spacing tool button in editor toolbar
- Bugfixes

## [2.3.10] - 2020-08-27
### Changed
- Fixed layer tool save-button

## [2.3.9] - 2020-08-27
### Changed
- Compatibility fix for PHP 7.1

## [2.3.8] - 2020-08-24
### Added
- Added `keepOriginalFileName` option to UploadType
### Changed
- **Changed `File`-element template**
- **Deprecated `Form/Types/ManagedUploadType`**, use `Form/Types/UploadType` instead
- Deprecated `DataTransformer/ImageFileTransformer`, use `DataTransformer/FileWithMetaDataTransformer` instead

## [2.3.7] - 2020-05-29
### Added
- Added sortWeekdays Twig filter (@entepe85)

## [2.3.6] - 2020-05-27
### Changed
- Cleaned up backend, removed inline scripts

## [2.3.5] - 2020-05-26
### Changed
- Bugfixes and backend improvements

## [2.3.4] - 2020-05-14
### Changed
- Replaced all occurrences of `CmsBundle` with `CMSBundle`, **do the same in your project!**

## [2.3.3] - 2020-05-14
### Changed
- Bugfixes

## [2.3.2] - 2020-05-14
### Changed
- **Changed extension class case** from `CmsExtension` to `CMSExtension`

## [2.3.1] - 2020-05-14
### Changed
- **Changed bundle class case** from `CmsBundle` to `CMSBundle`

## [2.3.0] - 2020-05-14
### Changed
- Updated twig dependency because gregwar/image-bundle is soon compatible with twig 3

## [2.2.18] - 2020-05-05
## Changed
- Bugfixes

## [2.2.17] - 2020-04-23
## Changed
- Bugfix

## [2.2.16] - 2020-04-22
## Changed
- Changed `dark-editor-theme` CSS class to `editor-dark`

## [2.2.15] - 2020-04-22
## Added
- Added distraction free edit mode

## Changed
- Bugfix

## [2.2.14] - 2020-04-21
## Changed
- Bugfix

## [2.2.13] - 2020-04-21
## Changed
- Improved editor grid styling

## [2.2.12] - 2020-04-20
## Changed
- Updated documentation

## [2.2.11] - 2020-04-16
## Added
- Added new column size options `col[-breakpoint]` and `col[-breakpoint]-auto`
## Changed
- Bugfixes
- **Updated column element template** (removed column width css-classes)

## [2.2.10] - 2020-04-09
## Changed
- Added padding tool light style. Dark style can be enable by adding the class `dark-editor-theme` to the page body when editing.

## [2.2.9] - 2020-04-09
## Changed
- Added padding tool to preview mode

## [2.2.8] - 2020-04-08
## Changed
- Security updates

## [2.2.7] - 2020-03-18
## Changed
- Bugfix

## [2.2.6] - 2020-03-10
## Changed
- Security updates

## [2.2.5] - 2020-03-09
## Changed
- Bugfix

## [2.2.4] - 2020-02-28
## Changed
- Bugfixes
- Added `admin_template` option to `menu_items` entry config (default template is `@cms/Admin/Menu/edit-item-title.html.twig`)

## [2.2.3] - 2020-02-19
## Changed
- Bugfixes

## [2.2.2] - 2020-02-18
## Changed
- Fixed EasyAdmin entity form templates and updated backend javascript
- **Update your assets**

## [2.2.1] - 2020-02-11
## Changed
- Fixed default template, typos and other bugs

## [2.2.0] - 2020-01-30
## Changed
- Update to Symfony 4.4 and EasyAdmin 2.3.5

## [2.1.10] - 2020-01-10
## Changed
- Bugfixes

## [2.1.9] - 2020-01-09
## Changed
- Added missing translation
- Bugfixes

## [2.1.8] - 2020-01-06
## Changed
- Bugfix (Execute script tags in dynamically inserted tab/modal content in backend)

## [2.1.7] - 2019-12-20
## Changed
- Improved backend design
## Removed
- Removed `RevisionTen\CMS\Form\Menu\ItemSettings`

## [2.1.6] - 2019-12-18
## Changed
- Improved dashboard and backend design

## [2.1.5] - 2019-12-17
## Added
- Added `websites` option to `page_templates`

## [2.1.4] - 2019-12-17
## Changed
- Updated docs
## Removed
- Removed no longer needed `messages.en.yaml`

## [2.1.3] - 2019-12-17
## Changed
- Updated translations
- **Use `bin/console debug:translation de --only-missing` to find missing german translations**

## [2.1.2] - 2019-12-11
## Changed
- Bugfix

## [2.1.1] - 2019-12-11
## Added
- Added permissions option to `page_templates` config
## Changed
- Updated `README.md` with instructions on how to configure template permissions

## [2.1.0] - 2019-12-10
## Added
- Added `ElementController`
## Removed
- Removed `bundles/cms/js/file-picker.js` (file picker is now part of `admin-backend.js`)
- **Deprecated jQuery `refreshElement` and `bindElement` events**, use the native CustomEvent javascript events instead. See readme for an example.
## Changed
- Rewrote `admin-backend.js` and `admin-frontend.js` in TypeScript
- Moved code from `PageController` to `ElementController`

## [2.0.20] - 2019-12-04
## Changed
- Don't include pages in sitemap that have a `noindex` in `page.meta.robots`

## [2.0.19] - 2019-11-12
## Changed
- Made `CacheService::getVersion($uuid)` method public
- Made `FrontendController::renderPage($pageUuid, $alias)` method public

## [2.0.18] - 2019-11-11
## Changed
- Fixed help html in element form types

## [2.0.17] - 2019-10-23
## Changed
- Updated file picker to also use image meta data if enabled
- **Update your assets**

## [2.0.16] - 2019-10-23
## Changed
- Save image dimensions when uploading image, set the `file_with_meta_data` option in your UploadType field to true to return the file with meta data

## [2.0.15] - 2019-10-18
## Changed
- Bugfix

## [2.0.14] - 2019-10-18
## Changed
- Enabled file picker for UploadType by default, you can still disable it by setting `show_file_picker` to `false`

## [2.0.13] - 2019-10-17
## Changed
- Bugfix for indexing pages without a description

## [2.0.12] - 2019-10-14
## Changed
- Bugfix for menu child items

## [2.0.11] - 2019-10-09
## Changed
- Changed image picker sorting

## [2.0.10] - 2019-09-24
## Added
- Added `getPageData`, `getAliases` and `getFirstAlias` methods to PageService

## [2.0.9] - 2019-09-23
## Added
- Conditional forms Javascript improvements

## [2.0.8] - 2019-09-20
## Added
- Added a twig extension to render menus, use the `cms_menu()` function in your template instead of rendering `MenuController::renderMenu`. [See a before/after example here](https://github.com/RevisionTen/cms/blob/master/Resources/docs/menu-update.md)

## [2.0.7] - 2019-09-19
## Changed
- Bugfixes

## [2.0.6] - 2019-09-19
## Added
- Added `disable_cache_workaround` config option. Set it to `true` If your hosters APCu cache behaves propperly and shares the cache as one would expect.

## [2.0.5] - 2019-09-12
## Added
- Added `solr_host` config option to cms

## [2.0.4] - 2019-09-09
## Changed
- Filepicker bugfix **Update your assets**

## [2.0.3] - 2019-09-05
## Changed
- Added support for `data-condition="1"` to buttons to trigger conditional form reload

## [2.0.2] - 2019-08-29
### Added
- Added `page_change_seo_settings` permission. Users with this permission can edit the robots metatag settings. **Add it to your roles**
## Changed
- Coding style, added return types to form types
- Allow relative urls in trix editor link dialog

## [2.0.1] - 2019-08-26
### Changed
- Bugfixes

## [2.0.0] - 2019-08-22
### Removed
- **Removed `RevisionTen\CMS\SymfonyEvent\PagePublishedEvent`**, use `RevisionTen\CMS\Event\PagePublishEvent` instead
- **Removed `RevisionTen\CMS\SymfonyEvent\PageUnpublishedEvent`**, use `RevisionTen\CMS\Event\PageUnpublishEvent` instead
- **Removed `RevisionTen\CMS\SymfonyEvent\PageDeletedEvent`**, use `RevisionTen\CMS\Event\PageDeleteEvent` instead
### Changed
- Upgraded CQRS classes **Update your classes if they implement the CQRS interfaces**
- **Update your database schema**
- **Update your assets**

## [1.7.12] - 2019-08-22
### Changed
- Improved language prefix usage for menu items

## [1.7.11] - 2019-08-22
### Changed
- Added language prefix to menu item paths if their page language differs from the defaultLanguage

## [1.7.10] - 2019-08-07
### Changed
- Code cleanup and bugfixes

## [1.7.9] - 2019-08-07
### Added
- Added PageDeletedEvent
### Changed
- Open page settings in tab instead of modal
- Updated cqrs classes
- Mark associated tasks as deleted when deleting pages
- **Update your database schema**
- **Update your assets**

## [1.7.8] - 2019-08-02
### Changed
- Added page history to page inspect modal

## [1.7.7] - 2019-07-31
### Changed
- Bugfixes

## [1.7.6] - 2019-07-31
### Changed
- Improved page settings form templates

## [1.7.5] - 2019-07-31
### Changed
- DoctrineType improved
- Form validation bugfixes

## [1.7.4] - 2019-07-26
### Changed
- Trigger "CustomEvent" editor events in addition to the jQuery events

## [1.7.3] - 2019-07-18
### Changed
- Backend styling improvements

## [1.7.2] - 2019-07-17
### Added
- Added `bin/console cms:solr:clear` command that empties the entire solr collection

## [1.7.1] - 2019-07-16
### Changed
- Changed DummyOutput in AliasSubscriber to BufferedOutput

## [1.7.0] - 2019-07-16
### Added
- Added TemplateFilterType
- Added LanguageFilterType
### Changed
- Updated solarium/solarium from ^4.0 to ^5.0
- **update your serializers!** (null can't be passed to filterControlCharacters for example)
- **update your solarium config array** if you have implemented your own index or search service
- Updated easycorp/easyadmin-bundle to 2.2.2

## [1.6.9] - 2019-07-08
### Changed
- Do not deactivate aliases that have a fallback redirect

## [1.6.8] - 2019-07-08
### Changed
- Added status field to aliases
- **Update your database schema and run `bin/console cms:alias:update` afterwards**

## [1.6.7] - 2019-06-19
### Added
- Added public static method `getFulltextFilterQuery` to SearchService
### Changed
- Serializers can now be services

## [1.6.6] - 2019-06-14
### Added
- Bugfixes
- Added aggregate inspector to page editor toolbar
- Added `page_inspect` permission

## [1.6.5] - 2019-06-14
### Changed
- Two-factor authentication is no longer required for dev environment

## [1.6.4] - 2019-06-11
### Changed
- Improved page scheduling handlers

## [1.6.3] - 2019-06-05
### Changed
- Bugfixes & code cleanup

## [1.6.2] - 2019-06-04
### Changed
- Revert password encoding algorithm to bcrypt until [#31763](https://github.com/symfony/symfony/pull/31763) is released

## [1.6.1] - 2019-06-04
### Added
- Added task entity permissions
- Added tasks overview table
### Changed
- Bugfixes
- Added state field to PageStreamRead entity
- **Update your database schema**

## [1.6.0] - 2019-06-03
### Added
- Added Task entity
- **Update your database schema**
- Added TaskService
- Added PageAddScheduleCommand and PageRemoveScheduleCommand
- Added `cms:tasks:run` console command
- Added `page_schedule` permission
### Changed
- Increased requirements to symfony 4.3
- Updated EasyAdmin dependency
- Fixed deprecations
- Use RepeatType in password reset form
- Test If password in password reset form is compromised

## [1.5.31] - 2019-05-29
### Added
- Added new `form_submissions_delete` permission
- Added form submission entity list
### Changed
- Updated forms dependency
- **Update your database schema**
- Added maximize button to default CKEditor configuration
- Improved menu edit admin template

## [1.5.30] - 2019-05-17
### Changed
- Fixed website entity editing bug

## [1.5.29] - 2019-05-15
### Changed
- Added `website` and `alias` objects to element templates. **Update your page templates**. [See a before/after example here](https://github.com/RevisionTen/cms/blob/master/Resources/docs/section-helper-update.md).

## [1.5.28] - 2019-05-10
### Changed
- SEO and accessability improvements

## [1.5.27] - 2019-05-10
### Changed
- Improved Alias creation

## [1.5.26] - 2019-05-07
### Changed
- Fixed CKEditor configuration overwrite bug

## [1.5.25] - 2019-05-06
### Changed
- Bugfixes

## [1.5.24] - 2019-05-06
### Changed
- Renamed `website` int variable in request to `websiteId`

## [1.5.23] - 2019-04-24
### Changed
- Bugfixes

## [1.5.22] - 2019-04-18
### Changed
- Added optimization of solr index after indexing pages

## [1.5.21] - 2019-04-15
### Added
- Added CKEditorType configuration to cms configuration [[see default config here](https://github.com/RevisionTen/cms/blob/master/Resources/config/cms.yaml) and [full configuration reference here](https://ckeditor.com/docs/ckeditor4/latest/api/CKEDITOR_config.html)]
- **Run bin/console assets:install --symlink**

## [1.5.20] - 2019-04-11
### Changed
- Improved timing element template

## [1.5.19] - 2019-04-11
### Changed
- Added a 6er spacing choice to SpacingType **[Extend your Bootstrap spacing classes](https://github.com/RevisionTen/cms#extend-your-bootstrap-spacing-classes)**

## [1.5.18] - 2019-04-11
### Changed
- Added alias to page even when in editing view

## [1.5.15, 1.5.16, 1.5.17] - 2019-04-08
### Changed
- Set utf8_unicode_ci collaction on user string fields to make it MySQL 5.5. compatible
- **Update your database schema**

## [1.5.14] - 2019-04-05
### Added
- Added bootstrap button styles to ckeditor

## [1.5.13] - 2019-04-05
### Added
- Added 404 error page option to website settings **Update your database schema**
### Changed
- **Changed FrontendController method signatures**

## [1.5.12] - 2019-04-05
### Changed
- Keep empty span and i-tags in CKEditor (for fontawesome icons)
- Added fontawesome css to ckeditor content css

## [1.5.11] - 2019-04-04
### Changed
- Added indexing on postUpdate to AliasSubscriber

## [1.5.10] - 2019-04-04
### Changed
- Fixed UploadType file delete checkbox

## [1.5.9] - 2019-03-29
### Changed
- Improved SVG handling in UploadWidget template

## [1.5.8] - 2019-03-28
### Changed
- Remove useless and deprecated use of ContainerAwareCommand in IndexCommand

## [1.5.7] - 2019-03-28
### Changed
- Set utf8_unicode_ci collaction on uuid fields to make it MySQL 5.5. compatible
- Changed `json` database fields to `text`
- **Update your database schema**

## [1.5.6] - 2019-03-26
### Added
- Added AliasSubscriber that indexes the referenced page when the alias is saved

## [1.5.5] - 2019-03-24
### Added
- Added `serializeToSolrArray` method to UserRead entity
### Changed
- **Renamed entity serialization method call in IndexService** from `serialize` to `serializeToSolrArray`

## [1.5.4] - 2019-03-18
### Changed
- Fixed FormController

## [1.5.3] - 2019-03-12
### Added
- Added `constraints` option to UploadType

## [1.5.2] - 2019-03-12
### Changed
- Improved UploadType form template

## [1.5.1] - 2019-03-08
### Changed
- Fixed search template

## [1.5.0] - 2019-03-04
### Changed
- **Changed the SolrSerializerInterface** to have full control over what is indexed. [See a before/after example here](https://github.com/RevisionTen/cms/blob/master/Resources/docs/serializer-update.md).

## [1.4.42] - 2019-03-01
### Changed
- Update the PageRead and search index via the PageSubscriber instead of using listeners

## [1.4.41] - 2019-02-28
### Changed
- Update the PageStreamRead before indexing happens

## [1.4.40] - 2019-02-28
### Changed
- Update the PageStreamRead via the AggregrateSubscriber instead of using listeners

## [1.4.39] - 2019-02-27
### Added
- Added "extra" array field to UserAggregate to save custom data
### Changed
- Update the UserRead via the AggregrateSubscriber instead of using listeners

## [1.4.38] - 2019-02-26
### Changed
- Improved UploadType, files uploaded with this type now also appear in the file overview

## [1.4.37] - 2019-02-25
### Changed
- Enable select2 in conditional-forms when `data-widget="select2"` is set
- Improved SpacingType form template

## [1.4.36] - 2019-02-22
### Added
- Added a form attribute to enable conditional forms in page form, just add a `data-condition` attribute to the form element to trigger a reload on change. This can also be used in element edit modals.

## [1.4.35] - 2019-02-20
### Added
- Added CMS data collector (for the debug toolbar)

## [1.4.34] - 2019-02-20
### Changed
- Changed property visibility in services

## [1.4.33] - 2019-02-18
### Added
- Added PagePublishedEvent (`cms.page.published`) and PageUnpublishedEvent (`cms.page.unpublished`)
- Added PageSubscriber that indexes pages when they are updated

## [1.4.32] - 2019-02-18
### Changed
- Improved managed file form theme
- Fixed page create form data transformation
### Removed
- Removed TimeType

## [1.4.31] - 2019-02-15
### Added
- Added a TimeType that save the time string in a format that corresponds to the form options
- Added `enable_title` and `enable_chooser` options to ManagedUploadType (both default to true)
### Changed
- Execute embedded script tags when loading modal content (for example when a form widget includes a google map)

## [1.4.30] - 2019-02-15
### Changed
- Updated EasyaAdmin dependency
- Improved collection item sort javascript
- Improved OpeningHoursSpecificationType form theme
- Fixed invalid form response in modal

## [1.4.29] - 2019-02-14
### Changed
- Fixed "/"-frontpage redirect

## [1.4.28] - 2019-02-14
### Changed
- Fixed ManagedUploadType

## [1.4.27] - 2019-02-13
### Added
- Added ReadModelTrait
### Changed
- Admin form theme improvements

## [1.4.26] - 2019-02-08
### Changed
- Removed modal fade

## [1.4.25] - 2019-02-05
### Changed
- Fixed FormController, inject original FormController as a service instead of forwarding requests

## [1.4.24] - 2019-02-04
### Changed
- Security forms styling

## [1.4.23] - 2019-02-01
### Changed
- Login form styling

## [1.4.22] - 2019-01-29
### Changed
- Updated to EasyAdmin 2.0.4 and locked version
- Styling fixes for EasyAdmin 2.0.4

## [1.4.21] - 2019-01-29
### Changed
- Backend style fixes

## [1.4.20] - 2019-01-22
### Added
- Added image manipulation options to image element settings. **Update your image template & form-type**, If you have replaced the default template and form-type, to take advantage of this new functionality

## [1.4.19] - 2019-01-21
### Changed
- Improved doctrineType

## [1.4.18] - 2019-01-21
### Added
- Added LanguageAndWebsiteTrait
- Added docs for LanguageAndWebsiteTrait
- Added path constraint to alias entity
### Changed
- Set default values for website and language property in entity new form

## [1.4.17] - 2019-01-17
### Changed
- Fixed an inconsistency with date types in the PageStreamRead entity
- Added option to index only a specific aggregate

## [1.4.16] - 2019-01-16
### Added
- **Added `form_submissions` permission**
- Added form submission saving (**database update required**)
### Changed
- Updated form bundle routes

## [1.4.15] - 2019-01-11
### Changed
- Fixed 0 margin/padding option

## [1.4.14] - 2019-01-11
### Changed
- Fixed collection index bug

## [1.4.13] - 2019-01-10
### Changed
- Search improvements

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
