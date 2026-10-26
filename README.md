# Spieleverleih

Eine kleine interaktive PHP-Konsolenanwendung zur Verwaltung eines Spieleladens: Konsolen- und Brettspiele liegen auf Regalen mit begrenzter Kapazität und können gesucht, an Personen ausgeliehen und zurückgegeben werden.
Das Projekt ist als überschaubare OOP-Lösung für eine technische Bewerbungsaufgabe aufgebaut.

Alle Daten liegen im Arbeitsspeicher. Beim Beenden gehen neue Spiele und Ausleihen verloren; jeder Start beginnt wieder mit den drei Beispielspielen.

## Voraussetzungen und Start

Benötigt wird **PHP CLI ab Version 8.3**, da `Person` typisierte Klassenkonstanten verwendet. Entwickelt und geprüft wurde die Anwendung mit **PHP 8.5.12**.
Es gibt keine Installation von Projektabhängigkeiten: Composer, Frameworks, Datenbank und Webserver werden nicht benötigt.

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
| `0` | Programm beenden | Keine |

Die Zahl in eckigen Klammern vor einem Spiel ist seine ID. Ein Listeneintrag sieht beispielsweise so aus:

```text
[1] Mario Kart 8 | Switch | USK 0 | Spieler: 1–4 | verfügbar
```

Bei ausgeliehenen Spielen steht anstelle von `verfügbar` der Hinweis `ausgeliehen an …` mit dem Namen der Person. Auch ausgeliehene Spiele erscheinen weiterhin in Listen und Suchergebnissen.

### Einen Ablauf ausprobieren

1. Mit `1` die Spiele anzeigen.
2. Mit `3` eine Ausleihe beginnen: Spiel-ID `3`, einen Namen und Alter `18` eingeben.
3. Mit `1` prüfen, dass „The Last of Us“ jetzt ausgeliehen ist.
4. Mit `4` und Spiel-ID `3` das Spiel zurückgeben.
5. Mit `6` ein neues Spiel zu Regal `2` hinzufügen, etwa „Carcassonne“, Plattform „Brettspiel“, USK `0` und Spieleranzahl `2` bis `5`.
6. Die Erfolgsmeldung zeigt die automatisch vergebene Spiel-ID `4`; über `2` lässt sich das neue Spiel anschließend suchen.

Beim Hinzufügen wird nur das Zielregal ausgewählt. Eine Spiel-ID muss der Benutzer nicht eingeben.

### Bestand beim Programmstart

| Spiel-ID | Titel | Plattform | USK | Spieler | Regal |
| --- | --- | --- | --- | --- | --- |
| 1 | Mario Kart 8 | Switch | 0 | 1–4 | 1 |
| 2 | Die Siedler von Catan | Brettspiel | 6 | 3–4 | 2 |
| 3 | The Last of Us | PlayStation | 18 | 1 | 1 |

Regal `1` hat drei Plätze, Regal `2` zwei Plätze. Beim Start ist in beiden Regalen jeweils ein Platz frei.
Der Ausgangsbestand wird in `index.php` vor der Menüschleife angelegt; dort lassen sich Beispielspiele und Regalkapazitäten ändern oder weitere Regale ergänzen. Einen Menüpunkt zum Anlegen neuer Regale gibt es derzeit nicht.

## Projektaufbau und Verantwortlichkeiten

```text
meliksah-koc-spieleverleih/
├── src/
│   ├── Game.php
│   ├── Shelf.php
│   ├── Person.php
│   └── GameStore.php
├── index.php
├── tests.php
├── README.md
└── AGENTS.md
```

| Datei / Klasse | Verantwortung |
| --- | --- |
| `src/Game.php` | Spieldaten und aktuelle ausleihende Person; prüft Verfügbarkeit und USK bei der Ausleihe und ermöglicht die Rückgabe. |
| `src/Person.php` | Name und Alter einer Person; validiert einen nicht leeren Namen und ein Alter von 0 bis 99 Jahren. |
| `src/Shelf.php` | Regal-ID, Kapazität und zugeordnete Spiele; verhindert Überbelegung und doppelte IDs innerhalb des Regals. |
| `src/GameStore.php` | Verwaltet alle Regale, sucht Spiele, vergibt neue Spiel-IDs und verhindert doppelte IDs im gesamten Laden; leitet Ausleihe und Rückgabe an das jeweilige Spiel weiter. |
| `index.php` | Lädt die Klassen mit `require_once`, legt den Ausgangsbestand an und übernimmt Menü, Ein-/Ausgabe und Fehlermeldungen. |
| `tests.php` | Führt unabhängige Tests mit eigenen Prüffunktionen aus und meldet Fehler über den Exit-Code. |

Die Geschäftsregeln liegen in den Klassen, damit sie auch ohne die CLI verwendet und getestet werden können. Alle PHP-Dateien verwenden `declare(strict_types=1);`; die Klassen nutzen typisierte Eigenschaften und Constructor Property Promotion.
Die öffentlichen Stammdaten sind `readonly`, sodass etwa eine Spiel-ID oder das Alter nicht nachträglich verändert werden kann.

