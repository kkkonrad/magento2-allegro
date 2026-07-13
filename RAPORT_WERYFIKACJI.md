# Raport weryfikacji implementacji — Macopedia_Allegro

## Zweryfikowane technicznie

- Magento 2.4.8-p5: `setup:upgrade`, `setup:db:status` i `setup:di:compile` zakończone sukcesem.
- PHP 8.2: lint całego modułu oraz `git diff --check` bez błędów.
- PHPUnit: 23 testy jednostkowe, 58 asercji, wynik PASS.
- Zarejestrowane konsumery dla MySQL MQ i RabbitMQ: stan/cena, status zamówienia, przesyłka.
- Rejestr retry ma backoff, limit pięciu prób, status `dead`, komunikat systemowy i komendę diagnostyczną.
- Brak martwych operacji asynchronicznych w aktualnym środowisku: `macopedia:allegro:async-failures --limit=10`.
- Odczyt katalogowy Sandbox wykonano komendą `macopedia:allegro:product:search 5901234123457`; brak wyniku dla tego EAN nie był błędem połączenia.

## Scenariusze E2E do wykonania w Sandbox

Wymagają one przygotowanego produktu Magento, aktywnego cennika dostaw Allegro oraz osobnego konta kupującego Sandbox. Nie należy wykonywać ich na danych produkcyjnych.

| AC | Scenariusz | Dowód PASS |
| --- | --- | --- |
| AC-01 | OAuth i refresh | aktywny status połączenia po ponownym logowaniu Sandbox |
| AC-02 | Wyszukanie EAN | ID katalogowe, kategoria i parametry w formularzu |
| AC-03 | Product offer | ID oferty i katalogu zapisane na produkcie Magento |
| AC-04 | Błąd 422 | bezpieczny komunikat operatora, request ID w logu, bez sekretów |
| AC-05 | Zmiana ilości | komunikat MQ i pojedyncza aktualizacja oferty |
| AC-06 | Zamówienie | jedno zamówienie Magento po zakupie Sandbox |
| AC-07 | Ponowienie | rekord błędu, naprawa i import bez duplikatu |
| AC-08 | Przesyłka | numer trackingowy widoczny przy checkout form Sandbox |

## Bezpieczny porządek wykonania E2E

1. Ustawić Sandbox i połączyć konto w `Sklepy → Konfiguracja → Allegro`.
2. Utworzyć lub wskazać prosty produkt Magento z poprawnym EAN oraz przygotować cennik dostaw, lokalizację i płatności.
3. Utworzyć nieaktywną ofertę, zweryfikować mapowanie, a następnie opublikować ją.
4. Uruchomić po jednym konsumencie właściwym dla konfiguracji MQ, np. `bin/magento queue:consumers:start AllegroApiQueueDb --max-messages=100`.
5. Wykonać zmianę ilości, zakup testowy, zmianę statusu i utworzenie przesyłki.
6. Zachować jedynie zanonimizowane dowody: ID encji, request ID i czas testu. Nie zapisywać tokenów ani sekretów.
