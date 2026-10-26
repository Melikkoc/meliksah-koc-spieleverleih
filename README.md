# Spieleverleih

Eine kleine interaktive PHP-Konsolenanwendung zur Verwaltung eines Spieleladens: Konsolen- und Brettspiele liegen auf Regalen mit begrenzter Kapazität und können gesucht, ausgeliehen, zurückgegeben, bearbeitet und gelöscht werden.
Das Projekt ist als überschaubare OOP-Lösung für eine technische Bewerbungsaufgabe aufgebaut.

Während des Programmlaufs liegen die Daten in Objekten im Arbeitsspeicher. Regale, Spiele und aktive Ausleihen werden zusätzlich in `spieleverleih.json` gespeichert und beim nächsten Start wiederhergestellt.
Nur beim ersten Start ohne Datendatei wird der Bestand mit drei Beispielspielen angelegt.

## Voraussetzungen und Start

Benötigt wird **PHP CLI ab Version 8.3**, da `Person` typisierte Klassenkonstanten verwendet. Entwickelt und geprüft wurde die Anwendung mit **PHP 8.5.12**.
Es gibt keine Installation von Projektabhängigkeiten: Composer, Frameworks, Datenbank und Webserver werden nicht benötigt.
Das Projektverzeichnis muss beschreibbar sein, damit Daten- und Sperrdatei angelegt werden können.

Alle folgenden Befehle werden im Projektverzeichnis ausgeführt:

```bash
php -v
php index.php
```

Die Bedienung erfolgt im Terminal: Menüpunkt eingeben, mit Enter bestätigen und den weiteren Eingabeaufforderungen folgen.
Mit `0` wird das Programm beendet. Auch das Ende der Standardeingabe beendet es kontrolliert.

## Bedienung

| Auswahl | Funktion | Benötigte Eingaben |
| --- | --- | --- |
| `1` | Alle Spiele mit Verfügbarkeit anzeigen | Keine |
| `2` | Nach einem Titel oder Titelbestandteil suchen | Suchbegriff |
| `3` | Ein Spiel ausleihen | Spiel-ID, Name und Alter der Person |
| `4` | Ein Spiel zurückgeben | Spiel-ID |
| `5` | Regale mit belegten und freien Plätzen anzeigen | Keine |
| `6` | Ein neues Spiel hinzufügen | Regal-ID, Titel, Plattform, USK, minimale und maximale Spieleranzahl |
| `7` | Ein vorhandenes Spiel bearbeiten | Spiel-ID, anschließend die gewünschten neuen Werte |
| `8` | Ein Spiel löschen | Spiel-ID und Bestätigung mit `j` oder `ja` |
| `0` | Programm beenden | Keine |

Die Zahl in eckigen Klammern vor einem Spiel ist seine ID. Ein Listeneintrag sieht beispielsweise so aus:

```text
[1] Mario Kart 8 | Switch | USK 0 | Spieler: 1–4 | verfügbar
```

Bei ausgeliehenen Spielen steht anstelle von `verfügbar` der Hinweis `ausgeliehen an …` mit dem Namen der Person. Auch ausgeliehene Spiele erscheinen weiterhin in Listen und Suchergebnissen.

Beim Bearbeiten stehen die bisherigen Werte in eckigen Klammern. Enter übernimmt den jeweiligen Wert, etwa wenn nur der Titel geändert werden soll. Bearbeitbar sind Titel, Plattform, USK und Spieleranzahlen; Spiel-ID und Regalzuordnung bleiben erhalten.
Ausgeliehene Spiele müssen vor dem Bearbeiten oder Löschen zurückgegeben werden. Vor dem Löschen wird das ausgewählte Spiel angezeigt und eine Bestätigung abgefragt; Enter oder eine andere Antwort als `j` beziehungsweise `ja` bricht ab. Löschen gibt den Regalplatz frei.

### Einen Ablauf ausprobieren