Der `GameStore` erzeugt seine Regale selbst und gibt über `getShelfOverview()` nur deren ID, Kapazität und Belegung zurück. So laufen Neuzugänge im Laden immer über seine Prüfung auf eindeutige Spiel-IDs.
Interfaces, zusätzliche Architekturschichten und ein Autoloader sind für diesen Umfang nicht erforderlich; die vier Klassen werden ausdrücklich eingebunden.

## Geschäftsregeln

- **Ein Spieleexemplar pro ID:** Mehrere Exemplare desselben Titels sind mit unterschiedlichen IDs möglich. Ein bereits zugeordnetes Exemplar kann im selben Laden nicht zusätzlich in einem zweiten Regal liegen.
- **Automatische IDs:** `addNewGame()` verwendet die höchste vorhandene Spiel-ID plus eins, bei leerem Bestand die ID `1`. Ein fehlgeschlagener Zugang verbraucht keine ID. Die IDs gelten für den jeweiligen Laden im aktuellen Programmlauf.
- **Regalkapazität:** Regal-IDs und Kapazitäten sind positive ganze Zahlen. Ein ausgeliehenes Spiel bleibt seinem Regal zugeordnet und belegt weiterhin einen Platz; dadurch funktioniert die Rückgabe auch bei einem vollen Regal.
- **Spieldaten:** Titel und Plattform dürfen nicht leer sein. Die Plattform ist Freitext. Erlaubte USK-Werte sind `0`, `6`, `12`, `16` und `18`. Die minimale Spieleranzahl ist mindestens `1`, die maximale mindestens so groß wie die minimale.
- **Person und Ausleihe:** Das Alter ist eine ganze Zahl zwischen `0` und `99` einschließlich. Zusätzlich muss es mindestens der USK entsprechen. Ein Spiel kann nur ausgeliehen werden, wenn es verfügbar ist.
- **Rückgabe:** Nur ein tatsächlich ausgeliehenes Spiel kann zurückgegeben werden. Dabei wird die ausleihende Person entfernt; das Spiel ist anschließend wieder verfügbar.
- **Suche:** Gesucht wird ausschließlich im Titel, über alle Regale hinweg. Die Suche findet Teilstrings ohne Beachtung der Groß-/Kleinschreibung, auch bei Umlauten. Sonderzeichen werden als Text behandelt. Ein leerer Suchbegriff wird abgewiesen; eine Suche ohne Treffer liefert eine leere Liste.

Der Ausleihstatus wird allein über die gespeicherte `Person` bestimmt: `null` bedeutet verfügbar. Ein zusätzliches Statusfeld ist dadurch nicht nötig.

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

## Fehlerbehandlung

Die Klassen verwenden eingebaute Exceptions mit verständlichen Meldungen:

| Exception | Typische Ursache |
| --- | --- |
| `InvalidArgumentException` | Ungültige Stammdaten, Alter außerhalb von 0–99, leere Suche oder doppelte Spiel-/Regal-ID |
| `OutOfBoundsException` | Spiel-ID oder Regal-ID existiert nicht |
| `DomainException` | Person ist zu jung, Spiel ist bereits ausgeliehen oder wurde vor der Rückgabe nicht ausgeliehen |
| `OverflowException` | Regal ist voll oder der Zahlenbereich für neue Spiel-IDs ist ausgeschöpft |

Die CLI fängt diese Exceptions ab und zeigt `Fehler: …` an. Anschließend erscheint das Hauptmenü; die Aktion kann erneut ausgewählt werden. Fehlgeschlagene Aktionen verändern den vorhandenen Bestand oder die Ausleihe nicht.
Nicht ganzzahlige Zahleneingaben und unbekannte Menüpunkte werden ebenfalls abgefangen. Andere Programme, die die Klassen direkt verwenden, müssen die Exceptions selbst behandeln.

## Tests und Syntaxprüfung

Die automatisierten Tests benötigen keine Testbibliothek und keine laufende Menüanwendung:

```bash
php tests.php
```

Jeder Test erstellt seine eigenen Objekte und prüft einen Ablauf mit `expect()` oder `expectException()`. Die Prüfung hängt nicht von PHPs aktivierbaren `assert()`-Anweisungen ab.
Die Ausgabe zeigt pro Test `[OK]` oder `[FEHLER]` und am Ende die Anzahl der Tests und Fehlschläge. Der Exit-Code ist `0`, wenn alle Tests bestehen, andernfalls `1`.

Abgedeckt sind Suche inklusive Umlauten und Sonderzeichen, automatische und doppelte IDs, erfolgreiche Ausleihe, USK- und Altersgrenzen, doppelte Ausleihe, Rückgabe, reservierte Regalplätze, Kapazitätsgrenzen und ungültige Stammdaten sowie unbekannte IDs.
Die Tests prüfen die Klassen; Menüeingaben lassen sich zusätzlich mit `php index.php` anhand des oben beschriebenen Ablaufs ausprobieren.

Die Syntax aller PHP-Dateien lässt sich getrennt prüfen:

```bash
php -l src/*.php index.php tests.php
```

## Mögliche Weiterentwicklung

Sinnvolle nächste Schritte wären Datenpersistenz, damit Bestand und Ausleihen einen Neustart überstehen, sowie Ausleihfristen mit Fälligkeitsdatum.
Beides ist derzeit nicht implementiert. Auch das Bearbeiten oder Entfernen von Spielen und eine eigene Personenverwaltung gehören aktuell nicht zum Funktionsumfang.
