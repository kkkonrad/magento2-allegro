# Zamówienia, statusy i przesyłki

## Bezpieczne uruchomienie importu

1. Powiąż wszystkie aktywne oferty z właściwymi produktami Magento.
2. Skonfiguruj store view, dostawę, płatność i statusy.
3. Włącz import na Sandboxie.
4. Kup ofertę z osobnego konta kupującego Sandbox.
5. Sprawdź, czy powstało dokładnie jedno zamówienie Magento.
6. Ponów import tego samego checkout form i potwierdź brak duplikatu.
7. Zmień status i dodaj przesyłkę z numerem śledzenia.
8. Sprawdź rezultat w Allegro oraz health-check.

API sprzedawcy nie zastępuje zakupu wykonywanego przez konto kupującego.

## Idempotencja

Checkout form ID jest kluczem idempotencji. Ponowienie zdarzenia, crona lub komendy nie powinno utworzyć drugiego zamówienia Magento. Stan importu przyjmuje między innymi wartości `new`, `processing`, `imported`, `retryable` i `failed`.

## Rezerwacje i błędy

Nieopłacony checkout może utworzyć rezerwację, jeśli obsługa rezerwacji jest włączona. Po płatności rezerwacja jest usuwana, a moduł tworzy zamówienie.

Błędy importu są widoczne w panelu **Sprzedaż → Allegro zamówienia z błędami**. Rekord przechowuje przyczynę oraz liczbę prób i może zostać ręcznie ponowiony.

## Komendy importu

```bash
bin/magento macopedia:allegro:order-import -c CHECKOUT_FORM_ID
bin/magento macopedia:allegro:orders-import
bin/magento macopedia:allegro:orders-with-errors-import
```

## Statusy

Mapowanie statusów Magento → Allegro konfiguruje się w panelu modułu, np. `processing → PROCESSING` oraz `complete → SENT`. Wysyłkę statusów włącz dopiero po sprawdzeniu mapowań na Sandboxie.

## Przesyłki

Na przesyłce zamówienia Magento dodaj przewoźnika i numer śledzenia. Moduł może przekazać do Allegro jedną lub wiele przesyłek wraz z ilościami produktów. Funkcja wymaga aktywnej synchronizacji przesyłek i działającego konsumenta kolejki.
