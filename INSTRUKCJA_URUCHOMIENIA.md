# Instrukcja uruchomienia modułu Macopedia_Allegro

Dokument opisuje przygotowanie konta Allegro, konfigurację Magento oraz bezpieczną kolejność uruchomienia integracji. Dotyczy zarówno Allegro Sandbox, jak i środowiska produkcyjnego. Dane aplikacji, konto i token OAuth muszą zawsze pochodzić z tego samego środowiska.

## 1. Checklista przed rozpoczęciem

### Po stronie Allegro

| Element | Kiedy wymagany | Rezultat |
| --- | --- | --- |
| Konto sprzedawcy | zawsze | Konto może wystawiać oferty w wybranym środowisku. |
| Aplikacja REST API | zawsze | Dostępne są Client ID, Client Secret i poprawny redirect URI. |
| Cennik dostawy | przed wystawieniem oferty | Cennik jest widoczny na liście w formularzu Magento. |
| Warunki zwrotów | dla sprzedaży wymagającej zwrotów | Polityka jest widoczna w formularzu oferty. |
| Warunki reklamacji | zwykle dla konta firmowego | Polityka jest widoczna w formularzu oferty. |
| Informacje o gwarancji | tylko gdy udzielasz gwarancji lub wymaga jej proces | Można ją wybrać w formularzu. |
| Produkt w Katalogu Allegro | gdy oferta ma być powiązana z produktem | Produkt można znaleźć po GTIN lub nazwie i wybrać jego UUID. |
| Dane GPSR | gdy dotyczą produktu/kategorii | Katalog ma dane bezpieczeństwa albo na koncie istnieje wpis producenta/osoby odpowiedzialnej. |
| One Fulfillment | tylko gdy świadomie używasz tej usługi | Usługa jest aktywna, konto i produkt są przygotowane, a adres wycofania istnieje. |

### Po stronie Magento

| Element | Wymaganie |
| --- | --- |
| Cron Magento | Uruchamiany co minutę przez systemowy crontab. |
| MQ | Działa MySQL MQ albo RabbitMQ oraz konsumenci Allegro. |
| Adres pochodzenia | Kraj, województwo, kod pocztowy i miasto są uzupełnione. |
| Atrybut GTIN | Domyślnie `ean`; wartość ma poprawną cyfrę kontrolną. |
| Atrybut marki | Domyślnie `manufacturer`; używany przy tworzeniu produktu, nie do nadpisywania istniejącego produktu katalogowego. |
| Mapowanie dostawy i płatności | Każda metoda z Allegro ma odpowiednik Magento albo skonfigurowany fallback. |
| Mapowanie ofert | Każda sprzedawana pozycja Allegro wskazuje właściwy produkt Magento. |

## 2. Przygotowanie aplikacji Allegro API

1. Zarejestruj aplikację w odpowiednim panelu:

   - produkcja: <https://apps.developer.allegro.pl/>
   - Sandbox: <https://apps.developer.allegro.pl.allegrosandbox.pl/>

2. W Magento przejdź do **Sklepy → Konfiguracja → Allegro → Configuration → General**.
3. Ustaw **Is sandbox account** przed rozpoczęciem OAuth.
4. Skopiuj wartość pokazaną w polu **Use following callback** i dodaj ją jako redirect URI aplikacji Allegro. URI musi być identyczne, łącznie z protokołem HTTPS, ścieżką panelu i ewentualnym `index.php`.
5. W sekcji **Credentials** wpisz Client ID i Client Secret, zapisz konfigurację, a następnie kliknij **Połącz z kontem Allegro**.
6. Po autoryzacji sprawdź, czy panel pokazuje środowisko, identyfikator konta, termin tokena i status połączenia.

Nie kopiuj tokenów między Sandboxem i produkcją. Zmiana Client ID, Client Secret lub środowiska wymaga ponownego OAuth. Client Secret jest szyfrowany przez backend konfiguracji Magento, ale nie powinien trafiać do repozytorium, ticketów ani logów.

## 3. Co utworzyć w panelu sprzedawcy Allegro

### 3.1. Cennik dostawy

Przed wystawieniem pierwszej oferty utwórz co najmniej jeden cennik dostawy i dodaj do niego faktycznie obsługiwane metody. Moduł pobiera cenniki przez API; nie tworzy ich automatycznie.

