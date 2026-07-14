# Instalacja

## Wymagania

- Magento 2.4 i zgodna wersja PHP 8.1+
- dostęp do CLI Magento
- skonfigurowany cron Magento
- MySQL MQ lub RabbitMQ
- konto sprzedawcy oraz aplikacja Allegro REST API

## Instalacja kodu

Umieść moduł w `app/code/Macopedia/Allegro`, a następnie z katalogu głównego Magento wykonaj:

```bash
bin/magento module:enable Macopedia_Allegro
bin/magento setup:upgrade
bin/magento cache:flush
```

W trybie produkcyjnym wykonaj również standardowe dla projektu kroki kompilacji DI i wdrożenia statycznych zasobów.

## Po instalacji

1. Otwórz **Sklepy → Konfiguracja → Allegro → Configuration**.
2. Pozostaw wyłączony import zamówień i automatyczne synchronizacje.
3. Wybierz Sandbox albo produkcję.
4. Skonfiguruj aplikację i [[wykonaj OAuth|Magento-configuration]].
5. Uzupełnij lokalizację wysyłki oraz mapowania metod.
6. [[Uruchom konsumentów i cron|Queues-cron-and-monitoring]].
7. Wystaw jedną ofertę testową, a automatyzacje włączaj etapami.

## Aktualizacja

Przed aktualizacją wykonaj kopię bazy danych i konfiguracji bez sekretów. Po wymianie kodu uruchom:

```bash
bin/magento setup:upgrade
bin/magento cache:flush
bin/magento macopedia:allegro:health
```

Nie usuwaj tabel modułu ani mapowań ofert podczas zwykłej aktualizacji.
