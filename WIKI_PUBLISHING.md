# Publikacja GitHub Wiki

Źródła stron znajdują się w katalogu `wiki/`. GitHub Wiki jest osobnym repozytorium Git i wymaga utworzenia pierwszej strony w interfejsie GitHub oraz uwierzytelnienia z prawem zapisu.

## Pierwsza publikacja

1. Otwórz <https://github.com/kkkonrad/magento2-allegro/wiki>.
2. Kliknij **Create the first page**, pozostaw tytuł `Home` i zapisz stronę. Ten krok inicjalizuje repozytorium `magento2-allegro.wiki.git`.
3. Sklonuj repozytorium Wiki obok repozytorium modułu:

   ```bash
   git clone https://github.com/kkkonrad/magento2-allegro.wiki.git
   ```

4. Skopiuj zawartość katalogu `wiki/` do klonu Wiki, zastępując roboczą stronę `Home.md`.
5. Zatwierdź i wypchnij zmiany:

   ```bash
   git add .
   git commit -m "Add project wiki"
   git push origin master
   ```

Jeżeli GitHub utworzy inną domyślną gałąź, użyj jej nazwy zamiast `master`. Do zapisu przez HTTPS użyj tokenu lub credential managera; nie umieszczaj tokenu w adresie remote ani w plikach projektu.

## Aktualizacja

Katalog `wiki/` w repozytorium głównym jest źródłem wersjonowanym razem z kodem. Po zmianie skopiuj zaktualizowane pliki do klonu `magento2-allegro.wiki.git`, sprawdź linki, a następnie wykonaj commit i push w repozytorium Wiki.