Oficjalna instrukcja: [cenniki dostawy — tworzenie i edycja](https://allegro.pl/pomoc/dla-sprzedajacych/cennik-dostawy/cenniki-dostawy-tworzenie-edycja-i-podmiana-B826XYWjvFg).

Po utworzeniu cennika odśwież formularz oferty w Magento. Jeżeli lista jest pusta, sprawdź OAuth i środowisko konta.

### 3.2. Warunki zwrotów, reklamacji i gwarancji

Na koncie sprzedawcy przygotuj:

1. **Warunki zwrotów** — termin, adres i zasady zwrotu.
2. **Warunki reklamacji** — dane i sposób obsługi reklamacji.
3. **Informacje o gwarancji** — tylko jeśli gwarancja ma być oferowana; nie zastępuje warunków reklamacji.

Moduł wyświetla wpisy z konta na listach w formularzu oferty. Brak wymaganej polityki powoduje odrzucenie publikacji przez Allegro. Warunki są osobne dla Sandboxa i produkcji.

Oficjalne strony Allegro:

- [warunki zwrotów](https://allegro.pl/dla-sprzedajacych/warunki-oferty-zwroty-a124GwdXZFA)
- [warunki reklamacji](https://allegro.pl/dla-sprzedajacych/warunki-oferty-reklamacje-vKgeWL5GnHA)
- [informacje o gwarancji](https://allegro.pl/dla-sprzedajacych/warunki-oferty-gwarancje-9dXYn0VeXHM)

### 3.3. GPSR: producent i osoba odpowiedzialna

Nie należy mylić trzech pojęć:

- **Marka** — parametr produktu, np. `Pasieka Sienkiewicz`.
- **Producent odpowiedzialny** — dane podmiotu wymagane przez GPSR.
- **Osoba odpowiedzialna** — podmiot w UE wymagany w odpowiednich przypadkach, m.in. gdy producent jest spoza UE.

Najpierw sprawdź dane wybranego produktu w Katalogu Allegro. Jeśli produkt katalogowy zawiera kompletne `productSafety`, po wskazaniu jego UUID Allegro może zastosować producenta i informacje bezpieczeństwa z katalogu. Jeżeli katalog nie ma tych danych, utwórz prawdziwy wpis producenta lub osoby odpowiedzialnej w panelu sprzedawcy, a następnie wybierz go z listy w formularzu Magento.

Nie wpisuj danych losowych. Sprzedawca odpowiada za prawdziwość danych GPSR. Oficjalne wyjaśnienie obowiązków: [wymagania GPSR dla sprzedających](https://help.allegro.com/pl/sell/a/jakie-obowiazki-naklada-na-ciebie-rozporzadzenie-gpsr-x5xK1MOxac1).

### 3.4. One Fulfillment — tylko jeśli używasz

Zwykła integracja nie wymaga One Fulfillment. Nie wybieraj cennika zaczynającego się od `One Fulfillment`, jeśli konto nie jest aktywne w tej usłudze.

Jeśli świadomie używasz One Fulfillment, przygotuj:

1. aktywną usługę One Fulfillment na koncie;
2. właściwy cennik i warunki sprzedaży dla One Fulfillment;
3. **Adres do wycofania z Magazynu Allegro**;
4. ustawienia VAT wymagane dla oferty;
5. stan oferty `0` po stronie sprzedawcy — zapasem zarządza Magazyn Allegro;
6. przyjęty zapas i pozostałe kroki wymagane przez usługę.

Oficjalna instrukcja: [jak zacząć z One Fulfillment](https://help.allegro.com/pl/sell/a/jak-zaczac-korzystac-z-one-fulfillment-by-allegro-Rdxba35mZcm?marketplaceId=allegro-pl).

## 4. GTIN, Katalog Allegro i marka

### 4.1. Zasady GTIN

GTIN może mieć 8, 12, 13 lub 14 cyfr i musi mieć poprawną cyfrę kontrolną. Kod powinien pochodzić od właściciela marki/GS1. Moduł waliduje format, ale nie może zagwarantować, że kod został prawidłowo nadany.

W formularzu oferty masz dwie możliwości:

1. wpisać GTIN i kliknąć **Szukaj w Allegro**;
2. wpisać co najmniej trzy znaki w **Znajdź GTIN po nazwie produktu**, a następnie świadomie wybrać dokładny wariant.

Sandbox ma osobny, ograniczony katalog. Brak produktu w Sandboxie nie oznacza automatycznie braku produktu na produkcji. Dla fraz ogólnych zacznij od jednego słowa, np. `biurko`, a dopiero później zawężaj wynik.

### 4.2. Źródło prawdy dla marki

Jeśli wyszukiwanie zwróci UUID istniejącego produktu katalogowego, Katalog Allegro jest źródłem prawdy dla marki oraz innych parametrów identyfikujących. Moduł nie wysyła wtedy lokalnego `manufacturer` w `productSet.product.parameters`.

Przykład: jeśli Magento ma markę `6PAK Nutrition`, a wybrany GTIN wskazuje produkt katalogowy marki `Pasieka Sienkiewicz`, nie wolno nadpisywać marki katalogowej. Najpierw sprawdź, czy wybrano właściwy produkt. Jeśli tak, oferta zostanie powiązana z marką z katalogu. Jeśli nie, wybierz inny GTIN/UUID.

Lokalny atrybut marki jest potrzebny przy przepływie tworzenia nowego produktu lub gdy API wymaga danych produktu bez istniejącego UUID. Jego wartość musi odpowiadać słownikowi kategorii Allegro.

## 5. Konfiguracja Magento

Wszystkie ustawienia znajdują się w **Sklepy → Konfiguracja → Allegro → Configuration**.

### 5.1. Zalecany stan początkowy

Przed pierwszym testem pozostaw wyłączone automatyczne akcje biznesowe:

- import zamówień;
- synchronizację stanów i cen;
- wysyłanie statusów i numerów przesyłek.

Najpierw połącz OAuth, sprawdź odczyty API i wystaw jeden szkic. Automatyzacje włączaj etapami.

### 5.2. General i Credentials

- **Is sandbox account** — musi odpowiadać kontu i aplikacji.
- **Token refresh cron enabled** — zalecane `Tak`.
- **API connection timeout** — domyślnie 10 s.
- **API request timeout** — domyślnie 120 s.
- **Client ID / Client Secret** — dane aplikacji z właściwego środowiska.

### 5.3. Origin

Uzupełnij kraj, województwo, kod pocztowy i miasto miejsca wysyłki. Dla Polski województwo jest wymagane. Moduł nie stosuje fikcyjnych wartości domyślnych.

### 5.4. Offer create

Zalecane ustawienia:

- atrybut GTIN/EAN: `ean`;
- atrybut marki: `manufacturer`;
- atrybut opisu: rzeczywisty opis produktu;
- atrybut ceny: atrybut używany przez sklep.

Na produkcie Magento uzupełnij zdjęcie z rolą `Allegro`, nazwę, opis, cenę, stan oraz GTIN, jeśli go znasz.

### 5.5. Dostawa i płatność importowanych zamówień

W sekcji **Delivery** odwzoruj nazwy metod dostawy Allegro na aktywne metody Magento i ustaw metodę domyślną. Nazwa po stronie Allegro musi odpowiadać nazwie otrzymywanej z API.

W sekcji **Payment** wybierz aktywną metodę Magento dla płatności online i — jeśli obsługujesz — dla pobrania.

W sekcji **Order import** wybierz store view, statusy nadpłaty/niedopłaty oraz mapowanie statusów Magento → Allegro, np. `processing → PROCESSING`, `complete → SENT`.

## 6. Kolejka, konsumenci i cron

Moduł obsługuje MySQL MQ i RabbitMQ. Systemowy cron Magento powinien uruchamiać `bin/magento cron:run` co minutę. Konsumenci muszą być uruchamiani przez `cron_consumers_runner`, Supervisor/systemd albo ręcznie.

Konsumenci MySQL MQ:

```text
AllegroApiQueueDb
AllegroOrderStatusQueueDb
AllegroShipmentQueueDb
```

Konsumenci RabbitMQ:

```text
AllegroApiQueue
AllegroOrderStatusQueue
AllegroShipmentQueue
```

Podstawowa kontrola:

```bash
bin/magento macopedia:allegro:health
bin/magento queue:consumers:list
bin/magento cron:run
```

Prawidłowy health-check powinien pokazać `OAuth: connected`, brak martwych operacji i kolejkę, która nie rośnie stale. Operacje `never` są dopuszczalne dla cronów świadomie wyłączonych.

## 7. Pierwsza oferta — zalecana kolejność

1. Otwórz produkt Magento i wybierz zdjęcie z rolą `Allegro`.
2. Kliknij **Dodaj na Allegro**.
3. Znajdź produkt katalogowy po GTIN albo nazwie.
4. Zweryfikuj nazwę, wariant, zdjęcie, GTIN, markę i kategorię wyniku.
5. Wybierz cennik dostawy, zwroty, reklamacje i ewentualną gwarancję.
6. Zweryfikuj GPSR. Jeśli katalog nie ma kompletnych danych, wybierz producenta/osobę odpowiedzialną z list konta.
7. Uzupełnij czas wysyłki, fakturę, cenę, ilość, opis oraz parametry ofertowe.
8. Zapisz szkic i sprawdź komunikaty walidacji Allegro.
9. Opublikuj dopiero po usunięciu wszystkich błędów.

Przy zmianie GTIN w istniejącej ofercie zawsze ponownie wybierz dokładny produkt katalogowy. UUID, kategoria i marka muszą pochodzić z tego samego produktu.

## 8. Import zamówień — bezpieczne uruchomienie

1. Powiąż wszystkie istniejące oferty z produktami Magento.
2. Skonfiguruj store view, dostawę i płatność.
3. Włącz import na Sandboxie.
4. Kup ofertę z osobnego konta kupującego Sandbox. API sprzedawcy nie tworzy zakupu.
5. Sprawdź, czy powstało dokładnie jedno zamówienie Magento.
6. Ponów import tego samego checkout form i potwierdź brak duplikatu.
7. Zmień status oraz dodaj przesyłkę z numerem śledzenia.
8. Sprawdź rezultat w Allegro i `macopedia:allegro:health`.

## 9. Typowe błędy i rozwiązania

| Komunikat / objaw | Przyczyna | Rozwiązanie |
| --- | --- | --- |
| `GTIN does not exist in GS1` | Kod jest testowy, błędny albo nie został nadany dla produktu. | Użyj prawdziwego GTIN właściciela marki. Nie generuj losowego kodu dla produkcji. |
| Produkt katalogowy nie ma wymaganej marki | Katalog lub propozycja produktu nie zawiera wymaganego parametru. | Dla istniejącego produktu wybierz właściwy UUID; dla nowego produktu uzupełnij `manufacturer` zgodnie ze słownikiem kategorii. |
| `PARAMETER_MISMATCH` dla `Marka` | Lokalna marka nie odpowiada produktowi wskazanemu przez GTIN. | Sprawdź wybrany produkt. Dla UUID nie nadpisuj marki katalogowej; aktualna wersja modułu pomija lokalną markę. |
| Brak polityki zwrotów/reklamacji | Wpis nie istnieje na koncie w danym środowisku. | Utwórz warunki w panelu Allegro i odśwież formularz. |
| Brak producenta/osoby odpowiedzialnej | Produkt podlega GPSR, a katalog nie ma kompletnych danych. | Dodaj prawdziwy wpis na koncie Allegro i wybierz go w ofercie. |
| One Fulfillment nie jest aktywny | Wybrano cennik usługi dla nieaktywnego konta. | Wybierz zwykły cennik albo aktywuj i skonfiguruj usługę. |
| Brak adresu wycofania One Fulfillment | Konto nie ma adresu zwrotu produktów z Magazynu Allegro. | Dodaj **Adres do wycofania z Magazynu Allegro**. |
| Wyszukiwanie nazwy zwraca 0 w Sandboxie | Sandbox ma osobny, uboższy katalog albo fraza jest zbyt dokładna. | Użyj krótszej frazy, np. `biurko`; sprawdź też właściwe środowisko. |
| Zamówienie nie importuje się z powodu regionu | Adres nie ma wartości zgodnej z regionem Magento. | Aktualna wersja rozpoznaje polskie województwo z kodu pocztowego; sprawdź konfigurację kraju i regionów Magento. |
| Kolejka rośnie | Konsumenci nie działają albo API stale odrzuca komunikaty. | Uruchom właściwe konsumery, sprawdź health i listę martwych operacji. |
| OAuth disconnected / token expired | Zmieniono dane aplikacji, środowisko albo refresh token wygasł. | Zapisz poprawne dane i wykonaj OAuth ponownie. |

## 10. Checklista gotowości do produkcji

- [ ] Produkcyjna aplikacja Allegro ma dokładny redirect URI.
- [ ] Client Secret nie znajduje się w repozytorium ani logach.
- [ ] Konto produkcyjne ma cennik, zwroty i reklamacje.
- [ ] Dane GPSR są prawdziwe i kompletne dla sprzedawanych produktów.
- [ ] GTIN-y i wybrane UUID produktów zostały ręcznie zweryfikowane.
- [ ] Dostawa, płatność, store view i statusy są zmapowane.
- [ ] Cron i wszystkie wymagane konsumery działają.
- [ ] `bin/magento macopedia:allegro:health` nie pokazuje błędu.
- [ ] Sandbox przeszedł cykl: oferta → zakup → import → retry → status → przesyłka.
- [ ] Automatyzacje będą włączane etapami, z obserwacją logów i kolejki.

## 11. Bezpieczeństwo i rollback

Przed wdrożeniem wykonaj backup bazy oraz eksport konfiguracji bez sekretów. W razie problemów najpierw wyłącz import, synchronizacje i crony modułu, następnie zatrzymaj konsumery Allegro. Cofnięcie kodu nie powinno usuwać tabel modułu ani danych mapowań. Tokeny i Client Secret nie mogą być częścią kopii repozytorium ani artefaktów CI.

