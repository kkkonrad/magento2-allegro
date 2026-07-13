# Specyfikacja wymagań — Macopedia_Allegro

## 1. Cel i zakres

Moduł `Macopedia_Allegro` integruje Magento 2 z Allegro REST API. Ma umożliwiać bezpieczną, obserwowalną i odporną na błędy obsługę ofert, zamówień oraz synchronizacji stanów i cen.

Wersja bazowa wspiera Magento 2.4 (pakiet wymaga `magento/framework ^103.0`) oraz PHP 8.1+. Deklaracja kompatybilności z Magento 2.3 nie jest częścią tego zakresu, dopóki zależności pakietu nie zostaną zmienione i przetestowane.

Zakres obejmuje konto produkcyjne Allegro i Sandbox. Sandbox jest wymaganym środowiskiem testów integracyjnych; dane dostępu do niego nie mogą trafić do repozytorium ani logów.

Poza zakresem pierwszej wersji są: obsługa wielu kont sprzedawców w jednej instalacji, warianty wieloproduktowe, masowe wystawianie ofert oraz mapowanie kategorii Magento–Allegro. Mogą zostać dodane jako osobne epiki.

## 2. Role

| Rola | Uprawnienia i cel |
| --- | --- |
| Administrator Magento | Konfiguruje połączenie, mapowania i automatyzacje; inicjuje publikację ofert. |
| Operator sprzedaży | Tworzy, edytuje i publikuje ofertę; obsługuje błędy importu. |
| Proces systemowy | Realizuje crony i konsumuje kolejki do synchronizacji oraz importu. |
| Allegro API | Zewnętrzne źródło katalogu, ofert, zamówień i statusów. |

## 3. Wymagania funkcjonalne

### FR-01. Instalacja i konfiguracja

1. Moduł musi dać się włączyć przez `bin/magento module:enable Macopedia_Allegro` i poprawnie przejść `bin/magento setup:upgrade`.
2. Konfiguracja musi być dostępna dla zakresów default, website i store, zgodnie z definicją w `system.xml`.
3. Administrator musi móc wybrać środowisko Production albo Sandbox.
4. Client ID, Client Secret, access token i refresh token muszą być przechowywane wyłącznie w mechanizmie bezpiecznej konfiguracji Magento. Sekrety nie mogą znaleźć się w kodzie, dokumentacji, eksporcie konfiguracji ani zwykłych logach.
5. Moduł musi prezentować dokładny callback URL wymagany przy rejestracji aplikacji Allegro.

### FR-02. Autoryzacja OAuth

1. Moduł musi obsługiwać Authorization Code Flow dla konta użytkownika Allegro.
2. Połączenie musi zapisywać tokeny dla właściwego zakresu konfiguracji i rozróżniać token Sandbox od produkcyjnego.
3. Refresh tokena musi odbywać się przed wygaśnięciem tokena dostępowego, także przez cron.
4. Błąd autoryzacji musi dawać administratorowi zrozumiały komunikat i nie może ujawnić sekretu ani pełnego tokena.

### FR-03. Katalog Allegro i tworzenie oferty

1. Operator musi móc wyszukać produkt w Katalogu Allegro po EAN z formularza tworzenia oferty.
2. Po wyborze produktu katalogowego formularz musi ustawić jego identyfikator, kategorię i dostępne parametry, pozostawiając operatorowi możliwość ich weryfikacji.
3. Oferta powiązana z produktem katalogowym musi być tworzona przez aktualny endpoint Allegro dla product offer.
4. Payload utworzenia oferty musi zawierać wszystkie wymagane dane: nazwę, cenę z walutą, ilość, lokalizację, produkt katalogowy, kategorię, parametry, cennik dostaw, czas realizacji, fakturę, zdjęcia i opis, jeśli wymagają ich reguły danej kategorii/API.
5. Formularz musi używać jednego, jawnie zdefiniowanego modelu danych. W szczególności pola `delivery_shipping_rates_id`, `delivery_handling_time` i `payments_invoice` muszą zostać zamienione na format oczekiwany przez API; nie wolno zakładać nieistniejących zagnieżdżeń danych.
6. Po sukcesie utworzenia oferty moduł musi atomowo zapisać na produkcie Magento `allegro_offer_id` oraz — dla produktu katalogowego — `allegro_product_id`. Jeśli zapis lokalnego mapowania się nie powiedzie, błąd musi trafić do logu i kolejki naprawczej.
7. Operator musi móc zapisać szkic, edytować ofertę, opublikować ją i zakończyć. Status widoczny w Magento musi odpowiadać statusowi zwróconemu przez Allegro.
8. Przed wysłaniem moduł musi walidować format EAN, wymagane parametry kategorii, cenę dodatnią, ilość nieujemną, poprawność zdjęć oraz kompletność konfiguracji sprzedażowej.

