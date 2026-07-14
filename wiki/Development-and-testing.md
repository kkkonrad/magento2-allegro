# Rozwój i testowanie

## Dokumenty projektowe

W repozytorium głównym znajdują się:

- [`SPECYFIKACJA_WYMAGAN.md`](https://github.com/kkkonrad/magento2-allegro/blob/main/SPECYFIKACJA_WYMAGAN.md)
- [`PLAN_IMPLEMENTACJI.md`](https://github.com/kkkonrad/magento2-allegro/blob/main/PLAN_IMPLEMENTACJI.md)
- [`RAPORT_WERYFIKACJI.md`](https://github.com/kkkonrad/magento2-allegro/blob/main/RAPORT_WERYFIKACJI.md)
- [`INSTRUKCJA_URUCHOMIENIA.md`](https://github.com/kkkonrad/magento2-allegro/blob/main/INSTRUKCJA_URUCHOMIENIA.md)

## Testy lokalne

Po zmianach uruchom testy jednostkowe/integracyjne przewidziane w repozytorium, kontrolę standardu kodu oraz kompilację DI właściwą dla wersji Magento. Minimalna kontrola działającej instalacji:

```bash
bin/magento setup:di:compile
bin/magento macopedia:allegro:health
```

## Testy E2E na Sandboxie

Scenariusz akceptacyjny powinien objąć:

1. OAuth i odświeżenie tokena;
2. odczyt cenników i warunków konta;
3. wyszukanie produktu po GTIN oraz nazwie;
4. utworzenie szkicu i publikację oferty;
5. zakup z konta kupującego Sandbox;
6. idempotentny import zamówienia;
7. synchronizację statusu;
8. wysłanie numeru przesyłki;
9. synchronizację ceny i stanu;
10. zachowanie retry przy błędach przejściowych.

## Zasady zmian

- Nie zapisuj sekretów ani danych E2E w repozytorium.
- Zachowuj idempotencję operacji importu i synchronizacji.
- Nie usuwaj mapowań po timeoutach lub niejednoznacznych błędach API.
- Loguj request ID i kontekst techniczny bez Authorization oraz danych osobowych.
- Każdą zmianę payloadu oferty sprawdzaj dla produktu nowego i istniejącego w Katalogu Allegro.

## Zgłoszenia

Błędy i propozycje rozwoju można zgłaszać w [GitHub Issues](https://github.com/kkkonrad/magento2-allegro/issues). Zgłoszenie nie może zawierać Client Secret, tokenów OAuth ani niezmaskowanych danych klienta.
