Tytuł projektu: NIP-GUS Checker (Bitrix24 Local App)
Typ aplikacji: Server-side Local App osadzona jako zakładka w karcie Firmy w CRM (Placement: CRM_COMPANY_TAB) oraz dostępna z menu głównego.

1. Cel Biznesowy:
Automatyzacja wprowadzania danych firmowych do Bitrix24. Użytkownik podaje numer NIP, a aplikacja pobiera dane z API Ministerstwa Finansów (Biała Lista) i uzupełnia/aktualizuje kartę Firmy w systemie CRM, weryfikując jednocześnie status podatnika VAT.

2. Wymagania UI/UX (na podstawie zrzutów ekranu):

Stylistyka: Aplikacja musi używać klas CSS z biblioteki Bitrix24 Design Tokens (np. ui-btn, ui-ctl, ui-nav).

Górna nawigacja (Zakładki):

App - główny widok aplikacji.

Ustawienia - panel konfiguracyjny.

Kup licencję - przycisk przekierowujący/informacyjny.

Widok "App" (Stepper 3-krokowy):

Krok 1 (Wyszukaj): Pole input na NIP i przycisk z ikoną lupy. Pod spodem instrukcja tekstowa ("Aplikacja umożliwia stworzenie...").

Krok 2 (Sprawdź): Widok podzielony na dwie sekcje. Po lewej: formularz z pobranymi danymi (Pełna nazwa firmy, NIP, Kraj, Miasto, Ulica, Nr mieszkania/biura, Kod pocztowy, PKD) w trybie read-only. Po prawej: duży prostokątny panel "Status VAT" (zielony z tekstem "Podmiot o wskazanym NIP jest zarejestrowanym czynnym podatnikiem VAT" lub czerwony w przypadku braku rejestracji). Na dole przyciski "Wstecz", "Zaktualizuj obecną firmę", "Utwórz firmę".

Krok 3 (Zapisz): Komunikat o sukcesie po dodaniu/aktualizacji w CRM.

3. Wymagania Logiczne i Techniczne:

Integracja API: Endpoint proxy w PHP pobierający dane z https://wl-api.mf.gov.pl/api/search/nip/ (w celu uniknięcia błędów CORS na frontendzie).

Logika CRM (BX24 JS SDK):

Tworzenie firmy (crm.company.add).

Aktualizacja istniejącej firmy (crm.company.update), jeśli aplikacja została uruchomiona w kontekście konkretnej karty w CRM.

Zabezpieczenie przed duplikatami: przed utworzeniem nowej firmy, wyszukaj po NIP (crm.company.list). Jeśli istnieje, zablokuj tworzenie i zasugeruj aktualizację.

Moduł Ustawień (Mapowanie Pól): Interfejs pozwalający przypisać dane z API (np. name, nip, workingAddress) do konkretnych pól w Bitrix24 (np. TITLE, UF_CRM_NIP). Aplikacja musi pobrać listę dostępnych pól używając crm.company.fields.