### FR-04. Zarządzanie istniejącymi ofertami

1. Administrator musi móc ręcznie powiązać istniejącą ofertę Allegro z produktem Magento.
2. Moduł musi codziennie lub na żądanie wykrywać mapowania do ofert, które już nie istnieją, i bezpiecznie je usuwać.
3. Odświeżenie oferty nie może usuwać danych, których operator nie edytował, ani nadpisywać danych pochodzących z Allegro pustymi wartościami.

### FR-05. Synchronizacja stanów i cen

1. Zmiana dostępnej ilości produktu powiązanego z ofertą musi opublikować zdarzenie do MQ zamiast wykonywać wywołanie API w cyklu zapisu produktu.
2. Konsument musi aktualizować ilość po kolei, z obsługą ponowień i idempotencją.
3. Synchronizacja cen musi być opcjonalna i respektować skonfigurowaną politykę procentową.
4. Brak mapowania Allegro, wyłączona synchronizacja lub brak ważnego tokena nie mogą blokować zapisu produktu w Magento.

### FR-06. Import i obsługa zamówień

1. Moduł musi cyklicznie pobierać zdarzenia zamówień z Allegro i importować opłacone checkout forms do właściwego store view.
2. Musi mapować produkty po `allegro_offer_id`, dostawę i płatność według konfiguracji oraz zachowywać dane kupującego, płatności, dostawy i wiadomości.
3. Nieopłacone zamówienia muszą opcjonalnie tworzyć rezerwacje; opłacenie musi usuwać właściwą rezerwację i uruchamiać import zamówienia.
4. Import musi być idempotentny: ponowne zdarzenie lub ponowienie nie może utworzyć drugiego zamówienia.
5. Błędne importy muszą trafiać do siatki błędów z checkout form ID, przyczyną, liczbą prób oraz datami pierwszej i ostatniej próby. Operator musi móc ponowić import wybranych rekordów.

### FR-07. Statusy i przesyłki

1. Zmiana skonfigurowanego statusu Magento dla zamówienia Allegro musi aktualizować status realizacji w Allegro.
2. Dodanie numeru śledzenia w Magento musi opcjonalnie wysyłać dane przewoźnika i pozycje przesyłki do Allegro.
3. Błędy wysłania statusu lub numeru przesyłki muszą być ponawialne i nie mogą blokować zapisu zamówienia albo przesyłki.

### FR-08. Zadania cykliczne i kolejki

1. Crony importu, ponowień importu, odświeżania tokena, czyszczenia rezerwacji i czyszczenia mapowań muszą być niezależnie włączalne w konfiguracji.
2. Moduł musi wspierać RabbitMQ i MySQL MQ oraz dokumentować kompletną konfigurację obu wariantów.
3. Każdy konsument musi mieć określone: kolejkę, retry/backoff, maksymalną liczbę prób i mechanizm obserwacji nieprzetworzonych komunikatów.

### FR-09. Panel administracyjny i CLI

1. Panel administratora musi pokazywać status połączenia, konfigurację i rozpoznawalne błędy integracji.
2. Komendy CLI muszą mieć prefiks `macopedia:allegro:` i być opisane w dokumentacji.
3. Przynajmniej następujące operacje muszą być dostępne z CLI: import pojedynczego zamówienia, import oczekujących błędów, czyszczenie mapowań oraz wyszukanie katalogowe po EAN.

## 4. Wymagania niefunkcjonalne

### NFR-01. Bezpieczeństwo

1. Zabronione są wywołania diagnostyczne przerywające wykonanie, takie jak `dd`, `die`, `exit`, `var_dump` i `print_r`, w kodzie produkcyjnym.
2. Logi HTTP mogą zawierać identyfikator żądania, metodę, endpoint, kod odpowiedzi i zanonimizowany błąd. Nie mogą zawierać `Authorization`, Client Secret, tokenów ani pełnych danych osobowych kupującego.
3. Uprawnienia ACL muszą ograniczać konfigurację, publikację ofert oraz ponawianie importów do odpowiednich ról administracyjnych.

### NFR-02. Niezawodność i obsługa błędów

