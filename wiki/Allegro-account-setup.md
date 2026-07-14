# Przygotowanie konta Allegro

Konfigurację wykonuje się osobno dla Sandboxa i produkcji. Konto, aplikacja, Client ID, Client Secret oraz token OAuth muszą pochodzić z tego samego środowiska.

## Aplikacja REST API

Zarejestruj aplikację w odpowiednim panelu:

- [Allegro produkcyjne](https://apps.developer.allegro.pl/)
- [Allegro Sandbox](https://apps.developer.allegro.pl.allegrosandbox.pl/)

Redirect URI skopiuj z pola **Use following callback** w konfiguracji modułu. Musi być identyczny z adresem zapisanym w aplikacji, łącznie z HTTPS, ścieżką panelu i ewentualnym `index.php`.

## Dane wymagane przed publikacją oferty

W panelu sprzedawcy utwórz:

1. co najmniej jeden zwykły cennik dostawy;
2. warunki zwrotów;
3. warunki reklamacji;
4. informacje o gwarancji, jeśli jej udzielasz;
5. prawdziwe dane producenta lub osoby odpowiedzialnej, jeżeli wymaga ich GPSR i produkt katalogowy ich nie zawiera.

Moduł pobiera te elementy z konta przez API, ale ich nie tworzy. Dane z Sandboxa nie są widoczne na koncie produkcyjnym i odwrotnie.

## GPSR

Nie należy utożsamiać:

- marki — parametru produktu;
- producenta odpowiedzialnego — danych podmiotu gospodarczego;
- osoby odpowiedzialnej — podmiotu w UE wymaganego w określonych przypadkach, między innymi dla części producentów spoza UE.

Najpierw sprawdź `productSafety` wybranego produktu katalogowego. Jeżeli dane są niekompletne, utwórz właściwy wpis na koncie Allegro i wybierz go w formularzu oferty Magento. Nie używaj danych losowych.

## One Fulfillment

Zwykła integracja nie wymaga One Fulfillment. Nie wybieraj cennika tej usługi, jeśli konto nie jest aktywne.

Jeśli korzystasz z One Fulfillment, wymagane są między innymi aktywna usługa, właściwy cennik, ustawienia VAT, zapas przyjęty przez Magazyn Allegro oraz **Adres do wycofania z Magazynu Allegro**. Stan po stronie sprzedawcy powinien odpowiadać zasadom tej usługi.

## Przydatne instrukcje Allegro

- [Cenniki dostawy](https://allegro.pl/pomoc/dla-sprzedajacych/cennik-dostawy/cenniki-dostawy-tworzenie-edycja-i-podmiana-B826XYWjvFg)
- [Warunki zwrotów](https://allegro.pl/dla-sprzedajacych/warunki-oferty-zwroty-a124GwdXZFA)
- [Warunki reklamacji](https://allegro.pl/dla-sprzedajacych/warunki-oferty-reklamacje-vKgeWL5GnHA)
- [Informacje o gwarancji](https://allegro.pl/dla-sprzedajacych/warunki-oferty-gwarancje-9dXYn0VeXHM)
- [Wymagania GPSR](https://help.allegro.com/pl/sell/a/jakie-obowiazki-naklada-na-ciebie-rozporzadzenie-gpsr-x5xK1MOxac1)
