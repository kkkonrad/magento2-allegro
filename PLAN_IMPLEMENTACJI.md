# Plan implementacji — Macopedia_Allegro

## 1. Cel planu

Plan opisuje realizację wymagań z dokumentu [SPECYFIKACJA_WYMAGAN.md](SPECYFIKACJA_WYMAGAN.md) dla stabilnej wersji integracji Magento 2.4 z Allegro REST API. Kolejność prac minimalizuje ryzyko: najpierw zabezpieczamy klienta API i tworzenie ofert, następnie synchronizację i zamówienia, a na końcu operacyjność oraz wydanie.

Plan zakłada pracę na Allegro Sandbox do testów integracyjnych. Testy automatyczne nie mogą zależeć od dostępności Sandboxa; połączenie z nim stanowi osobną bramkę akceptacyjną.

## 2. Zasady architektoniczne

Docelowy przepływ zależności:

```text
Admin UI / CLI / Cron / Consumer
              |
         Application Service
              |
     Validator + Mapper/Builder
              |
          Repository API
              |
       HTTP Client + OAuth
              |
          Allegro REST API
```

1. Kontrolery, crony, obserwery i konsumenci pozostają cienkie. Orkiestrują przypadek użycia, ale nie budują payloadów API.
2. Dane formularza są normalizowane do jednego DTO przed walidacją i komunikacją z Allegro.
3. Payloady Allegro tworzą dedykowane buildery; nie są składane bezpośrednio w kontrolerze.
4. Repozytoria odpowiadają za semantykę zasobu API, a klient HTTP za transport, nagłówki, serializację, timeouty i błędy.
5. Ponowienia dotyczą wyłącznie błędów przejściowych: problemów sieciowych, HTTP 429 oraz wybranych 5xx. Błędy 4xx wymagające korekty danych nie są automatycznie ponawiane.
6. Zapis w Allegro i Magento nie może być jedną transakcją rozproszoną. Po utworzeniu oferty w Allegro mapowanie lokalne jest zapisywane w transakcji Magento. Niepowodzenie zapisu lokalnego tworzy rekord uzgodnienia, który naprawia osobny proces reconciliation.
7. Wszystkie operacje wywoływane ponownie muszą być idempotentne albo posiadać klucz deduplikacji.

## 3. Strategia realizacji

| Etap | Zakres | Priorytet | Zależności | Szacunek |
| --- | --- | --- | --- | --- |
| 0 | Baseline, test harness i kontrakty | P0 | — | 2–3 dni |
| 1 | Bezpieczeństwo klienta API i błędy | P0 | Etap 0 | 3–4 dni |
| 2 | OAuth i konfiguracja | P0 | Etap 1 | 2–3 dni |
| 3 | Katalog, formularz i payload product offer | P0 | Etapy 1–2 | 6–8 dni |
| 4 | Mapowanie i cykl życia ofert | P0/P1 | Etap 3 | 4–6 dni |
| 5 | Synchronizacja stanów i cen | P1 | Etapy 1 i 4 | 4–6 dni |
| 6 | Import zamówień i rezerwacje | P1 | Etapy 1, 2 i 4 | 6–9 dni |
| 7 | Statusy i przesyłki | P1 | Etapy 1 i 6 | 3–4 dni |
| 8 | Operacyjność, ACL, CLI i dokumentacja | P1 | Etapy 2–7 | 3–5 dni |
| 9 | E2E Sandbox, stabilizacja i wydanie | P0/P1 | Wszystkie wymagane etapy | 4–6 dni |

Łączny szacunek dla jednej osoby: 37–54 dni robocze. Szacunek nie obejmuje funkcji P2 ani czasu oczekiwania na dane i zachowanie zewnętrznego API.

## 4. Etap 0 — baseline i infrastruktura testowa

### IMP-001. Ustalenie punktu odniesienia

Powiązane wymagania: NFR-03.

Zakres:

1. Zapisać wspieraną macierz Magento 2.4.x i PHP 8.1/8.2/8.3 zgodnie z rzeczywistymi ograniczeniami projektu.
2. Uruchomić i zanotować wyniki `php -l`, `git diff --check`, `setup:di:compile` oraz podstawowego uruchomienia panelu.
3. Oddzielić zastane błędy projektu od regresji modułu.
4. Usunąć albo przenieść pliki `example_usage.php`, `fixed_example.php` i `sample.php` do katalogu `dev/examples`, jeżeli nadal są potrzebne.

