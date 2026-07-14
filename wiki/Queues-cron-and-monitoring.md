# Kolejki, cron i monitoring

Moduł wykonuje synchronizacje asynchronicznie. Sam zapis produktu nie gwarantuje przetworzenia komunikatu — wymagane są działające kolejki i konsumenci.

## Konsumenci

MySQL MQ:

```text
AllegroApiQueueDb
AllegroOrderStatusQueueDb
AllegroShipmentQueueDb
```

RabbitMQ:

```text
AllegroApiQueue
AllegroOrderStatusQueue
AllegroShipmentQueue
```

Konsumenci mogą działać przez `cron_consumers_runner`, Supervisor/systemd albo zostać uruchomieni zgodnie ze standardem danego projektu Magento.

## Cron

Systemowy crontab powinien uruchamiać cron Magento co minutę. Podstawowa kontrola:

```bash
bin/magento cron:run
bin/magento queue:consumers:list
bin/magento macopedia:allegro:health
```

## Interpretacja health-checku

Oczekiwany rezultat to między innymi aktywne OAuth, brak martwych operacji oraz kolejka, która nie rośnie stale. Wartość `never` może być prawidłowa dla świadomie wyłączonego zadania.

## Gdy kolejka rośnie

1. Sprawdź, czy uruchomiono konsumenta właściwego dla wybranego backendu MQ.
2. Sprawdź status OAuth i łączność z API.
3. Odczytaj błędy konsumenta oraz techniczny identyfikator żądania Allegro.
4. Zweryfikuj retry i martwe operacje.
5. Nie usuwaj komunikatów ani mapowań przed ustaleniem ich stanu biznesowego.

## Debug mode

Tryb debug zapisuje techniczny kontekst żądań, między innymi metodę, endpoint, status, request ID i nazwy pól. Nie powinien zapisywać nagłówka Authorization, tokenów, Client Secret ani pełnego body z danymi klientów. Log znajduje się w `var/log/allegro-http-request.log` względem katalogu Magento.
