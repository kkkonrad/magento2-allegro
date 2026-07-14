# Raport weryfikacji implementacji — Macopedia_Allegro

Data ostatniej aktualizacji: 2026-07-14. Środowisko: Magento 2.4.8-p5, PHP 8.2, Allegro Sandbox.

## Wynik

Pełny scenariusz E2E AC-01–AC-08 zakończył się wynikiem PASS. Oferta przeszła cykl: katalog → szkic → publikacja → synchronizacja ceny i ilości → zakup → import zamówienia → ponowienie bez duplikatu → status → przesyłka → zakończenie.

## Bramka techniczna

- `setup:upgrade --keep-generated` i `setup:db:status`: PASS.
- `setup:di:compile`: PASS.
- PHPUnit: 69 testów jednostkowych, 196 asercji: PASS.
- lint PHP całego modułu i `git diff --check`: PASS.
- kontrola `dd`, `die`, `exit`, `var_dump` i `print_r` w kodzie produkcyjnym: PASS.
- logi po finalnym buildzie: brak nowych błędów krytycznych i brak markerów sekretów. `exception.log` zachowuje wpisy z godz. 11:28 UTC po celowo nieudanych próbach uruchomienia instalatora testowego; przyczyny zostały usunięte, a późniejszy test integracyjny przeszedł.
- CI obejmuje PHP 8.1/8.2/8.3 oraz osobny job integracyjny na runnerze Magento.
- test integracyjny na czystej, izolowanej bazie: 1 test, 10 asercji — PASS. Sprawdza rozwiązywanie usług, timeouty, tabele operacyjne i unikalność rezerwacji.

Klient API ma konfigurowalne timeouty, ograniczony retry z backoff/jitter i domenowe wyjątki dla autoryzacji, walidacji, 404, rate limitu, transportu oraz pozostałych błędów odpowiedzi. OAuth używa ochrony `state`, oddzielnych tokenów środowiskowych i blokady odświeżania. Sesja administratora dla `state` jest ładowana leniwie, dzięki czemu komendy CLI i instalator Magento nie inicjalizują jej bez potrzeby.

## Wyniki E2E Sandbox

| AC | Wynik | Dowód / uwagi |
| --- | --- | --- |
| AC-01 | PASS | Konto sprzedawcy połączone przez OAuth; odczyty i zapisy API oraz refresh tokena działają. Tokeny Sandbox i Production są rozdzielone. |
| AC-02 | PASS | GTIN `9506000140445` powiązano z produktem katalogowym `00913892-b563-42e6-b343-95ba2935ba2a`, kategoria `260969`. |
| AC-03 | PASS | Oferta `7781864283` została powiązana z produktem Magento `2041`, zwalidowana, opublikowana i finalnie zakończona. Dedykowane mapowanie zawiera środowisko Sandbox, konto sprzedawcy i produkt katalogowy. |
| AC-04 | PASS | Kontrolowany błędny POST zwrócił HTTP 422 jako `ValidationException`, request ID `4d70cdbe78ac1fb8`, kod `ConstraintViolationException.NotBlank`, ścieżka `name`. |
| AC-05 | PASS | Zmiana ceny i ilości została przetworzona przez MySQL MQ. Allegro potwierdziło cenę `12.34` i ilość `3`; konsument odczytał aktualne dane i zdeduplikował starsze komunikaty. |
| AC-06 | PASS | Zakup utworzył checkout form `c6d5ac00-7f71-11f1-a02b-87d6edb9dac3`. Po korekcie rozpoznawania regionu importer utworzył zamówienie Magento `000000004`, wartość `22.83`, dostawa `10.49`, jedna pozycja. |
| AC-07 | PASS | Pierwsza próba kontrolowanie zapisała błąd braku `regionId`; retry zakończył import. Wielokrotne ponowienie tego samego checkout form nadal pozostawiło dokładnie jedno zamówienie Magento. |
| AC-08 | PASS | Statusy `processing → PROCESSING` i `complete → SENT` przeszły przez MQ. Przesyłka Magento `000000005`, tracking `E2E-20260714-0004`, przewoźnik `OTHER`, została potwierdzona przez endpoint przesyłek checkout form. |

Końcowy odczyt oferty potwierdził status `ENDED`, cenę `12.34` i ilość `2` po zakupie.

## Zrealizowane elementy planu

- bezpieczny klient API, timeouty, retry, wyjątki domenowe i logowanie bez sekretów;
- OAuth z CSRF `state`, blokadą refreshu i statusem połączenia;
- GTIN/EAN, konfigurowalna marka (`manufacturer` domyślnie), GPSR i dane wymagane przez Product Offer API;
- wyszukiwanie produktu katalogowego po nazwie z listą kandydatów i wyborem GTIN; test Sandbox potwierdził odnalezienie właściwego produktu oraz GTIN;
- jeden serwis zapisu oferty, walidator, builder payloadu i wyłączenie starej ścieżki `/sale/offers`;
- dedykowane mapowanie produkt–oferta–konto–środowisko, reconciliation i walidacja ręcznego mapowania;
- bezpieczne czyszczenie mapowań z `--dry-run` i usuwaniem wyłącznie po potwierdzonym 404;
- idempotentne MQ dla ceny, ilości, statusu i przesyłki oraz rejestr operacji po wyczerpaniu prób;
- trwały stan importu checkout form, blokada, retry, bezpieczne błędy i ochrona przed duplikatem zamówienia;
- rozpoznawanie regionu adresu na podstawie danych kraju i polskiego kodu pocztowego;
- blokady cronów, metryki ostatniego uruchomienia i komenda `macopedia:allegro:health`;
- testy jednostkowe, test infrastruktury Magento, CI, dokumentacja operacyjna i zasady rollbacku.

## Uwagi przed wdrożeniem produkcyjnym

1. W produkcji skonfigurować osobne dane aplikacji Allegro i ponownie wykonać OAuth; nie kopiować tokenów Sandbox.
2. Włączać import, synchronizacje i crony etapami, obserwując `macopedia:allegro:health` oraz logi przez pełny cykl operacyjny.
3. Zachować nowe tabele podczas rollbacku; najpierw wyłączyć automatyzacje, potem cofnąć kod do wersji zgodnej ze schematem.