Rezultat: raport baseline i czyste wejście do dalszych zmian.

### IMP-002. Struktura testów

Powiązane wymagania: NFR-03, AC-01–AC-08.

Zakres:

1. Dodać `Test/Unit`, `Test/Integration` i konfigurację PHPUnit zgodną z Magento.
2. Utworzyć fabryki fixture dla tokena, produktu, oferty, checkout form oraz odpowiedzi API.
3. Dodać mockowalny transport HTTP; testy jednostkowe nie mogą wykonywać połączeń sieciowych.
4. Dodać do CI lint PHP, kontrolę formatowania, testy jednostkowe i — w osobnym jobie — testy integracyjne Magento.

Warunek zakończenia: przykładowy test jednostkowy i integracyjny przechodzą lokalnie oraz w CI.

## 5. Etap 1 — klient API, bezpieczeństwo i błędy

### IMP-101. Usunięcie kodu diagnostycznego

Powiązane wymagania: NFR-01.1, NFR-02.4, AC-04.

Pliki wejściowe:

- `Model/Api/Client.php`
- `Logger/Logger.php`

Zakres:

1. Usunąć `dd()` i wszystkie pozostałe wywołania przerywające wykonanie.
2. Dodać automatyczną kontrolę repozytorium na `dd`, `die`, `var_dump`, `print_r` i podobne konstrukcje.
3. Zapewnić kontrolowany wyjątek dla błędu każdego typu żądania HTTP.

Testy: odpowiedzi 400, 401, 403, 422, 429, 500, timeout i brak poprawnego JSON.

### IMP-102. Jedna hierarchia wyjątków

Powiązane wymagania: NFR-02.1–NFR-02.4.

Zakres:

1. Zastąpić zduplikowane `Model\Api\ClientException` i `Model\Api\Client\ClientException` jedną hierarchią wyjątków domenowych.
2. Wprowadzić co najmniej: `AuthenticationException`, `ValidationException`, `RateLimitException`, `TransportException`, `ApiResponseException` i `NotFoundException`.
3. Wyjątek API powinien przenosić kod HTTP, bezpieczny komunikat, opcjonalny kod Allegro, ścieżkę błędnego pola i request ID.
4. Poprawić wszystkie repozytoria, aby łapały właściwe typy i zachowywały poprzedni wyjątek.

Testy: mapowanie odpowiedzi transportu na wyjątki oraz zachowanie pełnej przyczyny w logu.

### IMP-103. Bezpieczne logowanie i retry

Powiązane wymagania: NFR-01.2, NFR-02.3, NFR-04.3.

Zakres:

1. Dodać redaktor nagłówków i pól tajnych przed logowaniem requestu/response.
2. Logować request ID, metodę, endpoint, status, czas odpowiedzi i typ błędu.
3. Nie logować tokenów, Client Secret, danych autoryzacyjnych ani pełnych danych kupującego.
4. Dodać konfigurowalny timeout połączenia i odpowiedzi.
5. Dodać retry z exponential backoff i jitter dla timeoutów, 429 i wskazanych 5xx; uwzględnić `Retry-After`.

Warunek zakończenia: AC-04 przechodzi na mocku API, a log nie zawiera żadnej wartości tajnej z fixture.

## 6. Etap 2 — OAuth i konfiguracja

### IMP-201. Konfiguracja środowiskowa i sekrety

Powiązane wymagania: FR-01, NFR-01.

Zakres:

1. Zweryfikować scope konfiguracji dla Client ID, Client Secret i tokenów.
2. Ustalić jednoznaczny mechanizm szyfrowania sekretów przez backend model Magento.
3. Oddzielić tokeny Production i Sandbox tak, aby zmiana środowiska nie użyła tokena z drugiego systemu.
4. Dodać status konfiguracji bez wyświetlania sekretów.
5. Zweryfikować callback URL z routingiem adminhtml i generować go na podstawie konfiguracji backend URL.

### IMP-202. Pełny cykl tokena

Powiązane wymagania: FR-02, AC-01.

Zakres:

1. Zweryfikować Authorization Code Flow, parametr `state` i ochronę przed CSRF.
2. Zapisywać datę wygaśnięcia access tokena.
3. Odświeżać token z bezpiecznym marginesem czasowym oraz blokadą zapobiegającą równoległym odświeżeniom.
4. Po zmianie Client ID, Client Secret albo środowiska unieważniać stare powiązanie lokalne.
5. Dodać akcję „Rozłącz konto” i czytelny status połączenia.

Testy: poprawne połączenie, błędny `state`, odrzucony kod, wygasły refresh token i równoległy refresh.

## 7. Etap 3 — Katalog Allegro i product offer

### IMP-301. DTO formularza i normalizacja danych

Powiązane wymagania: FR-03.1–FR-03.5, NFR-03.2.

Zakres:

1. Wprowadzić DTO, np. `OfferSaveRequest`, zawierające jawnie typowane pola formularza.
2. Dodać `OfferFormDataMapper`, który normalizuje płaskie dane UI do DTO.
3. Ujednolicić nazwy `product` (Magento product ID) oraz `product_id` (Allegro catalog product ID), aby nie mogły zostać pomylone.
4. Przywrócić precyzyjne typy tablic parametrów i normalizować skalary na granicy formularza.
5. Ujednolicić strukturę parametrów `values`, `valuesIds` i `rangeValue`.

Testy: mapowanie kompletnego formularza, brak pól opcjonalnych, parametry jedno- i wielowartościowe oraz niepoprawny typ wejściowy.

### IMP-302. Wyszukiwanie EAN i wypełnianie formularza

Powiązane wymagania: FR-03.1–FR-03.2, AC-02.

Pliki wejściowe:

- `Console/Command/SearchProductByEanCommand.php`
- `Model/Api/ProductCatalogRepository.php`
- `view/adminhtml/web/js/allegro_offer/form/field/ean.js`

Zakres:

1. Walidować i kodować EAN przed wykonaniem requestu.
2. Obsłużyć brak wyniku, wiele wyników, timeout i błąd autoryzacji.
3. Usunąć produkcyjne `console.log` i oprzeć aktualizację komponentów UI na publicznym API Magento UI Registry.
4. Uzupełniać kategorię i parametry bez pętli aktywnego oczekiwania o stałym limicie czasu; wykorzystać subskrypcję stanu komponentu.
5. Pozostawić operatorowi widoczny wybór produktu i możliwość ponowienia wyszukiwania.

### IMP-303. Builder i walidator payloadu

Powiązane wymagania: FR-03.3–FR-03.8, AC-03–AC-04.

Zakres:

1. Utworzyć `ProductOfferPayloadBuilder` niezależny od kontrolera.
2. Mapować nazwę, `sellingMode`, `stock`, `location`, `productSet`, kategorię, parametry, dostawę, fakturę, zdjęcia, opis, after-sales services i publikację.
3. Ujednolicić wartość języka z aktualnym kontraktem API; nie utrzymywać równolegle `pl` i `pl-PL` bez uzasadnienia endpointu.
4. Utworzyć `ProductOfferValidator` i zwracać błędy przypisane do pól formularza.
5. Usunąć domyślne dane biznesowe typu „Warszawa” i „00-001”; brak konfiguracji ma być błędem walidacji.
6. Nie utrzymywać ręcznej listy dozwolonych parametrów kategorii jako źródła prawdy; korzystać z definicji zwróconych przez API i cache.

Testy jednostkowe: snapshot/array assertions pełnego payloadu oraz warianty brakujących pól. Test kontraktowy: request zgodny z fixture bieżącego OpenAPI.

### IMP-304. Cienki przypadek użycia publikacji

Powiązane wymagania: FR-03.3–FR-03.8.

Zakres:

1. Utworzyć `CreateProductOfferService` i `UpdateProductOfferService`.
2. Kontroler `Offer/Save` ma jedynie pobrać dane, wywołać usługę i zbudować wynik/komunikat.
3. Rozdzielić tworzenie szkicu od publikacji; nie wymuszać `ACTIVE` w każdym utworzeniu.
4. Ujednolicić starszą ścieżkę `/sale/offers` z nową ścieżką product offer albo jawnie oznaczyć starą jako legacy i ograniczyć jej użycie.