1. Błąd API musi zostać zamieniony na wyjątek domenowy z kodem HTTP, identyfikatorem żądania i komunikatem możliwym do przedstawienia operatorowi.
2. Repozytoria muszą przechwytywać dokładnie te typy wyjątków, które rzuca warstwa klienta; nie mogą maskować błędów ogólnym komunikatem bez zapisu przyczyny.
3. Operacje zewnętrzne muszą mieć timeout, ograniczone retry i ochronę przed duplikacją po ponowieniu.
4. Brak odpowiedzi lub walidacja 4xx/5xx Allegro nie może powodować błędu krytycznego PHP ani przerwania procesu bez kontrolowanego wyniku.

### NFR-03. Zgodność techniczna i jakość

1. Kod musi być zgodny z PHP 8.1+ i kontraktami Magento 2.4.
2. Interfejsy PHP muszą zachowywać precyzyjne typy; nie wolno usuwać typu parametru tylko po to, aby ominąć błąd danych wejściowych. Należy normalizować dane na granicy formularza/API.
3. Każda zmiana musi przechodzić `php -l`, `git diff --check` oraz odpowiednie testy automatyczne.
4. Kod nie może wprowadzać nowych ostrzeżeń whitespace ani martwych plików demonstracyjnych do katalogu głównego modułu. Przykłady API należy przenieść do `dev/` lub dokumentacji.

### NFR-04. Wydajność

1. Wywołania API nie mogą odbywać się synchronicznie w krytycznych observerach zapisu produktu, zamówienia ani przesyłki.
2. Operacje katalogowe i konfiguracje Allegro mogą być cache’owane z kontrolowanym czasem życia oraz możliwością ręcznego wyczyszczenia.
3. Import i konsumpcja kolejek muszą być mierzalne przez liczbę przetworzonych, ponowionych i błędnych rekordów.

## 5. Kryteria akceptacji wersji pierwszej

| ID | Scenariusz | Warunek akceptacji |
| --- | --- | --- |
| AC-01 | Autoryzacja Sandbox | Administrator łączy konto Sandbox przez OAuth, a moduł potwierdza połączenie bez ujawnienia sekretu. |
| AC-02 | Wyszukiwanie EAN | Wyszukany produkt katalogowy uzupełnia ID, kategorię i parametry formularza. |
| AC-03 | Utworzenie oferty | Kompletna oferta product offer zostaje utworzona w Sandbox, a oba identyfikatory Allegro zapisują się na produkcie Magento. |
| AC-04 | Błąd walidacji API | Odpowiedź 422 pokazuje operatorowi bezpieczny komunikat, zapisuje kontekst w logu i nie powoduje błędu krytycznego PHP. |
| AC-05 | Zmiana ilości | Zmiana ilości w Magento publikuje komunikat MQ, a konsument aktualizuje właściwą ofertę tylko raz. |
| AC-06 | Import zamówienia | Płatne testowe zamówienie Sandbox tworzy jedno zamówienie Magento z prawidłowym produktem, dostawą i płatnością. |
| AC-07 | Ponowienie | Celowo nieudany import jest widoczny w siatce błędów i po naprawie może zostać zaimportowany bez duplikatu. |
| AC-08 | Przesyłka | Dodanie numeru śledzenia do zamówienia Allegro wysyła go do Sandbox i zapisuje wynik operacji. |

## 6. Plan realizacji

### Priorytet P0 — przed testami integracyjnymi

1. Usunąć `dd()` z klienta API i ujednolicić obsługę wyjątków.
2. Naprawić mapowanie formularza do payloadu product offer, w tym dostawę, płatności, opis i zdjęcia.
3. Zapisywać mapowania produktu Magento po powodzeniu publikacji product offer.
4. Dodać testy jednostkowe budowania payloadu i test integracyjny Sandbox dla AC-01–AC-04.

### Priorytet P1 — stabilna operacja

1. Dodać retry/backoff oraz metryki dla kolejek i cronów.
2. Uzupełnić testy importu zamówień, synchronizacji stanów i wysyłki przesyłek.
3. Zaktualizować README: wyjaśnić obsługę Katalogu Allegro, środowisko Sandbox, aktualne komendy i ograniczenia.

### Priorytet P2 — rozwój funkcjonalny

1. Warianty produktów i wielokrotne konta sprzedawców.
2. Masowe wystawianie oraz mapowanie kategorii i atrybutów.
3. Rozszerzone polityki cenowe i monitoring administracyjny.

## 7. Definition of Done

Funkcjonalność uznaje się za gotową, gdy ma zaakceptowane kryterium z sekcji 5, test automatyczny adekwatny do ryzyka, zweryfikowany scenariusz Sandbox (gdy integruje się z Allegro), aktualną dokumentację administratora oraz nie zawiera danych tajnych, wywołań debugujących ani nierozwiązanych błędów krytycznych w logach.
