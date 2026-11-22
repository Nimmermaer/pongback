# Pongback 1.0.0 – Issue-Entwürfe

Milestone: **1.0.0**

> Ziel: Eine stabile, sichere Extension für TYPO3 v13 und v14, die Pingbacks spec-konform empfängt, moderieren lässt, anzeigt und beim Veröffentlichen von Seiten automatisch an verlinkte Seiten sendet.

Entscheidungen: TYPO3 v13 + v14 · Empfangen **und** Senden in 1.0.0 · Webmention erst in 1.1 (Architektur vorbereiten) · bestehenden Code gezielt umbauen.

---

## Issue 1: M1 – Fundament: Umstellung auf TYPO3 v13 + v14

Grundlage für alle weiteren Meilensteine.

**Aufgaben**
- [ ] `composer.json` / `ext_emconf.php`: `typo3/cms-core: ^13.4 || ^14`, PHP `>=8.2`, einheitliche Versionsnummer
- [ ] `typo3/cms-dashboard` als optionale Abhängigkeit (`suggest`)
- [ ] CI (GitHub Actions): PHPStan, PHP-CS-Fixer, Rector, Testmatrix für v13 und v14
- [ ] `$GLOBALS['TSFE']`, `additionalHeaderData` und den `generateMetaTags`-Hook durch v13/v14-APIs ersetzen
- [ ] Plugins als `CType` statt `list_type` registrieren
- [ ] DataHandler-Hooks prüfen und wo möglich durch PSR-14-Events ersetzen
- [ ] Protokoll-Schnittstelle (Empfangen → Prüfen → Speichern) als Grundlage für ein späteres Webmention
- [ ] README-Badges und Versionsangaben angleichen

**Fertig, wenn:** die Extension sich in v13 und v14 installieren lässt und die CI für beide Versionen grün ist.

---

## Issue 2: M2 – Empfangen: sicher und spec-konform

**Aufgaben**
- [ ] Einen einzigen Empfangsweg festlegen (Middleware oder Page-Type), den anderen entfernen
- [ ] XML-RPC `pingback.ping` mit korrekten Fault-Codes (0, 16, 17, 32, 33, 48) und passenden HTTP-Status
- [ ] Model `Pingback` nur über Getter/Setter nutzen (kein direkter Zugriff auf protected Properties)
- [ ] `PingbackReceiver`-Service, der den Pingback tatsächlich speichert
- [ ] Quellseite sicher abrufen: kein Schreiben in Zwischendateien, Timeout, Größenlimit, nur http/https, keine privaten oder internen Adressen, TLS-Prüfung aktiv
- [ ] Prüfen, dass die Quellseite wirklich auf die Ziel-URL verlinkt; Textauszug ohne Hilfsdatei bilden
- [ ] Ziel-URL über den Site-Router auflösen und prüfen, dass sie zu einer eigenen Site gehört (statt Hostname-Substring und Slug-Abfrage)
- [ ] Duplikate erkennen (Fault 48), einfaches Rate-Limit
- [ ] Neue Pingbacks standardmäßig zur Moderation (konfigurierbar)
- [ ] Event `BeforePingbackCreateEvent` behalten; Fehler aus Listenern auswerten

**Fertig, wenn:** ein gültiger Pingback gespeichert wird, ungültige mit dem korrekten Fault-Code abgelehnt werden und alles durch Functional-Tests abgedeckt ist.

---

## Issue 3: M3 – Senden von Pingbacks

**Aufgaben**
- [ ] Beim Veröffentlichen oder Ändern einer Seite (inkl. Content-Elementen, korrekte Seiten-ID) die externen Links sammeln
- [ ] Pingback-Endpunkt der Zielseite entdecken (`X-Pingback`-Header, `<link rel="pingback">`)
- [ ] Messenger-Handler, der asynchron sendet, mit Wiederholung bei Fehlern
- [ ] Nur sichtbare und veröffentlichte Seiten; jedes Ziel nur einmal pro Quelle anpingen
- [ ] Protokoll der gesendeten Pingbacks (Ziel, Status, Zeitpunkt)
- [ ] Tabellenfilter auf exakte Tabellennamen statt Substring-Prüfung
- [ ] Kein Backend-User-Objekt in Queue-Messages serialisieren

**Fertig, wenn:** beim Veröffentlichen einer Seite mit externem Link ein Pingback gesendet und protokolliert wird.

---

## Issue 4: M4 – Moderation und Anzeige

**Aufgaben**
- [ ] Backend-Modul: Liste nach gewählter Seite im Seitenbaum, Freigeben, Ablehnen, Löschen
- [ ] AJAX-Umschalten nur mit Rechteprüfung (Seitenrechte), CSRF-Schutz, POST statt GET
- [ ] Repository-Settings nicht global verändern
- [ ] Optionale E-Mail bei neuem Pingback (vorhandene `Mail`-Klasse nutzen)
- [ ] Frontend-Plugin „Pingbacks dieser Seite“: richtige Seiten-ID, Pagination, Cache-Tags, Cache-Leerung bei neuen oder geänderten Pingbacks
- [ ] Dashboard-Widget: neueste zuerst, eigener Button statt t3news-Überbleibsel, eigene Widget-Gruppe
- [ ] `X-Pingback`-Header und Meta-Tag mit korrekter, absoluter URL (Seiten-ID statt Site-Identifier)

**Fertig, wenn:** Redakteure Pingbacks im Backend moderieren können und freigegebene Pingbacks im Frontend und im Dashboard erscheinen.

---

## Issue 5: M5 – Qualität und Release 1.0.0

**Aufgaben**
- [ ] Unit-Tests: Validierung, Endpunkt-Erkennung, XML-RPC-Antworten
- [ ] Functional-Tests: Empfang, Speichern, Senden, Backend-Umschalten
- [ ] Dokumentation im TYPO3-Format (Installation, Konfiguration, Site-Set/TypoScript, Events, Upgrade)
- [ ] `CHANGELOG.md`
- [ ] Release 1.0.0: Git-Tag, Packagist, TER

**Fertig, wenn:** alle Meilensteine geschlossen sind und die CI grün ist.

---

## Nach 1.0.0 (eigener Milestone)
- 1.1: Webmention-Unterstützung
- EXT:news / EXT:blog-Integration
- Erweiterte Spam-Filter