Warunek zakończenia: payload jest deterministyczny, kontroler nie zna struktury JSON Allegro, a testy jednostkowe nie wymagają Magento Object Manager.

## 8. Etap 4 — mapowanie i cykl życia ofert

### IMP-401. Trwałe mapowanie produktu i oferty

Powiązane wymagania: FR-03.6, FR-04.1, AC-03.

Zakres:

1. Po sukcesie API zapisać `allegro_offer_id` i `allegro_product_id` w jednej lokalnej transakcji.
2. Dodać unikalność `allegro_offer_id` w skali produktów lub dedykowanej tabeli mapowań.
3. Zapisać środowisko, seller/account ID i datę synchronizacji, aby mapowanie Sandbox nie zostało użyte na produkcji.
4. W przypadku błędu zapisu utworzyć rekord reconciliation z offer ID, product ID i przyczyną.
5. Dodać cron oraz CLI do ponowienia uzgodnienia.

Testy: sukces, duplikat offer ID, rollback lokalny, wpis reconciliation i skuteczne ponowienie.

### IMP-402. Edycja, publikacja i zakończenie

Powiązane wymagania: FR-03.7, FR-04.3.

Zakres:

1. Rozdzielić komendy zapisu danych oferty i zmiany statusu publikacji.
2. Przy edycji stosować jawne merge/patch; puste pole nie może przypadkowo usunąć danych Allegro.
3. Po każdej operacji zapisywać status faktycznie zwrócony przez API.
4. Dodać obsługę konfliktu wersji lub równoległej edycji, jeśli endpoint udostępnia odpowiedni mechanizm.

### IMP-403. Czyszczenie i ręczne mapowanie

Powiązane wymagania: FR-04.1–FR-04.2.

Zakres:

1. Zweryfikować offer ID przed ręcznym zapisaniem mapowania.
2. Czyszczenie ma usuwać mapowanie wyłącznie po jednoznacznym 404 z właściwego środowiska; timeout nie jest dowodem usunięcia oferty.
3. Dodać dry-run i raport do komendy czyszczącej.

## 9. Etap 5 — synchronizacja stanów i cen

### IMP-501. Publikacja zdarzeń MQ

Powiązane wymagania: FR-05.1, FR-05.4, NFR-04.1.

Zakres:

1. Observer/plugin zapisu ma publikować mały komunikat zawierający product ID, offer ID, store/seller context, typ zmiany i correlation ID.
2. Publikacja nie może blokować zapisu produktu przy wyłączonej integracji lub braku mapowania.
3. Ograniczyć duplikaty zdarzeń powstające z jednego zapisu produktu.

### IMP-502. Idempotentny konsument

Powiązane wymagania: FR-05.2–FR-05.3, FR-08.2–FR-08.3, AC-05.

Zakres:

1. Konsument pobiera aktualny stan/cenę z Magento w chwili obsługi, a nie ufa starej wartości z komunikatu.
2. Dodać klucz deduplikacji i zapis ostatniej zsynchronizowanej wartości.
3. Zaimplementować retry/backoff, maksymalną liczbę prób i kolejkę błędów lub tabelę failed messages.
4. Obsłużyć RabbitMQ i MySQL MQ tym samym kontraktem wiadomości.
5. Zmierzyć success/retry/failure oraz czas obsługi.

Testy: duplikat komunikatu, kolejność zmian, oferta usunięta, 429, timeout i wyłączona synchronizacja.

## 10. Etap 6 — import zamówień i rezerwacje

### IMP-601. Idempotencja importu

Powiązane wymagania: FR-06.1, FR-06.4, AC-06–AC-07.

Zakres:

1. Ustalić checkout form ID jako klucz idempotencji.
2. Dodać unikalny indeks lub rejestr importów chroniący przed równoległym utworzeniem dwóch zamówień.
3. Wprowadzić statusy przetwarzania: `new`, `processing`, `imported`, `failed`, `retryable`.
4. Użyć blokady/claim rekordu na czas importu.

### IMP-602. Mapowanie i budowa zamówienia

Powiązane wymagania: FR-06.2–FR-06.3, AC-06.

Zakres:

1. Zweryfikować mapowanie każdej pozycji po offer ID przed rozpoczęciem zapisu zamówienia.
2. Walidować store view, walutę, podatki, ceny, ilości, metodę dostawy i płatności.
3. Zapisywać dane kupującego zgodnie z zasadą minimalizacji danych.
4. Emitować udokumentowany event rozszerzający przed zapisem quote.
5. Objąć lokalne tworzenie quote/order transakcją i kontrolowanym rollbackiem.

### IMP-603. Rezerwacje i błędy importu

Powiązane wymagania: FR-06.3, FR-06.5, AC-07.

Zakres:

1. Rezerwacja dla nieopłaconego checkout form musi być unikalna i idempotentna.
2. Opłacenie usuwa dokładnie właściwą rezerwację po skutecznym przejściu do importu.
3. Klasyfikować błędy na trwałe i ponawialne.
4. Siatka błędów ma pokazywać bezpieczny komunikat, liczbę prób i terminy.
5. Ręczne ponowienie musi używać tego samego serwisu aplikacyjnego co cron/CLI.

Testy integracyjne: płatne i nieopłacone zamówienie, duplikat eventu, brak mapowania produktu, błąd dostawy, ponowienie i równoległy import.

## 11. Etap 7 — statusy i przesyłki

### IMP-701. Aktualizacja statusu

Powiązane wymagania: FR-07.1, FR-07.3.

Zakres:

1. Oddzielić wykrycie zmiany statusu od wywołania Allegro przez kolejkę.
2. Zapobiegać pętli synchronizacji i wielokrotnemu wysłaniu tego samego statusu.
3. Walidować mapowanie statusów przed publikacją komunikatu.

### IMP-702. Numery przesyłek

Powiązane wymagania: FR-07.2–FR-07.3, AC-08.

Zakres:

1. Znormalizować przewoźnika, tracking number i ilości pozycji.
2. Wysyłać dane asynchronicznie z kluczem idempotencji shipment/tracking.
3. Zapisywać rezultat i udostępnić ponowienie błędnej wysyłki.

Testy: jedna i wiele przesyłek, ponowiony observer, nieznany przewoźnik, częściowa przesyłka i timeout.

## 12. Etap 8 — operacyjność, ACL, CLI i dokumentacja

### IMP-801. Crony, blokady i monitoring

Powiązane wymagania: FR-08, NFR-04.3.

Zakres:

1. Dodać osobne przełączniki dla każdego crona.
2. Zabezpieczyć zadania przed równoległym wykonaniem.
3. Raportować czas ostatniego sukcesu, liczbę przetworzonych rekordów, retry i błędy.
4. Dodać mechanizm identyfikacji zaległych kolejek i nieodświeżonego tokena.

### IMP-802. Panel i ACL

Powiązane wymagania: FR-09.1, NFR-01.3.

Zakres:

1. Rozdzielić ACL konfiguracji, ofert, błędów importu i operacji ponawiania.
2. Pokazać stan połączenia, środowisko, konto i czas ostatnich poprawnych synchronizacji.
3. Komunikaty UI muszą rozróżniać błąd danych, autoryzacji, limitu i awarii tymczasowej.

### IMP-803. CLI i README

Powiązane wymagania: FR-08.2, FR-09.2–FR-09.3.

Zakres:

1. Ujednolicić prefiks wszystkich komend na `macopedia:allegro:`.
2. Dodać sensowne kody wyjścia oraz tryby `--dry-run`, `--limit` i `--verbose`, gdzie mają zastosowanie.
3. Zaktualizować README o instalację, OAuth, Sandbox, crony, kolejki, komendy, Katalog Allegro i procedury awaryjne.
4. Usunąć nieaktualną deklarację, że wystawianie ofert z Katalogiem jest niemożliwe, dopiero po zaliczeniu AC-03.

## 13. Etap 9 — testy E2E, wydanie i rollback

### IMP-901. Scenariusze Sandbox

Powiązane wymagania: AC-01–AC-08.

Kolejność testów:

1. Połączenie OAuth i refresh tokena.
2. Wyszukanie produktu katalogowego po EAN.
3. Utworzenie szkicu product offer i zapis mapowania.
4. Publikacja, edycja oraz zakończenie oferty.
5. Kontrolowany payload powodujący 422.
6. Synchronizacja ilości i ceny przez MQ.
7. Zakup testowy z osobnego konta Sandbox i import zamówienia.
8. Ponowienie eventu zamówienia bez duplikatu.
9. Dodanie statusu i numeru przesyłki.