1. Mit `1` die Spiele anzeigen.
2. Mit `3` eine Ausleihe beginnen: Spiel-ID `3`, einen Namen und Alter `18` eingeben.
3. Mit `1` prüfen, dass „The Last of Us“ jetzt ausgeliehen ist.
4. Mit `4` und Spiel-ID `3` das Spiel zurückgeben.
5. Mit `6` ein neues Spiel zu Regal `2` hinzufügen, etwa „Carcassonne“, Plattform „Brettspiel“, USK `0` und Spieleranzahl `2` bis `5`.
6. Die Erfolgsmeldung zeigt die automatisch vergebene Spiel-ID; beim unveränderten Ausgangsbestand ist das `4`. Über `2` lässt sich das neue Spiel anschließend suchen.
7. Mit `0` beenden und erneut `php index.php` starten: Das hinzugefügte Spiel ist weiterhin vorhanden.

Beim Hinzufügen wird nur das Zielregal ausgewählt. Eine Spiel-ID muss der Benutzer nicht eingeben.
Der Beispielablauf geht vom Ausgangsbestand aus; bei bereits gespeicherten Änderungen können IDs und Verfügbarkeiten abweichen.

### Beispielbestand beim ersten Start

| Spiel-ID | Titel | Plattform | USK | Spieler | Regal |
| --- | --- | --- | --- | --- | --- |
| 1 | Mario Kart 8 | Switch | 0 | 1–4 | 1 |
| 2 | Die Siedler von Catan | Brettspiel | 6 | 3–4 | 2 |
| 3 | The Last of Us | PlayStation | 18 | 1 | 1 |

Regal `1` hat drei Plätze, Regal `2` zwei Plätze. Im Ausgangsbestand ist in beiden Regalen jeweils ein Platz frei.
Die Beispielspiele und Regale werden in `index.php` angelegt, wenn noch keine Datendatei existiert. Änderungen an diesen Vorgaben wirken sich daher nicht auf einen bereits gespeicherten Bestand aus. Einen Menüpunkt zum Anlegen neuer Regale gibt es derzeit nicht.

## Projektaufbau und Verantwortlichkeiten

```text
meliksah-koc-spieleverleih/
├── .gitignore
├── src/
│   ├── Game.php
│   ├── Shelf.php
│   ├── Person.php
│   ├── GameStore.php
│   └── JsonStorage.php
├── index.php
├── tests.php
├── spieleverleih.json
├── spieleverleih.json.lock
├── README.md
└── AGENTS.md
```

`spieleverleih.json` und die Sperrdatei entstehen beim Start automatisch und sind über `.gitignore` vom Repository ausgeschlossen.

| Datei / Klasse | Verantwortung |
| --- | --- |
| `src/Game.php` | Spieldaten und aktuelle ausleihende Person; prüft Verfügbarkeit und USK bei der Ausleihe und ermöglicht die Rückgabe. |
| `src/Person.php` | Name und Alter einer Person; validiert einen nicht leeren Namen und ein Alter von 0 bis 99 Jahren. |
| `src/Shelf.php` | Regal-ID, Kapazität und zugeordnete Spiele; verhindert Überbelegung, doppelte IDs sowie das Ersetzen oder Entfernen ausgeliehener Spiele. |
| `src/GameStore.php` | Verwaltet alle Regale, sucht Spiele, vergibt IDs und verhindert doppelte IDs im gesamten Laden; koordiniert Ausleihe, Rückgabe, Bearbeiten und Löschen. |
| `src/JsonStorage.php` | Lädt und speichert den Bestand als JSON, prüft Dateiinhalte und koordiniert Dateisperre sowie sicheres Ersetzen der Datendatei. |
| `index.php` | Lädt die Klassen und den gespeicherten Bestand, legt bei Bedarf die Beispielspiele an und übernimmt Menü, Speichervorgänge und Fehlermeldungen. |
| `tests.php` | Führt unabhängige Tests mit eigenen Prüffunktionen aus und meldet Fehler über den Exit-Code. |

Die Geschäftsregeln liegen in den Klassen, damit sie auch ohne die CLI verwendet und getestet werden können. Alle PHP-Dateien verwenden `declare(strict_types=1);`; die Klassen nutzen typisierte Eigenschaften und Constructor Property Promotion.
Die öffentlichen Stammdaten sind `readonly`, sodass etwa eine Spiel-ID oder das Alter nicht nachträglich verändert werden kann.
Beim Bearbeiten wird ein vollständig validiertes neues `Game` mit derselben ID an derselben Regalposition eingesetzt. Dadurch bleiben die Stammdaten eines einzelnen Spielobjekts unveränderlich und ungültige Änderungen lassen den alten Bestand bestehen.

