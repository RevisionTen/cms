
### Installation

Zuerst muss die Snapshot-Datenbank-Tabelle geleert werden. Dies muss auch im Produktivsystem geschehen wenn das Upgrade eingespielt wird.

Die neuen CMS Version erfordert PHP 7.4 und Symfony 5.3, sowie Twig 3.3.
Folgende Abhängigkeiten wurden entfernt:
- easycorp/easyadmin-bundle
- symfony/swiftmailer-bundle

### Geänderte Klassennamen und Methoden

Im Form-Element Template muss `FormsBundle:Form:renderForm` zu `FormsBundle:Form:renderCmsForm` geändert werden.

##### Twig Template Pfade
Alt | Neu
--- | ---
@cms/Admin/admin-styles.html.twig | @CMS/Frontend/Editor/admin-styles.html.twig
@cms/Admin/admin-scripts.html.twig | @CMS/Frontend/Editor/admin-scripts.html.twig
@cms/base.html.twig | @CMS/Frontend/base.html.twig
@cms/layout.html.twig | @CMS/Frontend/Page/simple.html.twig
@cms/sitemap.xml.twig | @CMS/Frontend/sitemap.xml.twig
@cms/Helper/element-config-helper.html.twig | @CMS/Frontend/Helper/element-config-helper.html.twig
@cms/Helper/section-helper.html.twig | @CMS/Frontend/Helper/section-helper.html.twig
@cms/Search/fulltext.html.twig | @CMS/Frontend/Search/fulltext.html.twig
@cms/Menu/footer.html.twig | @CMS/Frontend/Menu/footer.html.twig
@cms/Menu/main.html.twig | @CMS/Frontend/Menu/main.html.twig
@cms/Menu/Items/divider.html.twig | @CMS/Frontend/Menu/Items/divider.html.twig
@cms/Menu/Items/link.html.twig | @CMS/Frontend/Menu/Items/link.html.twig
@cms/Menu/Items/page.html.twig | @CMS/Frontend/Menu/Items/page.html.twig
@cms/Layout/card-background-fitted.html.twig | @CMS/Frontend/Elements/card-background-fitted.html.twig
@cms/Layout/card-background.html.twig | @CMS/Frontend/Elements/card-background.html.twig
@cms/Layout/card-bottom.html.twig | @CMS/Frontend/Elements/card-bottom.html.twig
@cms/Layout/card-left.html.twig | @CMS/Frontend/Elements/card-left.html.twig
@cms/Layout/card-right.html.twig | @CMS/Frontend/Elements/card-right.html.twig
@cms/Layout/card-top.html.twig | @CMS/Frontend/Elements/card-top.html.twig
@cms/Layout/cookie-banner.html.twig | @CMS/Frontend/Elements/cookie-banner.html.twig
@cms/Layout/section-empty.html.twig | @CMS/Frontend/Editor/section-empty.html.twig
@cms/Layout/anchor.html.twig | @CMS/Frontend/Elements/anchor.html.twig
@cms/Layout/card.html.twig | @CMS/Frontend/Elements/card.html.twig
@cms/Layout/column.html.twig | @CMS/Frontend/Elements/column.html.twig
@cms/Layout/controller.html.twig | @CMS/Frontend/Elements/controller.html.twig
@cms/Layout/file.html.twig | @CMS/Frontend/Elements/file.html.twig
@cms/Layout/form.html.twig | @CMS/Frontend/Elements/form.html.twig
@cms/Layout/image.html.twig | @CMS/Frontend/Elements/image.html.twig
@cms/Layout/images.html.twig | @CMS/Frontend/Elements/images.html.twig
@cms/Layout/row.html.twig | @CMS/Frontend/Elements/row.html.twig
@cms/Layout/section.html.twig | @CMS/Frontend/Elements/section.html.twig
@cms/Layout/text.html.twig | @CMS/Frontend/Elements/text.html.twig
@cms/Layout/timing.html.twig | @CMS/Frontend/Elements/timing.html.twig
@cms/Layout/vimeo.html.twig | @CMS/Frontend/Elements/vimeo.html.twig
@cms/Layout/youtube.html.twig | @CMS/Frontend/Elements/youtube.html.twig

