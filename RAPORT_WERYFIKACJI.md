# Raport weryfikacji implementacji — Macopedia_Allegro

## Zweryfikowane technicznie

- Magento 2.4.8-p5: `setup:upgrade`, `setup:db:status` i `setup:di:compile` zakończone sukcesem.
- PHP 8.2: lint całego modułu oraz `git diff --check` bez błędów.
- PHPUnit: 32 testy jednostkowe, 73 asercje, wynik PASS.
- Zarejestrowane konsumery dla MySQL MQ i RabbitMQ: stan/cena, status zamówienia, przesyłka.
- Rejestr retry ma backoff, limit pięciu prób, status `dead`, komunikat systemowy i komendę diagnostyczną.
- Brak martwych operacji asynchronicznych w aktualnym środowisku: `macopedia:allegro:async-failures --limit=10`.
- Odczyt katalogowy Sandbox wykonano komendą `macopedia:allegro:product:search 5901234123457`; znaleziono produkt katalogowy `822e8799-71a4-4aae-a2e5-b4cfc1d367ae`.

## Wyniki E2E Sandbox — 2026-07-13

| AC | Wynik | Dowód / uwagi |
| --- | --- | --- |
| AC-01 | PASS części technicznej | Nowy OAuth działa; uwierzytelnione wywołania katalogu, dostaw i ofert zakończyły się sukcesem. |
| AC-02 | PASS | GTIN `5901234123457` zwrócił ID produktu i kategorię `64509`. |
| AC-03 | PASS dla draftu i mapowania | Utworzono nieaktywny draft `7781864283`; na produkcie Magento `2041` zapisano offer ID i catalog product ID. |
| AC-04 | PASS części obsługi błędów | Sandbox zwrócił strukturalne błędy walidacji bez ujawnienia tokenu ani Client Secret. Pełny test komunikatu w panelu pozostaje do wykonania. |

Aktualizacja draftu przez `PATCH /sale/product-offers/7781864283` zakończyła się sukcesem: cena zmieniła się z `10` na `11`, a status pozostał `INACTIVE`.

Test wykrył i pozwolił poprawić dwa błędy payloadu:

1. `images` endpointu product-offer musi być tablicą URL-i, a nie tablicą obiektów `{url: ...}`.
2. Parametry z `options.describesProduct=true` należą do produktu katalogowego i nie mogą być wysyłane w głównej sekcji `parameters` oferty. Formularz filtruje je teraz na podstawie definicji API.

Draft pozostaje niepublikowalny z przyczyn danych/konta Sandbox, a nie błędu transportu modułu. API zgłasza między innymi testowy GTIN spoza GS1, brak wymaganych warunków zwrotów/reklamacji, brak responsible producer oraz ograniczenia wybranego cennika One Fulfillment. Sandbox zwraca wyłącznie cenniki One Fulfillment, a konto nie ma aktywnej usługi i wymaganej konfiguracji magazynowej. Allegro ustawiło z tego powodu `stock.available=null` mimo przesłanej ilości `5`.

## Pozostałe scenariusze E2E do wykonania w Sandbox

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
