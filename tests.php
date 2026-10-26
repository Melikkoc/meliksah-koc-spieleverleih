<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Person.php';
require_once __DIR__ . '/src/Game.php';
require_once __DIR__ . '/src/Shelf.php';
require_once __DIR__ . '/src/GameStore.php';

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

$tests = [
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
    'Unbekannte Spiel-IDs werden bei Suche, Ausleihe und Rückgabe behandelt' => function (): void {
        $store = createStore();
        foreach ([-1, 0, 999] as $id) {
            expectException(OutOfBoundsException::class, fn () => $store->getGameById($id));
            expectException(OutOfBoundsException::class, fn () => $store->borrowGame($id, new Person('Alex', 20)));
            expectException(OutOfBoundsException::class, fn () => $store->returnGame($id));
        }
    },
    'Unbekannte und doppelte Regal-IDs werden behandelt' => function (): void {
        $store = createStore();
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