Der `GameStore` erzeugt seine Regale selbst und gibt über `getShelfOverview()` nur deren ID, Kapazität und Belegung zurück. So laufen Neuzugänge im Laden immer über seine Prüfung auf eindeutige Spiel-IDs.
Dateizugriffe sind in `JsonStorage` gebündelt, während die bisherigen Klassen weiterhin die Geschäftsregeln durchsetzen.
Interfaces, zusätzliche Architekturschichten und ein Autoloader sind für diesen Umfang nicht erforderlich; die Klassen werden ausdrücklich eingebunden.

## Geschäftsregeln

- **Ein Spieleexemplar pro ID:** Mehrere Exemplare desselben Titels sind mit unterschiedlichen IDs möglich. Ein bereits zugeordnetes Exemplar kann im selben Laden nicht zusätzlich in einem zweiten Regal liegen.
- **Automatische IDs:** `addNewGame()` verwendet die höchste vorhandene Spiel-ID plus eins, bei leerem Bestand die ID `1`. Ein fehlgeschlagener Zugang verbraucht keine ID. Gespeicherte IDs bleiben über Neustarts hinweg erhalten; wird die höchste ID gelöscht, kann diese Nummer später erneut vergeben werden.
- **Regalkapazität:** Regal-IDs und Kapazitäten sind positive ganze Zahlen. Ein ausgeliehenes Spiel bleibt seinem Regal zugeordnet und belegt weiterhin einen Platz; dadurch funktioniert die Rückgabe auch bei einem vollen Regal.
- **Spieldaten:** Titel und Plattform dürfen nicht leer sein. Die Plattform ist Freitext. Erlaubte USK-Werte sind `0`, `6`, `12`, `16` und `18`. Die minimale Spieleranzahl ist mindestens `1`, die maximale mindestens so groß wie die minimale.
- **Person und Ausleihe:** Das Alter ist eine ganze Zahl zwischen `0` und `99` einschließlich. Zusätzlich muss es mindestens der USK entsprechen. Ein Spiel kann nur ausgeliehen werden, wenn es verfügbar ist.
- **Rückgabe:** Nur ein tatsächlich ausgeliehenes Spiel kann zurückgegeben werden. Dabei wird die ausleihende Person entfernt; das Spiel ist anschließend wieder verfügbar.
- **Bearbeiten:** Nur verfügbare Spiele dürfen geändert werden. Es gelten dieselben Prüfungen wie beim Anlegen; ID, Regalzuordnung und Belegung bleiben gleich. Das funktioniert auch in einem vollen Regal.
- **Löschen:** Nur verfügbare Spiele dürfen entfernt werden. Ein gelöschtes Spiel verschwindet aus Listen, Suche und ID-Abfrage; sein Regalplatz wird frei. Leere Regale bleiben erhalten.
- **Suche:** Gesucht wird ausschließlich im Titel, über alle Regale hinweg. Die Suche findet Teilstrings ohne Beachtung der Groß-/Kleinschreibung, auch bei Umlauten. Sonderzeichen werden als Text behandelt. Ein leerer Suchbegriff wird abgewiesen; eine Suche ohne Treffer liefert eine leere Liste.

Der Ausleihstatus wird allein über die gespeicherte `Person` bestimmt: `null` bedeutet verfügbar. Ein zusätzliches Statusfeld ist dadurch nicht nötig.

## JSON-Persistenz

Die CLI verwendet immer `spieleverleih.json` im Projektverzeichnis, unabhängig davon, aus welchem Arbeitsverzeichnis `php index.php` aufgerufen wird.

1. `JsonStorage` sperrt den Bestand für diesen Programmlauf und lädt die Datei über `load()`.
2. Fehlt die Datei, liefert `load()` `null`; die CLI legt die Beispielspiele an und speichert sie einmalig.
3. Bei einer vorhandenen Datei werden JSON-Struktur, Feldtypen und Geschäftsregeln geprüft. Die Klassen werden über ihre Konstruktoren und Methoden wiederhergestellt.
4. Nach erfolgreicher Ausleihe, Rückgabe, Aufnahme, Bearbeitung oder Löschung eines Spiels ruft die CLI `save()` auf. Erst nach erfolgreichem Speichern erscheint die Erfolgsmeldung.

