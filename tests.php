<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Person.php';
require_once __DIR__ . '/src/Game.php';
require_once __DIR__ . '/src/Shelf.php';
require_once __DIR__ . '/src/GameStore.php';
require_once __DIR__ . '/src/JsonStorage.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param class-string<Throwable> $exceptionClass */
function expectException(string $exceptionClass, callable $action): void
{
    try {
        $action();
    } catch (Throwable $exception) {
        if ($exception instanceof $exceptionClass) {
            return;
        }

        throw new RuntimeException(
            sprintf('Erwartet: %s; erhalten: %s (%s).', $exceptionClass, $exception::class, $exception->getMessage()),
            previous: $exception,
        );
    }

    throw new RuntimeException('Erwartete Exception wurde nicht ausgelöst: ' . $exceptionClass);
}

function createStore(): GameStore
{
    // Jeder Test erhält einen eigenen Bestand, damit Zustandsänderungen andere Tests nicht beeinflussen.
    $store = new GameStore();
    $store->addShelf(1, 2);
    $store->addShelf(2, 2);
    $store->addGame(1, new Game(1, 'Mario Kart 8', 'Switch', 0, 1, 4));
    $store->addGame(2, new Game(2, 'Die Siedler von Catan', 'Brettspiel', 6, 3, 4));
    $store->addGame(1, new Game(3, 'The Last of Us', 'PlayStation', 18, 1, 1));

    return $store;
}

function withStorageFile(callable $test): void
{
    $directory = sys_get_temp_dir() . '/spieleverleih-test-' . bin2hex(random_bytes(8));

    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Das Testverzeichnis konnte nicht angelegt werden.');
    }

    try {
        $test($directory . '/bestand.json');
    } finally {
        // Testdateien bleiben getrennt vom echten Bestand und werden auch bei Fehlern entfernt.
        foreach (array_diff(scandir($directory), ['.', '..']) as $name) {
            $path = $directory . '/' . $name;
            is_dir($path) ? rmdir($path) : unlink($path);
        }

        rmdir($directory);
    }
}