Dowody testowe: request ID, ID danych Sandbox, zanonimizowany log, wynik w Magento i Allegro oraz data wykonania. Sekrety i tokeny nie są częścią dowodów.

### IMP-902. Bramka jakości

Wydanie może zostać zaakceptowane, gdy:

1. AC-01–AC-08 mają status PASS albo jawnie zatwierdzone odstępstwo.
2. Testy jednostkowe i integracyjne przechodzą bez błędów.
3. `php -l`, `git diff --check` i `setup:di:compile` kończą się sukcesem.
4. Nie występują nowe błędy krytyczne w `exception.log` i `system.log`.
5. Logi zostały sprawdzone pod kątem sekretów i danych osobowych.
6. README i instrukcja operacyjna odpowiadają wdrażanej wersji.

### IMP-903. Wdrożenie i wycofanie

Zakres:

1. Wdrożyć najpierw na środowisko testowe Magento połączone z Allegro Sandbox.
2. Przed produkcją wykonać backup bazy i eksport konfiguracji bez sekretów.
3. W produkcji początkowo wyłączyć automatyczny import, synchronizację oraz crony; uruchamiać je etapami po weryfikacji OAuth i odczytów API.
4. Monitorować logi, kolejki, crony i liczbę błędnych importów przez minimum jeden pełny cykl operacyjny.
5. Rollback kodu nie może usuwać nowych tabel ani danych. Automatyzacje należy najpierw wyłączyć, następnie cofnąć kod do kompatybilnej wersji.

## 14. Proponowany podział na iteracje

| Iteracja | Zakres | Kryterium wyjścia |
| --- | --- | --- |
| Sprint 1 | Etapy 0–1 | Bezpieczny klient API, test harness, AC-04 na mocku. |
| Sprint 2 | Etap 2 i IMP-301–IMP-302 | Stabilny OAuth Sandbox i wyszukiwanie EAN: AC-01–AC-02. |
| Sprint 3 | IMP-303–IMP-304 i Etap 4 | Utworzenie, mapowanie i cykl życia oferty: AC-03–AC-04. |
| Sprint 4 | Etap 5 | Stabilna synchronizacja MQ: AC-05. |
| Sprint 5 | Etapy 6–7 | Import, retry, status i przesyłka: AC-06–AC-08. |
| Sprint 6 | Etapy 8–9 | Operacyjność, dokumentacja, pełna regresja i release candidate. |

## 15. Rejestr głównych ryzyk

| Ryzyko | Skutek | Ograniczenie |
| --- | --- | --- |
| Zmiana kontraktu Allegro API | Błędy payloadu i publikacji | Test kontraktowy względem bieżącego OpenAPI oraz E2E Sandbox przed wydaniem. |
| Różnice Sandbox–Production | Fałszywe poczucie zgodności | Stopniowe uruchomienie produkcji, feature flags i monitoring. |
| Osierocona oferta po błędzie Magento | Oferta bez lokalnego mapowania | Tabela reconciliation, retry i raport administracyjny. |
| Duplikat zamówienia | Błąd biznesowy i magazynowy | Unikalny checkout form ID, blokada i idempotentny importer. |
| Pętla synchronizacji | Nadmiar requestów i limity API | Deduplikacja, źródło zmiany i zapis ostatniej wartości/statusu. |
| Ujawnienie tokena lub danych kupującego | Incydent bezpieczeństwa | Centralna redakcja logów, testy bezpieczeństwa i minimalizacja danych. |
| Brak działania konsumenta/crona | Narastające rozbieżności | Health status, metryki zaległości i alerty. |

## 16. Sposób śledzenia realizacji

Każde zadanie `IMP-*` powinno zostać osobnym ticketem albo niewielką grupą ticketów. Ticket musi zawierać:

1. odwołanie do FR/NFR/AC;
2. zakres i jawne elementy poza zakresem;
3. listę zmienianych kontraktów lub migracji danych;
4. testy automatyczne i manualne;
5. plan wdrożenia i rollbacku, jeśli zmiana wpływa na dane lub procesy asynchroniczne;
6. dowód spełnienia kryterium akceptacji.

Zadanie może zostać zamknięte dopiero po spełnieniu Definition of Done ze specyfikacji.