Ein gespeicherter leerer Laden bleibt leer. Eine leere oder beschädigte Datei gilt dagegen als Fehler und wird beim Start nicht mit Beispieldaten überschrieben.

Die Datei enthält ein Objekt mit `shelves`. Jedes Regal enthält seine Kapazität und die zugeordneten Spiele, beispielsweise:

```json
{
    "shelves": [
        {
            "id": 1,
            "capacity": 3,
            "games": [
                {
                    "id": 1,
                    "title": "Mario Kart 8",
                    "platform": "Switch",
                    "usk": 0,
                    "minPlayers": 1,
                    "maxPlayers": 4,
                    "borrower": {"name": "Alex", "age": 18}
                }
            ]
        }
    ]
}
```

Bei verfügbaren Spielen ist `borrower` gleich `null`; bei ausgeliehenen Spielen enthält es Name und Alter der Person. Die Verschachtelung erhält die Regalzuordnung auch während einer Ausleihe.
Die Datei enthält ausschließlich Daten; beim Laden werden daraus neue PHP-Objekte erzeugt. Auch gespeicherte Personen müssen die Alters- und USK-Prüfung bestehen.

Zum Speichern wird zunächst eine vollständige temporäre Datei im selben Verzeichnis geschrieben. Erst danach ersetzt sie die bisherige JSON-Datei durch Umbenennen. Bei einem Schreibfehler bleibt die bisherige Datendatei erhalten.
Die separate Datei `spieleverleih.json.lock` hält die Sperre für den gesamten Programmlauf. Ein zweiter Start mit derselben Datendatei meldet einen Fehler, damit zwei geladene Bestände sich nicht gegenseitig überschreiben. Nach Programmende wird die Sperre freigegeben; die leere Sperrdatei darf bestehen bleiben.

## Die Klassen ohne Menü verwenden

Das folgende Beispiel kann als separate PHP-Datei im Projektverzeichnis ausgeführt werden. Es zeigt die Verwendung der Geschäftslogik ohne die interaktive Menüführung:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Person.php';
require_once __DIR__ . '/src/Game.php';
require_once __DIR__ . '/src/Shelf.php';
require_once __DIR__ . '/src/GameStore.php';

$store = new GameStore();
$store->addShelf(id: 1, capacity: 2);

$game = $store->addNewGame(
    shelfId: 1,
    title: 'Catan',
    platform: 'Brettspiel',
    usk: 6,
    minPlayers: 3,
    maxPlayers: 4,
);

$person = new Person(name: 'Alex', age: 18);
$store->borrowGame($game->id, $person);
echo $game->isAvailable() ? "verfügbar\n" : "ausgeliehen\n";

