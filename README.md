# Spieleverleih

### 1. Was habe ich umgesetzt, und wie starte ich das Programm?

Ich habe einen Spieleverleih für die Konsole umgesetzt, in dem Spiele auf Regalen mit begrenzter Kapazität verwaltet, gesucht, ausgeliehen und zurückgegeben werden können. Mit `php index.php` starte ich das Programm, mit `php tests.php` führe ich die automatisierten Tests aus.

### 2. Welche Entscheidungen habe ich getroffen, und warum?

Ich habe mich für natives PHP ohne Framework und eine objektorientierte Struktur mit klar getrennten Klassen für Spiele, Personen, Regale, Bestand und Speicherung entschieden, damit der Code überschaubar und leicht nachvollziehbar bleibt. Nach der Umsetzung der eigentlichen Anforderungen hatte ich noch etwas Zeit übrig und habe deshalb aus Spaß und eigenem Interesse das Bearbeiten und Löschen von Spielen sowie die Speicherung als JSON ergänzt.

### 3. Was würde ich als Nächstes machen?

Als Nächstes würde ich Ausleihfristen mit Rückgabedatum und einer Übersicht über überfällige Spiele ergänzen; außerdem wären eine einfache Personenverwaltung für wiederkehrende Kunden und automatische Sicherungskopien der JSON-Datei sinnvoll.
