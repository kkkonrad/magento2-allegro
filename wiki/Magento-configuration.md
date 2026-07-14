# Konfiguracja Magento

Ustawienia znajdują się w **Sklepy → Konfiguracja → Allegro → Configuration**.

## OAuth

1. W sekcji **General** ustaw **Is sandbox account**.
2. Skopiuj **Use following callback** do konfiguracji aplikacji Allegro.
3. W sekcji **Credentials** wpisz Client ID i Client Secret.
4. Zapisz konfigurację.
5. Kliknij **Połącz z kontem Allegro** i zaakceptuj dostęp.
6. Sprawdź identyfikator konta, środowisko, termin tokena i status połączenia.

Zmiana Client ID, Client Secret lub środowiska wymaga ponownej autoryzacji. Client Secret jest szyfrowany przez backend konfiguracji Magento, lecz nadal nie może trafić do repozytorium ani logów.

## Zalecane ustawienia początkowe

Przed pierwszym testem wyłącz:

- import zamówień;
- automatyczną synchronizację stanów i cen;
- wysyłkę statusów oraz numerów przesyłek.

Najpierw potwierdź OAuth, odczyt danych konta i publikację jednego szkicu.

## Origin

Uzupełnij kraj, województwo, kod pocztowy i miasto miejsca wysyłki. Dla Polski województwo jest wymagane. Moduł nie powinien zastępować braków fikcyjnymi wartościami.

## Tworzenie oferty

Zalecane mapowanie atrybutów:

| Dane | Domyślne źródło |
| --- | --- |
| GTIN/EAN | `ean` |
| marka | `manufacturer` |
| opis | rzeczywisty atrybut opisu produktu |
| cena | atrybut ceny używany przez sklep |

Produkt powinien mieć nazwę, opis, cenę, stan i zdjęcie z rolą `Allegro`. Sposób użycia GTIN i marki opisuje strona [[GTIN i Katalog Allegro|Products-GTIN-and-catalog]].

## Dostawa, płatność i zamówienia

- Mapuj nazwy metod dostawy Allegro na aktywne metody Magento.
- Skonfiguruj bezpieczną metodę domyślną.
- Wybierz metodę płatności online oraz pobrania, jeśli jest obsługiwane.
- Wybierz store view dla importowanych zamówień.
- Skonfiguruj statusy nadpłaty/niedopłaty i mapowanie statusów Magento → Allegro.

Po konfiguracji uruchom:

```bash
bin/magento macopedia:allegro:health
```
