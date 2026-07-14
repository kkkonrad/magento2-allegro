# Rozwiązywanie problemów

| Objaw | Najczęstsza przyczyna | Rozwiązanie |
| --- | --- | --- |
| `GTIN does not exist in GS1` | Testowy, błędny lub nienadany kod | Użyj prawdziwego GTIN właściciela marki. |
| Brak marki produktu | Katalog lub propozycja produktu nie ma wymaganego parametru | Wybierz właściwy UUID albo uzupełnij `manufacturer` dla nowego produktu. |
| `PARAMETER_MISMATCH` dla `Marka` | Lokalna marka nie zgadza się z produktem wskazanym przez GTIN | Sprawdź produkt katalogowy; dla istniejącego UUID nie nadpisuj marki katalogowej. |
| Brak zwrotów lub reklamacji | Warunki nie istnieją na tym koncie/środowisku | Utwórz je w Allegro i odśwież formularz. |
| Brak producenta/osoby odpowiedzialnej | Produkt podlega GPSR, a katalog nie ma kompletnych danych | Dodaj prawdziwy wpis na koncie i wybierz go w ofercie. |
| One Fulfillment nieaktywne | Wybrano cennik usługi na zwykłym koncie | Wybierz zwykły cennik albo aktywuj usługę. |
| Brak adresu wycofania | Konto One Fulfillment nie ma wymaganego adresu | Dodaj **Adres do wycofania z Magazynu Allegro**. |
| Wyszukiwanie nazwy zwraca 0 | Ubogi katalog Sandbox lub zbyt dokładna fraza | Użyj krótszej frazy i sprawdź środowisko. |
| OAuth disconnected | Zmieniono aplikację, sekret, środowisko lub wygasł refresh token | Zapisz poprawne dane i wykonaj OAuth ponownie. |
| Kolejka stale rośnie | Konsument nie działa lub API odrzuca komunikaty | Sprawdź konsumentów, health-check i martwe operacje. |
| Zamówienie nie importuje się | Brak mapowania produktu, dostawy, płatności lub regionu | Sprawdź mapowania i rekord błędu importu. |

## Diagnostyka krok po kroku

```bash
bin/magento macopedia:allegro:health
bin/magento queue:consumers:list
bin/magento cron:run
```

Następnie sprawdź:

1. czy środowisko OAuth odpowiada danym aplikacji;
2. czy zasób istnieje na tym samym koncie Allegro;
3. czy oferta jest powiązana z właściwym produktem Magento;
4. czy odpowiedni konsument działa;
5. request ID oraz komunikat zwrócony przez Allegro.

Do zgłoszenia dołącz wersję Magento i PHP, środowisko Sandbox/produkcja, kroki odtworzenia, request ID i zanonimizowany komunikat. Nie dołączaj sekretów, tokenów ani danych klientów.
