# Oferty

## Warunki wstępne

Przed utworzeniem oferty sprawdź:

- aktywne połączenie OAuth;
- cennik dostawy;
- warunki zwrotów i reklamacji;
- gwarancję, jeśli dotyczy;
- dane GPSR wymagane dla produktu;
- lokalizację wysyłki;
- poprawne zdjęcie, cenę i stan produktu;
- poprawny GTIN lub świadomie wybrany produkt katalogowy.

## Pierwsza oferta

1. Na produkcie Magento wybierz zdjęcie z rolą `Allegro`.
2. Kliknij **Dodaj na Allegro**.
3. Znajdź produkt katalogowy po GTIN lub nazwie.
4. Zweryfikuj dokładny wariant, UUID, GTIN, kategorię i markę.
5. Wybierz cennik, zwroty, reklamacje i ewentualną gwarancję.
6. Sprawdź dane GPSR; jeśli katalog ich nie ma, wybierz wpis z konta.
7. Uzupełnij czas wysyłki, fakturę, cenę, ilość, opis i parametry kategorii.
8. Zapisz szkic i usuń wszystkie błędy walidacji Allegro.
9. Opublikuj ofertę.

Po zapisaniu produkt Magento zostaje powiązany z identyfikatorem oferty Allegro.

## Istniejące oferty

Przed włączeniem importu zamówień powiąż każdą sprzedawaną ofertę z odpowiednim produktem Magento. Identyfikator oferty można wpisać na edycji produktu w sekcji Allegro.

## Synchronizacja

Po włączeniu odpowiednich opcji zmiany ceny i stanu produktu są przekazywane do powiązanej oferty asynchronicznie. Procentową politykę ceny konfiguruje się wspólnie dla ofert obsługiwanych przez moduł.

## Czyszczenie mapowań

Najpierw wykonaj próbę bez zapisu:

```bash
bin/magento macopedia:allegro:clean-offers-mapping --dry-run --limit 1000
```

Bez `--dry-run` mapowanie jest usuwane tylko po jednoznacznej odpowiedzi 404 z Allegro. Timeout lub błąd przejściowy nie powinien usuwać relacji.

## One Fulfillment

Nie wybieraj cennika One Fulfillment dla zwykłej oferty. Jeśli usługa nie jest aktywna albo brakuje adresu wycofania, Allegro odrzuci publikację. Szczegóły znajdują się na stronie [[Przygotowanie konta Allegro|Allegro-account-setup]].