$tests = [
    'Spieldaten können bei gleicher ID und Regalzuordnung auch im vollen Regal bearbeitet werden' => function (): void {
        $store = createStore();
        $before = $store->getShelfOverview();
        $updated = $store->updateGame(1, 'Mario Kart Deluxe', 'Switch 2', 12, 2, 8);
        expect($store->getGameById(1) === $updated, 'Das bearbeitete Spiel fehlt im Bestand.');
        expect($updated->platform === 'Switch 2' && $updated->usk === 12, 'Plattform oder USK wurde nicht geändert.');
        expect($updated->minPlayers === 2 && $updated->maxPlayers === 8, 'Die Spieleranzahl wurde nicht geändert.');
        expect($store->searchGames('Mario Kart 8') === [], 'Der alte Titel darf nicht mehr gefunden werden.');
        expect($store->searchGames('Deluxe') === [$updated], 'Der neue Titel muss gefunden werden.');
        expect($store->getGamesInShelf(1)[0] === $updated, 'Das Spiel muss im ursprünglichen Regal bleiben.');
        expect($store->getShelfOverview() === $before, 'Bearbeiten darf keinen weiteren Platz belegen.');
        expectException(DomainException::class, fn () => $store->borrowGame(1, new Person('Alex', 11)));
    },
    'Ungültige Änderungen lassen sämtliche bisherigen Spieldaten unverändert' => function (): void {
        $store = createStore();
        $original = $store->getGameById(1);
        $before = $store->getShelfOverview();
        $invalidDetails = [
            ['', 'Switch', 0, 1, 4],
            ['Neu', ' ', 0, 1, 4],
            ['Neu', 'Switch', 7, 1, 4],
            ['Neu', 'Switch', 0, 0, 4],
            ['Neu', 'Switch', 0, 4, 3],
        ];

        foreach ($invalidDetails as $details) {
            expectException(InvalidArgumentException::class, fn () => $store->updateGame(1, ...$details));
            expect($store->getGameById(1) === $original, 'Eine ungültige Änderung darf das Original nicht ersetzen.');
            expect($store->getShelfOverview() === $before, 'Eine ungültige Änderung darf die Belegung nicht ändern.');
        }
    },
    'Ausgeliehene Spiele können erst nach der Rückgabe bearbeitet werden' => function (): void {
        $store = createStore();
        $person = new Person('Alex', 20);
        $store->borrowGame(1, $person);
        expectException(DomainException::class, fn () => $store->updateGame(1, 'Neu', 'Switch', 0, 1, 4));
        expect($store->getGameById(1)->title === 'Mario Kart 8', 'Der Titel darf während einer Ausleihe nicht verändert werden.');
        expect($store->getGameById(1)->getBorrower() === $person, 'Die laufende Ausleihe muss erhalten bleiben.');
        $store->returnGame(1);
        expect($store->updateGame(1, 'Neu', 'Switch', 0, 1, 4)->title === 'Neu', 'Nach der Rückgabe muss Bearbeiten funktionieren.');
    },
    'Löschen entfernt das Spiel aus Suche und Bestand und gibt seinen Regalplatz frei' => function (): void {
        $store = createStore();
        $otherGame = $store->getGameById(3);
        $store->removeGame(1);
        expectException(OutOfBoundsException::class, fn () => $store->getGameById(1));
        expectException(OutOfBoundsException::class, fn () => $store->removeGame(1));
        expect($store->searchGames('Mario') === [], 'Ein gelöschtes Spiel darf nicht mehr gefunden werden.');
        expect(count($store->getAllGames()) === 2, 'Das gelöschte Spiel ist noch im Bestand.');
        expect($store->getShelfOverview()[0]['gameCount'] === 1, 'Der Regalplatz wurde nicht freigegeben.');
        expect($store->getGameById(3) === $otherGame, 'Andere Spiele dürfen nicht verändert werden.');
        $newGame = $store->addNewGame(1, 'Neues Spiel', 'Switch', 0, 1, 4);
        expect($newGame->id === 4, 'Ein neues Spiel muss ohne ID-Kollision auf den freien Platz passen.');
    },
    'Ausgeliehene Spiele können erst nach der Rückgabe gelöscht werden' => function (): void {
        $store = createStore();
        $person = new Person('Alex', 20);
        $before = $store->getShelfOverview();
        $store->borrowGame(1, $person);
        expectException(DomainException::class, fn () => $store->removeGame(1));
        expect($store->getGameById(1)->getBorrower() === $person, 'Die Ausleihe muss nach abgewiesenem Löschen erhalten bleiben.');
        expect($store->getShelfOverview() === $before, 'Der reservierte Regalplatz muss erhalten bleiben.');
        $store->returnGame(1);
        $store->removeGame(1);
        expectException(OutOfBoundsException::class, fn () => $store->getGameById(1));
    },
    'Ein Regal nimmt beim Ersetzen kein unbekanntes Spiel auf und behandelt unbekannte Lösch-IDs' => function (): void {
        $shelf = new Shelf(1, 2);
        $game = new Game(1, 'Mario Kart 8', 'Switch', 0, 1, 4);
        $shelf->addGame($game);
        expectException(OutOfBoundsException::class, fn () => $shelf->replaceGame(new Game(2, 'Neu', 'Switch', 0, 1, 4)));
        expectException(OutOfBoundsException::class, fn () => $shelf->removeGame(2));
        expect($shelf->getGames() === [$game], 'Fehlgeschlagene Regalaktionen dürfen den Inhalt nicht verändern.');
    },
    'Änderungen und Löschungen bleiben nach Speichern und erneutem Laden erhalten' => function (): void {
        withStorageFile(function (string $path): void {
            $storage = new JsonStorage($path);
            $store = createStore();
            $store->updateGame(1, 'Mario Kart Deluxe', 'Switch 2', 12, 2, 8);
            $store->removeGame(2);
            $storage->save($store);
            unset($storage);
            $storage = new JsonStorage($path);
            $loaded = $storage->load();
            $game = $loaded->getGameById(1);
            expect($game->title === 'Mario Kart Deluxe' && $game->platform === 'Switch 2', 'Geänderte Texte wurden nicht gespeichert.');
            expect($game->usk === 12 && $game->minPlayers === 2 && $game->maxPlayers === 8, 'Geänderte Zahlen wurden nicht gespeichert.');
            expectException(OutOfBoundsException::class, fn () => $loaded->getGameById(2));
            expect($loaded->getShelfOverview() === $store->getShelfOverview(), 'Die geänderte Belegung wurde nicht gespeichert.');
        });
    },
    'Ein vollständig gelöschter Spielebestand bleibt leer und kann wieder befüllt werden' => function (): void {
        withStorageFile(function (string $path): void {
            $storage = new JsonStorage($path);
            $store = createStore();

            foreach ($store->getAllGames() as $game) {
                $store->removeGame($game->id);
            }

            $storage->save($store);
            $loaded = $storage->load();
            expect($loaded->getAllGames() === [], 'Gelöschte Spiele dürfen beim Laden nicht wieder erscheinen.');
            expect(count($loaded->getShelfOverview()) === 2, 'Leere Regale müssen erhalten bleiben.');
            expect($loaded->addNewGame(1, 'Neustart', 'Switch', 0, 1, 2)->id === 1, 'Bei leerem Bestand beginnt die ID-Vergabe wieder bei 1.');
        });
    },
    'Eine fehlende Datendatei wird von einem gespeicherten leeren Laden unterschieden' => function (): void {
        withStorageFile(function (string $path): void {
            $storage = new JsonStorage($path);
            expect($storage->load() === null, 'Bei einer fehlenden Datei muss null zurückgegeben werden.');
            expect(!file_exists($path), 'Laden darf keine Datendatei anlegen.');
            $storage->save(new GameStore());
            $loaded = $storage->load();
            expect($loaded instanceof GameStore, 'Ein gespeicherter leerer Laden muss geladen werden.');
            expect($loaded->getShelfOverview() === [], 'Ein leerer Laden darf keine Beispielregale erhalten.');
        });
    },
    'JSON erhält Spieldaten, Regalzuordnung, leere Regale und aktive Ausleihen' => function (): void {
        withStorageFile(function (string $path): void {
            $store = createStore();
            $store->addShelf(3, 2);
            $store->addNewGame(2, 'Ärger im Märchenwald', 'Brettspiel', 6, 2, 5);
            $store->borrowGame(3, new Person('Müller', 99));
            $storage = new JsonStorage($path);
            $storage->save($store);
            unset($storage);

            $storage = new JsonStorage($path);
            $loaded = $storage->load();
            expect($loaded instanceof GameStore, 'Der gespeicherte Laden fehlt.');
            expect($loaded->getShelfOverview() === $store->getShelfOverview(), 'Die Regaldaten wurden verändert.');

            foreach ($store->getAllGames() as $game) {
                $loadedGame = $loaded->getGameById($game->id);
                expect(get_object_vars($loadedGame) === get_object_vars($game), 'Die Spieldaten wurden verändert.');
                expect($loadedGame->isAvailable() === $game->isAvailable(), 'Der Ausleihstatus wurde verändert.');
            }

            foreach ($store->getShelfOverview() as $shelf) {
                $ids = static fn (Game $game): int => $game->id;
                expect(
                    array_map($ids, $loaded->getGamesInShelf($shelf['id'])) === array_map($ids, $store->getGamesInShelf($shelf['id'])),
                    'Die Regalzuordnung wurde verändert.',
                );
            }

            expect($loaded->getGameById(3)->getBorrower()?->name === 'Müller', 'Der Name der Person wurde nicht geladen.');
            expect($loaded->getGameById(3)->getBorrower()?->age === 99, 'Das Alter der Person wurde nicht geladen.');
        });
    },
    'Nach erneutem Laden funktionieren Rückgabe, USK-Prüfung und nächste Spiel-ID' => function (): void {
        withStorageFile(function (string $path): void {
            $storage = new JsonStorage($path);
            $store = createStore();
            $store->borrowGame(3, new Person('Alex', 18));
            $storage->save($store);
            $loaded = $storage->load();
            expectException(DomainException::class, fn () => $loaded->borrowGame(3, new Person('Kim', 20)));
            $loaded->returnGame(3);
            expectException(DomainException::class, fn () => $loaded->borrowGame(3, new Person('Kim', 17)));
            $game = $loaded->addNewGame(2, 'Neues Spiel', 'Switch', 0, 1, 4);
            expect($game->id === 4, 'Die automatische ID muss nach dem Laden fortgesetzt werden.');
            $storage->save($loaded);
            expect($storage->load()->getGameById(3)->isAvailable(), 'Die Rückgabe wurde nicht gespeichert.');
            expect($storage->load()->getGameById(4)->title === 'Neues Spiel', 'Das neue Spiel wurde nicht gespeichert.');
        });
    },
    'Kaputte oder falsch aufgebaute JSON-Dateien werden nicht überschrieben' => function (): void {
        withStorageFile(function (string $path): void {
            $storage = new JsonStorage($path);

            foreach (['', '{kaputt', 'null', '[]', '{}', '{"shelves": {}}', '{"shelves": [null]}'] as $json) {
                file_put_contents($path, $json);
                expectException(RuntimeException::class, fn () => $storage->load());
                expect(file_get_contents($path) === $json, 'Eine ungültige Datei darf beim Laden nicht verändert werden.');
            }
        });
    },
    'Gespeicherte Daten müssen dieselben Typen und Geschäftsregeln erfüllen wie CLI-Eingaben' => function (): void {
        withStorageFile(function (string $path): void {
            $storage = new JsonStorage($path);
            $store = createStore();
            $store->borrowGame(3, new Person('Alex', 18));
            $storage->save($store);
            $validData = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $invalidChanges = [
                function (array &$data): void { $data['shelves'][0]['id'] = '1'; },
                function (array &$data): void { $data['shelves'][0]['capacity'] = 0; },
                function (array &$data): void { $data['shelves'][0]['capacity'] = 1; },
                function (array &$data): void { $data['shelves'][] = $data['shelves'][0]; },
                function (array &$data): void { $data['shelves'][1]['games'][0]['id'] = 1; },
                function (array &$data): void { $data['shelves'][0]['games'][0]['title'] = ' '; },
                function (array &$data): void { $data['shelves'][0]['games'][0]['usk'] = 7; },
                function (array &$data): void { $data['shelves'][0]['games'][0]['minPlayers'] = 0; },
                function (array &$data): void { $data['shelves'][0]['games'][0]['maxPlayers'] = 0; },
                function (array &$data): void { unset($data['shelves'][0]['games'][0]['platform']); },
                function (array &$data): void { unset($data['shelves'][0]['games'][0]['borrower']); },
                function (array &$data): void { $data['shelves'][0]['games'][0]['borrower'] = false; },
                function (array &$data): void { $data['shelves'][0]['games'][1]['borrower']['name'] = ''; },
                function (array &$data): void { $data['shelves'][0]['games'][1]['borrower']['age'] = 100; },
                function (array &$data): void { $data['shelves'][0]['games'][1]['borrower']['age'] = 17; },
            ];

            foreach ($invalidChanges as $change) {
                $data = $validData;
                $change($data);
                $json = json_encode($data, JSON_THROW_ON_ERROR);
                file_put_contents($path, $json);
                expectException(RuntimeException::class, fn () => $storage->load());
                expect(file_get_contents($path) === $json, 'Abgewiesene Daten müssen unverändert bleiben.');
            }
        });
    },
    'Nicht als JSON darstellbare Daten lassen den gespeicherten Bestand unverändert' => function (): void {
        withStorageFile(function (string $path): void {
            $storage = new JsonStorage($path);
            $store = createStore();
            $storage->save($store);
            $before = file_get_contents($path);
            $store->addNewGame(2, "Ungültiges UTF-8: \xFF", 'Switch', 0, 1, 4);
            expectException(RuntimeException::class, fn () => $storage->save($store));
            expect(file_get_contents($path) === $before, 'Ein JSON-Fehler darf die vorhandene Datei nicht verändern.');
        });
    },
    'Dateifehler werden gemeldet und temporäre Schreibdateien aufgeräumt' => function (): void {
        withStorageFile(function (string $path): void {
            $storage = new JsonStorage($path);
            mkdir($path);
            expectException(RuntimeException::class, fn () => $storage->save(createStore()));
            expect(is_dir($path), 'Das bestehende Ziel darf nicht verändert werden.');
            expect(glob(dirname($path) . '/.spieleverleih-*') === [], 'Temporäre Schreibdateien müssen entfernt werden.');
            rmdir($path);
            $storage->save(createStore());
            expect(count($storage->load()->getAllGames()) === 3, 'Speichern muss nach Beheben des Dateifehlers funktionieren.');
            expectException(RuntimeException::class, fn () => new JsonStorage(dirname($path) . '/fehlt/bestand.json'));
        });
    },
    'Eine Datendatei kann nur von einem geöffneten Speicher gleichzeitig verwendet werden' => function (): void {
        withStorageFile(function (string $path): void {
            $first = new JsonStorage($path);
            expectException(RuntimeException::class, fn () => new JsonStorage($path));
            $first->save(createStore());
            unset($first);
            $second = new JsonStorage($path);
            expect(count($second->load()->getAllGames()) === 3, 'Nach Freigabe muss die Datei wieder verwendbar sein.');
        });
    },
    'Neue Spiele erhalten automatisch fortlaufende IDs über alle Regale hinweg' => function (): void {
        $store = new GameStore();
        $store->addShelf(1, 2);
        $store->addShelf(2, 2);
        $first = $store->addNewGame(1, 'Mario Kart 8', 'Switch', 0, 1, 4);
        $second = $store->addNewGame(2, 'Catan', 'Brettspiel', 6, 3, 4);
        expect($first->id === 1 && $second->id === 2, 'Die IDs müssen automatisch bei 1 beginnen.');
        expect($store->getGameById(2) === $second, 'Das neue Spiel wurde nicht gespeichert.');
    },
    'Automatische IDs berücksichtigen auch bereits vorhandene höhere IDs' => function (): void {
        $store = createStore();
        $store->addShelf(3, 2);
        $store->addGame(3, new Game(20, 'Vorhandenes Spiel', 'Switch', 0, 1, 4));
        $game = $store->addNewGame(2, 'Neues Spiel', 'Switch', 0, 1, 4);
        expect($game->id === 21, 'Die neue ID muss größer als die vorhandenen IDs sein.');
        expect($store->getGameById(20)->title === 'Vorhandenes Spiel', 'Ein vorhandenes Spiel wurde überschrieben.');
    },
    'Fehlgeschlagene Neuzugänge verbrauchen keine automatische ID' => function (): void {
        $store = createStore();
        expectException(OverflowException::class, fn () => $store->addNewGame(1, 'Neu', 'Switch', 0, 1, 4));
        expectException(OutOfBoundsException::class, fn () => $store->addNewGame(999, 'Neu', 'Switch', 0, 1, 4));
        expectException(InvalidArgumentException::class, fn () => $store->addNewGame(2, 'Neu', 'Switch', 0, 4, 1));
        $game = $store->addNewGame(2, 'Neu', 'Switch', 0, 1, 4);
        expect($game->id === 4, 'Nach einem Fehler muss die nächste freie ID weiter verfügbar sein.');
        expect(count($store->getAllGames()) === 4, 'Nur der erfolgreiche Neuzugang darf gespeichert sein.');
    },
    'Das Alter ist auf 0 bis 99 Jahre begrenzt' => function (): void {
        expect((new Person('Alex', 0))->age === 0, 'Die untere Altersgrenze muss erlaubt sein.');
        expect((new Person('Alex', 99))->age === 99, 'Die obere Altersgrenze muss erlaubt sein.');

        foreach ([-1, 100, 999, PHP_INT_MAX] as $age) {
            expectException(InvalidArgumentException::class, fn () => new Person('Alex', $age));
        }
    },
    'Suche findet Titel unabhängig von Groß-/Kleinschreibung und Regal' => function (): void {
        $store = createStore();
        expect($store->searchGames(' mArIo ')[0]->id === 1, 'Mario wurde nicht gefunden.');
        expect($store->searchGames('SIEDLER')[0]->id === 2, 'Catan wurde nicht gefunden.');
        expect($store->searchGames('unbekannt') === [], 'Eine erfolglose Suche muss leer sein.');
    },
    'Suche unterstützt Umlaute und behandelt Sonderzeichen als Text' => function (): void {
        $store = createStore();
        $store->addGame(2, new Game(4, 'Ärger im Märchenwald [Deluxe]', 'Brettspiel', 0, 1, 4));
        expect($store->searchGames('ärger')[0]->id === 4, 'Umlaute wurden nicht gefunden.');
        expect($store->searchGames('[Deluxe]')[0]->id === 4, 'Sonderzeichen wurden nicht gefunden.');
        expect($store->searchGames('.*') === [], 'Suchbegriffe dürfen keine regulären Ausdrücke sein.');
    },
    'Alle Spiele und einzelne IDs können abgefragt werden' => function (): void {
        $store = createStore();
        expect(count($store->getAllGames()) === 3, 'Es fehlen Spiele.');
        expect($store->getGameById(2)->title === 'Die Siedler von Catan', 'Falsches Spiel gefunden.');
    },
    'Ausleihe speichert die Person und ändert die Verfügbarkeit' => function (): void {
        $store = createStore();
        $person = new Person('Alex', 12);
        $store->borrowGame(1, $person);
        expect(!$store->getGameById(1)->isAvailable(), 'Das Spiel muss ausgeliehen sein.');
        expect($store->getGameById(1)->getBorrower() === $person, 'Die ausleihende Person fehlt.');
    },
    'Personen unter der USK-Grenze werden abgewiesen' => function (): void {
        $store = createStore();
        expectException(DomainException::class, fn () => $store->borrowGame(3, new Person('Alex', 17)));
        expect($store->getGameById(3)->isAvailable(), 'Eine abgewiesene Ausleihe darf nichts verändern.');
    },
    'Die genaue USK-Altersgrenze erlaubt die Ausleihe' => function (): void {
        $store = createStore();
        $store->borrowGame(3, new Person('Alex', 18));
        expect(!$store->getGameById(3)->isAvailable(), 'Ausleihe ab 18 muss möglich sein.');
        $store->borrowGame(1, new Person('Kim', 0));
        expect(!$store->getGameById(1)->isAvailable(), 'USK 0 muss ohne Mindestalter ausleihbar sein.');
    },
    'Doppelte Ausleihe überschreibt die ursprüngliche Person nicht' => function (): void {
        $store = createStore();
        $person = new Person('Alex', 20);
        $store->borrowGame(1, $person);
        expectException(DomainException::class, fn () => $store->borrowGame(1, new Person('Kim', 25)));
        expect($store->getGameById(1)->getBorrower() === $person, 'Die ursprüngliche Ausleihe wurde verändert.');
    },
    'Rückgabe stellt die Verfügbarkeit für eine weitere Ausleihe her' => function (): void {
        $store = createStore();
        $store->borrowGame(1, new Person('Alex', 20));
        $store->returnGame(1);
        expect($store->getGameById(1)->isAvailable(), 'Das Spiel muss wieder verfügbar sein.');
        expect($store->getGameById(1)->getBorrower() === null, 'Die Person muss bei Rückgabe entfernt werden.');
        $store->borrowGame(1, new Person('Kim', 25));
        expect($store->getGameById(1)->getBorrower()?->name === 'Kim', 'Erneute Ausleihe ist fehlgeschlagen.');
    },
    'Nicht ausgeliehene Spiele können nicht zurückgegeben werden' => function (): void {
        $store = createStore();
        expectException(DomainException::class, fn () => $store->returnGame(1));
        expect($store->getGameById(1)->isAvailable(), 'Das Spiel muss verfügbar bleiben.');
    },
    'Ein Regal hält seine Kapazität auch während einer Ausleihe ein' => function (): void {
        $shelf = new Shelf(1, 1);
        $game = new Game(1, 'Mario Kart 8', 'Switch', 0, 1, 4);
        $shelf->addGame($game);
        $game->borrow(new Person('Alex', 20));
        expectException(OverflowException::class, fn () => $shelf->addGame(new Game(2, 'Catan', 'Brettspiel', 6, 3, 4)));
        expect($shelf->getGames() === [$game], 'Der reservierte Platz muss erhalten bleiben.');
        $game->returnGame();
        expect($game->isAvailable(), 'Die Rückgabe in ein volles Regal muss funktionieren.');
    },
    'Ein fehlgeschlagener Zugang verändert den Laden nicht' => function (): void {
        $store = createStore();
        expectException(OverflowException::class, fn () => $store->addGame(1, new Game(4, 'Neu', 'Switch', 0, 1, 2)));
        expect(count($store->getAllGames()) === 3, 'Ein volles Regal darf kein Spiel aufnehmen.');
        expectException(OutOfBoundsException::class, fn () => $store->getGameById(4));
        $store->addGame(2, new Game(4, 'Neu', 'Switch', 0, 1, 2));
        expect($store->getGameById(4)->title === 'Neu', 'Ein abgewiesenes Spiel muss anderswo hinzugefügt werden können.');
    },
    'Doppelte Spiel-IDs sind regalübergreifend verboten' => function (): void {
        $store = createStore();
        expectException(InvalidArgumentException::class, fn () => $store->addGame(2, new Game(1, 'Duplikat', 'Switch', 0, 1, 2)));
        expect(count($store->getAllGames()) === 3, 'Die doppelte ID wurde hinzugefügt.');
        expect($store->getGameById(1)->title === 'Mario Kart 8', 'Das Original wurde überschrieben.');
    },
    'Dasselbe Spiel kann nicht zwei Regalen zugeordnet werden' => function (): void {
        $store = createStore();
        expectException(InvalidArgumentException::class, fn () => $store->addGame(2, $store->getGameById(1)));
        expect($store->getShelfOverview()[1]['gameCount'] === 1, 'Das zweite Regal wurde verändert.');
    },
    'Auch ein einzelnes Regal weist doppelte Spiel-IDs ab' => function (): void {
        $shelf = new Shelf(1, 2);
        $game = new Game(1, 'Mario Kart 8', 'Switch', 0, 1, 4);
        $shelf->addGame($game);
        expectException(InvalidArgumentException::class, fn () => $shelf->addGame($game));
        expectException(InvalidArgumentException::class, fn () => $shelf->addGame(new Game(1, 'Duplikat', 'Switch', 0, 1, 2)));
        expect($shelf->getGames() === [$game], 'Das Original muss erhalten bleiben.');
    },
    'Unbekannte Spiel-IDs werden bei Bestands- und Ausleihaktionen behandelt' => function (): void {
        $store = createStore();
        foreach ([-1, 0, 999] as $id) {
            expectException(OutOfBoundsException::class, fn () => $store->getGameById($id));
            expectException(OutOfBoundsException::class, fn () => $store->borrowGame($id, new Person('Alex', 20)));
            expectException(OutOfBoundsException::class, fn () => $store->returnGame($id));
            expectException(OutOfBoundsException::class, fn () => $store->updateGame($id, 'Neu', 'Switch', 0, 1, 4));
            expectException(OutOfBoundsException::class, fn () => $store->removeGame($id));
        }
    },
    'Unbekannte und doppelte Regal-IDs werden behandelt' => function (): void {
        $store = createStore();
        expectException(OutOfBoundsException::class, fn () => $store->getGamesInShelf(999));
        expectException(OutOfBoundsException::class, fn () => $store->addGame(999, new Game(4, 'Neu', 'Switch', 0, 1, 2)));
        expectException(InvalidArgumentException::class, fn () => $store->addShelf(1, 99));
        expect(count($store->getAllGames()) === 3, 'Die Spiele dürfen nicht verändert werden.');
        expect($store->getShelfOverview()[0]['capacity'] === 2, 'Das vorhandene Regal wurde überschrieben.');
    },
    'Ausleihe und Rückgabe verändern weder Regalzuordnung noch Belegung' => function (): void {
        $store = createStore();
        $before = $store->getShelfOverview();
        $store->borrowGame(1, new Person('Alex', 20));
        expect($store->getShelfOverview() === $before, 'Die Ausleihe darf keinen Regalplatz freigeben.');
        $store->returnGame(1);
        expect($store->getShelfOverview() === $before, 'Die Rückgabe darf die Belegung nicht ändern.');
    },
    'Ungültige Stammdaten und leere Suchbegriffe werden abgewiesen' => function (): void {
        $invalidInputs = [
            fn () => new Person(' ', 20),
            fn () => new Game(0, 'Titel', 'Switch', 0, 1, 4),
            fn () => new Game(1, ' ', 'Switch', 0, 1, 4),
            fn () => new Game(1, 'Titel', ' ', 0, 1, 4),
            fn () => new Game(1, 'Titel', 'Switch', -1, 1, 4),
            fn () => new Game(1, 'Titel', 'Switch', 7, 1, 4),
            fn () => new Game(1, 'Titel', 'Switch', 0, 0, 4),
            fn () => new Game(1, 'Titel', 'Switch', 0, 4, 3),
            fn () => new Shelf(0, 2),
            fn () => new Shelf(1, 0),
            fn () => new Shelf(1, -1),
            fn () => createStore()->searchGames(' '),
        ];

        foreach ($invalidInputs as $invalidInput) {
            expectException(InvalidArgumentException::class, $invalidInput);
        }
    },
];

$failed = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        echo '[OK] ' . $name . "\n";
    } catch (Throwable $exception) {
        $failed++;
        // Auch nach einem Fehlschlag die übrigen Tests ausführen und alle Fehler sammeln.
        fwrite(STDERR, '[FEHLER] ' . $name . ': ' . $exception->getMessage() . "\n");
    }
}

printf("\n%d Tests, %d fehlgeschlagen.\n", count($tests), $failed);
exit($failed === 0 ? 0 : 1);