##### PHP Klassen
Alt | Neu
--- | ---
RevisionTen\CMS\Model\Alias | RevisionTen\CMS\Entity\Alias
RevisionTen\CMS\Model\Domain | RevisionTen\CMS\Entity\Domain
RevisionTen\CMS\Model\FileRead | RevisionTen\CMS\Entity\FileRead
RevisionTen\CMS\Model\MenuRead | RevisionTen\CMS\Entity\MenuRead
RevisionTen\CMS\Model\PageRead | RevisionTen\CMS\Entity\PageRead
RevisionTen\CMS\Model\PageStreamRead | RevisionTen\CMS\Entity\PageStreamRead
RevisionTen\CMS\Model\RoleRead | RevisionTen\CMS\Entity\RoleRead
RevisionTen\CMS\Model\Task | RevisionTen\CMS\Entity\Task
RevisionTen\CMS\Model\UserRead | RevisionTen\CMS\Entity\UserRead
RevisionTen\CMS\Model\Website | RevisionTen\CMS\Entity\Website

Hier ein Script um die Twig-Pfade und Klassennamen im Projekt anzupassen (muss im Projektverzeichnis ausgeführt werden):
```SH
declare -a FIND=("FormsBundle:Form:renderForm" "RevisionTen\\\\CMS\\\\Model\\\\Alias" "RevisionTen\\\\CMS\\\\Model\\\\Domain" "RevisionTen\\\\CMS\\\\Model\\\\FileRead" "RevisionTen\\\\CMS\\\\Model\\\\MenuRead" "RevisionTen\\\\CMS\\\\Model\\\\PageRead" "RevisionTen\\\\CMS\\\\Model\\\\PageStreamRead" "RevisionTen\\\\CMS\\\\Model\\\\RoleRead" "RevisionTen\\\\CMS\\\\Model\\\\Task" "RevisionTen\\\\CMS\\\\Model\\\\UserRead" "RevisionTen\\\\CMS\\\\Model\\\\Website" "@cms\/Admin\/admin-styles.html.twig" "@cms\/Admin\/admin-scripts.html.twig" "@cms\/base.html.twig" "@cms\/layout.html.twig" "@cms\/sitemap.xml.twig" "@cms\/Helper\/element-config-helper.html.twig" "@cms\/Helper\/section-helper.html.twig" "@cms\/Search\/fulltext.html.twig" "@cms\/Menu\/footer.html.twig" "@cms\/Menu\/main.html.twig" "@cms\/Menu\/Items\/divider.html.twig" "@cms\/Menu\/Items\/link.html.twig" "@cms\/Menu\/Items\/page.html.twig" "@cms\/Layout\/card-background-fitted.html.twig" "@cms\/Layout\/card-background.html.twig" "@cms\/Layout\/card-bottom.html.twig" "@cms\/Layout\/card-left.html.twig" "@cms\/Layout\/card-right.html.twig" "@cms\/Layout\/card-top.html.twig" "@cms\/Layout\/cookie-banner.html.twig" "@cms\/Layout\/section-empty.html.twig" "@cms\/Layout\/anchor.html.twig" "@cms\/Layout\/card.html.twig" "@cms\/Layout\/column.html.twig" "@cms\/Layout\/controller.html.twig" "@cms\/Layout\/file.html.twig" "@cms\/Layout\/form.html.twig" "@cms\/Layout\/image.html.twig" "@cms\/Layout\/images.html.twig" "@cms\/Layout\/row.html.twig" "@cms\/Layout\/section.html.twig" "@cms\/Layout\/text.html.twig" "@cms\/Layout\/timing.html.twig" "@cms\/Layout\/vimeo.html.twig" "@cms\/Layout\/youtube.html.twig")
declare -a REPLACE=("FormsBundle:Form:renderCmsForm" "RevisionTen\\\\CMS\\\\Entity\\\\Alias" "RevisionTen\\\\CMS\\\\Entity\\\\Domain" "RevisionTen\\\\CMS\\\\Entity\\\\FileRead" "RevisionTen\\\\CMS\\\\Entity\\\\MenuRead" "RevisionTen\\\\CMS\\\\Entity\\\\PageRead" "RevisionTen\\\\CMS\\\\Entity\\\\PageStreamRead" "RevisionTen\\\\CMS\\\\Entity\\\\RoleRead" "RevisionTen\\\\CMS\\\\Entity\\\\Task" "RevisionTen\\\\CMS\\\\Entity\\\\UserRead" "RevisionTen\\\\CMS\\\\Entity\\\\Website" "@CMS\/Frontend\/Editor\/admin-styles.html.twig" "@CMS\/Frontend\/Editor\/admin-scripts.html.twig" "@CMS\/Frontend\/base.html.twig" "@CMS\/Frontend\/Page\/simple.html.twig" "@CMS\/Frontend\/sitemap.xml.twig" "@CMS\/Frontend\/Helper\/element-config-helper.html.twig" "@CMS\/Frontend\/Helper\/section-helper.html.twig" "@CMS\/Frontend\/Search\/fulltext.html.twig" "@CMS\/Frontend\/Menu\/footer.html.twig" "@CMS\/Frontend\/Menu\/main.html.twig" "@CMS\/Frontend\/Menu\/Items\/divider.html.twig" "@CMS\/Frontend\/Menu\/Items\/link.html.twig" "@CMS\/Frontend\/Menu\/Items\/page.html.twig" "@CMS\/Frontend\/Elements\/card-background-fitted.html.twig" "@CMS\/Frontend\/Elements\/card-background.html.twig" "@CMS\/Frontend\/Elements\/card-bottom.html.twig" "@CMS\/Frontend\/Elements\/card-left.html.twig" "@CMS\/Frontend\/Elements\/card-right.html.twig" "@CMS\/Frontend\/Elements\/card-top.html.twig" "@CMS\/Frontend\/Elements\/cookie-banner.html.twig" "@CMS\/Frontend\/Editor\/section-empty.html.twig" "@CMS\/Frontend\/Elements\/anchor.html.twig" "@CMS\/Frontend\/Elements\/card.html.twig" "@CMS\/Frontend\/Elements\/column.html.twig" "@CMS\/Frontend\/Elements\/controller.html.twig" "@CMS\/Frontend\/Elements\/file.html.twig" "@CMS\/Frontend\/Elements\/form.html.twig" "@CMS\/Frontend\/Elements\/image.html.twig" "@CMS\/Frontend\/Elements\/images.html.twig" "@CMS\/Frontend\/Elements\/row.html.twig" "@CMS\/Frontend\/Elements\/section.html.twig" "@CMS\/Frontend\/Elements\/text.html.twig" "@CMS\/Frontend\/Elements\/timing.html.twig" "@CMS\/Frontend\/Elements\/vimeo.html.twig" "@CMS\/Frontend\/Elements\/youtube.html.twig" )

for i in "${!FIND[@]}"
do
  echo "Ersetze ${FIND[i]} mit ${REPLACE[i]}"

  # CMS Konfiguration aktualisieren.
  sed -i '' "s/${FIND[i]}/${REPLACE[i]}/g" config/packages/cms.yaml

  # Twig Templates aktualisieren.
  find ./templates -name '*.twig' -print0 | xargs -0 sed -i '' "s/${FIND[i]}/${REPLACE[i]}/g"
  
  # PHP Dateien aktualisieren.
  find ./src -name '*.php' -print0 | xargs -0 sed -i '' "s/${FIND[i]}/${REPLACE[i]}/g"

  # "Master" durch "Main" ersetzen.
  find ./src -name '*.php' -print0 | xargs -0 sed -i '' "s/requestStack->getMasterRequest/requestStack->getMainRequest/g"
done
```


