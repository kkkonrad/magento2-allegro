# Magento 2 Allegro — dokumentacja

`Macopedia_Allegro` integruje Magento 2.4 z Allegro REST API. Moduł obsługuje Sandbox i produkcję, tworzenie oraz aktualizację ofert, Katalog Produktów Allegro, import zamówień i asynchroniczną synchronizację stanów, cen, statusów i przesyłek.

## Od czego zacząć

1. [[Zainstaluj moduł|Installation]].
2. [[Przygotuj konto i aplikację Allegro|Allegro-account-setup]].
3. [[Skonfiguruj Magento i OAuth|Magento-configuration]].
4. [[Uruchom kolejki i cron|Queues-cron-and-monitoring]].
5. [[Wystaw pierwszą ofertę|Offers]].
6. [[Przetestuj import zamówienia|Orders-and-shipments]].

## Najważniejsze tematy

- [[GTIN, marka i Katalog Allegro|Products-GTIN-and-catalog]]
- [[Tworzenie i obsługa ofert|Offers]]
- [[Import zamówień i przesyłki|Orders-and-shipments]]
- [[Rozwiązywanie problemów|Troubleshooting]]
- [[Bezpieczeństwo i wdrożenie produkcyjne|Security-and-production-rollout]]
- [[Rozwój i testowanie modułu|Development-and-testing]]

## Wymagania w skrócie

- Magento 2.4 (`magento/framework ^103.0`)
- PHP 8.1 lub nowszy zgodny z wersją Magento
- konto sprzedawcy Allegro lub Allegro Sandbox
- aplikacja Allegro REST API oraz autoryzacja OAuth
- działający cron Magento
- MySQL MQ albo RabbitMQ z uruchomionymi konsumentami

## Bezpieczeństwo

Nie zapisuj Client Secret, tokenów OAuth ani danych klientów w repozytorium, logach lub zgłoszeniach. Dane aplikacji Sandbox i produkcyjnej muszą pozostać rozdzielone. Po ujawnieniu sekretu należy go zmienić w panelu aplikacji Allegro i ponownie wykonać OAuth.

## Licencja

Kod jest udostępniony na licencji MIT. Szczegóły znajdują się w pliku `LICENSE` repozytorium.
