# Bezpieczeństwo i wdrożenie produkcyjne

## Sekrety

- Client Secret i tokeny OAuth nie mogą znajdować się w Git, CI artefacts, ticketach ani logach.
- Po ujawnieniu sekretu zmień go w Allegro i ponownie wykonaj OAuth.
- Nie kopiuj tokenów między Sandboxem i produkcją.
- Ogranicz dostęp administratorów Magento do konfiguracji i operacji Allegro.

## Checklista produkcyjna

- [ ] Aplikacja produkcyjna ma dokładny redirect URI.
- [ ] Sekrety nie znajdują się w repozytorium ani logach.
- [ ] Konto ma cennik, zwroty i reklamacje.
- [ ] Dane GPSR są prawdziwe i kompletne.
- [ ] GTIN-y oraz UUID produktów zostały ręcznie sprawdzone.
- [ ] Dostawa, płatność, store view i statusy są zmapowane.
- [ ] Cron i konsumenci działają.
- [ ] Health-check nie pokazuje błędu.
- [ ] Sandbox przeszedł cykl oferta → zakup → import → retry → status → przesyłka.
- [ ] Automatyzacje mają być włączane etapami i monitorowane.

## Zalecana kolejność wdrożenia

1. Wdróż kod i wykonaj `setup:upgrade`.
2. Skonfiguruj produkcyjną aplikację oraz OAuth.
3. Uruchom health-check bez automatyzacji biznesowych.
4. Utwórz i zweryfikuj jeden szkic oferty.
5. Włącz import zamówień i obserwuj pierwszy checkout.
6. Włącz kolejno synchronizację stanów, cen, statusów i przesyłek.

## Rollback

Przed wdrożeniem wykonaj backup bazy oraz eksport konfiguracji bez sekretów. W przypadku problemów:

1. wyłącz import, synchronizacje i crony modułu;
2. zatrzymaj konsumentów Allegro;
3. ustal stan przetwarzanych zamówień i ofert;
4. cofnij kod zgodnie z procesem projektu;
5. nie usuwaj tabel modułu ani mapowań ofert.

Po przywróceniu działania uruchom health-check i włączaj procesy pojedynczo.