### Neue Mailerkonfiguration

Da das CMS nun nur noch den neuen Symfony Mailer verwendet, muss dafür zwingend eine Konfiguration angelegt sein.


### Migration von EasyAdmin Entity Konfigurationen

Die Entity Konfiguration kann von easy_admin.entities nach cms.entities kopiert werden.

### Database Schema Update

Das Datenbankschema muss abschließend per `bin/console doctrine:schema:update --force` auf den aktuellen Stand gebracht werden.

#### Hinweise:
- Listenfelder die nicht sortierbar sind (z.b. weil sie eine Relationship sind), sollten mit der Eigenschaft `sortable: false` markiert werden.
- Durchsuchbare Listenfelder sollten in der neuen Option `cms.entities.{ Entity }.list.search_fields` aufgelistet werden.
- Die `required` Option von Formularfeldern beim Bearbeiten und Erstellen von Entities entspricht dem Standardwert des jeweiligen FormTypes (meistens `true`).
- Bei der `type`-Option in der Formularfeldkonfiguration sollte der vollständig qualifizierte Pfad des gewünschten FormTypes angegeben werden anstelle eines Alias wie z.b. `choice` oder `collection`.
- Es gibt keine `show`-Ansicht für Entities. Diese kann aber selbst implementiert werden. Dazu einfach einen `actions`-Eintrag anlegen die eine entsprechende Route enthält. 


### Neue Twig Template Pfade

- Als Template-Alias wird nun `@CMS` statt `@cms` verwendet.
- Die Element-Templates befinden sich nun in `@CMS/Frontend/Elements/`.
- Das neue Einfache-Seite-Template befindet sich nun in `@CMS/Frontend/Page/simple.html.twig`.

