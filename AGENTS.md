# AGENTS.md

## Projektkontext

Dieses Repository enthält einen kleinen Spieleverleih als CLI-Anwendung in nativem PHP. Ziel ist eine korrekte, verständliche und leicht nachvollziehbare Lösung der gestellten Aufgabe – ohne Overengineering.

## Arbeitsweise

- Lies vor Änderungen den betroffenen Code, die `README.md` und vorhandene Tests. Orientiere dich an bestehenden Konventionen.
- Setze nur die angeforderte Änderung um. Bevorzuge kleine, gezielte Änderungen statt großflächiger Refactorings.
- Triff einfache, begründbare Entscheidungen. Frage nur nach, wenn eine fachliche Unklarheit eine sichere Umsetzung verhindert.
- Bewahre bestehende Funktionen und öffentliche Schnittstellen, sofern ihre Änderung nicht notwendig ist.
- Füge keine neuen Features, Dateien oder Abhängigkeiten ohne konkreten Bedarf hinzu.
- Behaupte niemals, einen Befehl oder Test ausgeführt zu haben, wenn das nicht geschehen ist.

## PHP und Codequalität

- Verwende natives PHP und die vorhandene objektorientierte Struktur; keine Frameworks, kein Composer und keine zusätzlichen Pakete.
- Nutze `declare(strict_types=1);`, klare Typen, sinnvolle Sichtbarkeiten und präzise Methodennamen.
- Halte Klassen und Methoden klein und ihre Verantwortlichkeiten eindeutig. Geschäftslogik gehört in `src/`, Ein- und Ausgabe in die CLI.
- Vermeide duplizierte Logik, unnötige Abstraktionen, versteckte Seiteneffekte und überflüssigen Zustand.
- Verwende verständliche Exceptions für ungültige Operationen und fange erwartbare Fehler an der CLI-Grenze ab.
- Nutze Sprachfeatures nur, wenn sie von der lokal installierten PHP-Version unterstützt werden.

## Datenpersistenz

- Die CLI speichert Regale, Spiele und aktive Ausleihen in `spieleverleih.json`; Dateizugriffe liegen in `src/JsonStorage.php`.
- Speichere nach erfolgreichen Änderungen und lade den Bestand beim Start; Beispieldaten werden nur bei fehlender Datei angelegt.
- Prüfe beim Laden sowohl die JSON-Struktur als auch Feldtypen und bestehende Geschäftsregeln; beschädigte Dateien dürfen nicht überschrieben werden.
- Behalte die Sperre für den gesamten Programmlauf und das vollständige Schreiben vor dem atomaren Ersetzen der Datendatei bei.
- Melde Lade- und Speicherfehler verständlich; Erfolgsmeldungen dürfen erst nach erfolgreichem Speichern erscheinen.
- Daten-, Sperr- und temporäre Schreibdateien gehören nicht ins Repository; Dateitests verwenden einen getrennten temporären Bestand.

## Sicherheit und Robustheit

- Die Menüpunkte `7` und `8` bearbeiten beziehungsweise löschen Spiele und speichern erfolgreiche Änderungen direkt als JSON.
- Nur verfügbare Spiele dürfen bearbeitet oder gelöscht werden; aktive Ausleihen müssen zunächst zurückgegeben werden.
- Beim Bearbeiten bleiben ID und Regalzuordnung erhalten; validiere alle neuen Angaben vor dem Ersetzen des bisherigen Spielobjekts.
- Enter übernimmt im Bearbeitungsdialog den bisherigen Feldwert; vor dem Löschen fragt die CLI nach einer Bestätigung.
- Löschen gibt den Regalplatz frei; leere Regale und ein leerer gespeicherter Spielebestand bleiben beim Laden erhalten.

- Behandle sämtliche CLI-Eingaben als unzuverlässig: prüfe Typ, Format, Wertebereich und Existenz referenzierter Daten, bevor du Zustände änderst.
- Stelle sicher, dass abgelehnte Aktionen bestehende Daten nicht teilweise verändern.
- Vermeide `eval`, unnötige Shell-Aufrufe, dynamische Includes und unkontrollierte Datei- oder Netzwerkzugriffe.
- Gib keine Zugangsdaten, Tokens oder privaten Informationen im Code, in Logs oder in der Dokumentation aus.
- Führe keine destruktiven Befehle und keine Änderungen außerhalb des Projektverzeichnisses ohne ausdrücklichen Auftrag aus.

## Kommentare und Dokumentation

- Bevorzuge selbsterklärenden Code. Kommentiere **warum** eine nicht offensichtliche Entscheidung, Bedingung oder Zustandsänderung nötig ist – nicht jede triviale Codezeile.
- Ergänze kurze Kommentare unmittelbar am relevanten Code; entferne veraltete oder irreführende Kommentare bei Änderungen.
- Verwende PHPDoc dort, wo PHP-Typen allein Informationen nicht ausdrücken (z. B. Array-Elementtypen) oder eine Methode besondere Vorbedingungen hat.
- Halte die `README.md` bei Änderungen an Bedienung, Startbefehlen, Verhalten oder Architektur aktuell.
- Die später gewünschte ausführliche README soll Entwicklern ohne Projektkenntnisse Zweck, Bedienung, Architektur, Persistenz und Tests erklären.
- Dokumentiere keine geplanten Funktionen als bereits implementiert.

## Tests und Verifikation

- Ergänze oder aktualisiere bei Verhaltensänderungen passende Tests in `tests.php` – ohne zusätzliche Testbibliothek.
- Teste sowohl den Erfolgsfall als auch relevante Fehler- und Grenzfälle, insbesondere Altersprüfung, Ausleihstatus und Regalkapazität.
- Stelle sicher, dass fehlgeschlagene Operationen den bisherigen Zustand nicht verändern.
- Prüfe nach PHP-Änderungen die Syntax **jeder** betroffenen PHP-Datei mit `php -l <datei>`.
- Führe anschließend `php tests.php` aus. Testfehler dürfen nicht ignoriert oder durch Entfernen von Assertions „behoben“ werden.
- Prüfe bei CLI-Änderungen den betroffenen Ablauf mit `php index.php`, soweit interaktiv möglich.
- Falls ein Test oder Befehl nicht ausgeführt werden kann, benenne die Ursache und das verbleibende Risiko ausdrücklich.

## Abschluss jeder Aufgabe

- Prüfe den Diff auf ungewollte Änderungen, Debug-Ausgaben, tote Codepfade und unnötige Dateien.
- Aktualisiere betroffene Tests, Kommentare und README gemeinsam mit dem Code.
- Fasse knapp zusammen: **Was geändert wurde**, **warum**, **welche Prüfungen tatsächlich liefen** und **was gegebenenfalls offen ist**.