$store->returnGame($game->id);
echo $game->isAvailable() ? "verfügbar\n" : "ausgeliehen\n";
```

Die Ausgabe lautet zuerst `ausgeliehen`, danach `verfügbar`. Der Laden und der aufrufende Code verwenden dasselbe Spielobjekt; die Zustandsänderung ist deshalb auch über `$game` sichtbar.

`addNewGame()` legt ein Spiel an, ordnet es einem Regal zu und gibt das gespeicherte `Game` zurück. `addGame(int $shelfId, Game $game)` nimmt dagegen ein bereits erzeugtes Spiel mit vorgegebener ID auf, etwa in Tests; auch dabei werden doppelte IDs und die Regalkapazität geprüft.
`getAllGames()` und `searchGames(string $title)` liefern Listen von `Game`-Objekten. `getGameById(int $id)` liefert genau ein Spiel oder wirft eine Exception.
`getGamesInShelf(int $id)` liefert die Spiele eines bestimmten Regals. Die Geschäftslogik allein speichert keine Dateien; dafür muss der aufrufende Code `JsonStorage` einbinden und dessen `load()` beziehungsweise `save()` verwenden.

`updateGame(int $id, string $title, string $platform, int $usk, int $minPlayers, int $maxPlayers)` bearbeitet ein verfügbares Spiel und gibt das neue gespeicherte `Game` zurück. Eine vorher gehaltene Referenz zeigt weiterhin das alte Objekt; verwende den Rückgabewert oder frage das Spiel erneut mit `getGameById()` ab.
`removeGame(int $id)` entfernt ein verfügbares Spiel aus seinem Regal und dem Bestand. Beide Methoden werfen bei unbekannten IDs oder laufender Ausleihe eine Exception.

## Fehlerbehandlung

Die Klassen verwenden eingebaute Exceptions mit verständlichen Meldungen:

| Exception | Typische Ursache |
| --- | --- |
| `InvalidArgumentException` | Ungültige Stammdaten, Alter außerhalb von 0–99, leere Suche oder doppelte Spiel-/Regal-ID |
| `OutOfBoundsException` | Spiel-ID oder Regal-ID existiert nicht |
| `DomainException` | Person ist zu jung, Spiel ist bereits ausgeliehen, Rückgabe ist unzulässig oder ein ausgeliehenes Spiel soll bearbeitet beziehungsweise gelöscht werden |
| `OverflowException` | Regal ist voll oder der Zahlenbereich für neue Spiel-IDs ist ausgeschöpft |
| `RuntimeException` aus `JsonStorage` | Datendatei ist ungültig, nicht lesbar oder nicht schreibbar; ein anderer Programmstart hält bereits die Sperre |

Bei Eingabe- und Geschäftsfehlern zeigt die CLI `Fehler: …` an. Anschließend erscheint das Hauptmenü; die Aktion kann erneut ausgewählt werden. Abgewiesene fachliche Aktionen verändern den vorhandenen Bestand oder die Ausleihe nicht.
Nicht ganzzahlige Zahleneingaben und unbekannte Menüpunkte werden ebenfalls abgefangen. Andere Programme, die die Klassen direkt verwenden, müssen die Exceptions selbst behandeln.
Bei Lade- oder Speicherfehlern endet die CLI kontrolliert mit Exit-Code `1`. Die bisher gespeicherten Daten bleiben erhalten; eine aktuelle, nicht speicherbare Änderung wird nicht als erfolgreich bestätigt und beim Beenden verworfen.

## Tests und Syntaxprüfung

Die automatisierten Tests benötigen keine Testbibliothek und keine laufende Menüanwendung:

```bash
php tests.php
```

Jeder Test erstellt seine eigenen Objekte und prüft einen Ablauf mit `expect()` oder `expectException()`. Die Prüfung hängt nicht von PHPs aktivierbaren `assert()`-Anweisungen ab.
Die Ausgabe zeigt pro Test `[OK]` oder `[FEHLER]` und am Ende die Anzahl der Tests und Fehlschläge. Der Exit-Code ist `0`, wenn alle Tests bestehen, andernfalls `1`.

Abgedeckt sind Suche inklusive Umlauten und Sonderzeichen, automatische und doppelte IDs, erfolgreiche Ausleihe, USK- und Altersgrenzen, doppelte Ausleihe, Rückgabe, reservierte Regalplätze, Kapazitätsgrenzen und ungültige Stammdaten sowie unbekannte IDs.
Zusätzlich werden das Speichern und Laden einschließlich aktiver Ausleihen, leere Bestände, beschädigte Dateien, ungültige gespeicherte Werte, Schreibfehler und die Dateisperre geprüft. Die Dateitests verwenden eigene temporäre Verzeichnisse und verändern `spieleverleih.json` nicht.
Für Bearbeiten und Löschen werden insbesondere Validierung, unveränderte IDs und Regalzuordnungen, freie Regalplätze, der Schutz laufender Ausleihen und das Wiederherstellen der Änderungen nach erneutem Laden geprüft.
Die Tests prüfen die Klassen; Menüeingaben und Neustarts lassen sich zusätzlich mit `php index.php` anhand des oben beschriebenen Ablaufs ausprobieren.

Die Syntax aller PHP-Dateien lässt sich getrennt prüfen:

```bash
php -l src/*.php index.php tests.php
```

## Mögliche Weiterentwicklung

Eine mögliche nächste Erweiterung wären Ausleihfristen mit Fälligkeitsdatum.
Diese sind derzeit nicht implementiert. Auch eine eigene Personenverwaltung gehört aktuell nicht zum Funktionsumfang.
