# Produkty, GTIN, marka i Katalog Allegro

## GTIN

GTIN może mieć 8, 12, 13 lub 14 cyfr i musi mieć poprawną cyfrę kontrolną. Kod powinien być prawidłowo nadany przez właściciela marki, zwykle w systemie GS1. Walidacja formatu nie potwierdza prawa do używania kodu.

Nie generuj losowych GTIN-ów dla ofert produkcyjnych. Błąd `GTIN does not exist in GS1` oznacza zwykle, że kod jest testowy, błędny albo nie został nadany danemu produktowi.

## Wyszukiwanie produktu

Formularz oferty pozwala:

1. wpisać GTIN i wyszukać produkt w Allegro;
2. wpisać co najmniej trzy znaki nazwy i użyć **Znajdź GTIN po nazwie produktu**.

Wyszukiwanie po nazwie korzysta z Katalogu Allegro, a nie tylko z własnych ofert. Operator musi porównać nazwę, wariant, zdjęcie, GTIN i kategorię. Moduł nie wybiera automatycznie pierwszego wyniku.

Dla ogólnych produktów zacznij od krótkiej frazy, np. `biurko`, a dopiero później ją zawężaj. Sandbox ma osobny i uboższy katalog, więc wyniki mogą różnić się od produkcji.

## Istniejący produkt katalogowy

Po wybraniu UUID istniejącego produktu Katalog Allegro jest źródłem prawdy dla parametrów identyfikujących, w tym marki. Moduł nie wysyła wtedy lokalnego `manufacturer` w `productSet.product.parameters`, ponieważ mógłby on być sprzeczny z produktem wskazanym przez GTIN.

Przykład: jeśli Magento ma markę `6PAK Nutrition`, a wskazany GTIN należy do produktu marki `Pasieka Sienkiewicz`, nie należy zmieniać marki katalogowej. Trzeba sprawdzić, czy wybrano właściwy produkt. Błąd `PARAMETER_MISMATCH` chroni przed powiązaniem niezgodnych danych.

## Nowy produkt katalogowy

Lokalny atrybut marki, domyślnie `manufacturer`, jest używany w przepływie bez istniejącego UUID, jeśli kategoria wymaga tego parametru. Wartość musi odpowiadać słownikowi parametrów danej kategorii Allegro.

## Zmiana GTIN aktywnej oferty

Po zmianie GTIN ponownie wybierz właściwy produkt katalogowy. GTIN, UUID, kategoria i marka muszą opisywać ten sam produkt. Nie traktuj zmiany samego pola EAN jako bezpiecznej migracji oferty do innego produktu katalogowego.

## GPSR a marka

Marka nie zastępuje producenta ani osoby odpowiedzialnej wymaganych przez GPSR. Dane bezpieczeństwa mogą pochodzić z `productSafety` produktu katalogowego albo z prawdziwego wpisu wybranego z konta sprzedawcy.
