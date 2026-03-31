# Symfony Modular Monolith (Monolit modularny)

[![CI](https://github.com/gmaxsoft/SymfonyModularMonolith/actions/workflows/ci.yml/badge.svg)](https://github.com/gmaxsoft/SymfonyModularMonolith/actions/workflows/ci.yml)

Aplikacja demonstracyjna i szablon **Symfony 7** zorganizowany jako **modularny monolit**: jedno wdrożenie, jedna baza kodu, ale logika biznesowa podzielona na **moduły** z jasnymi granicami katalogów, zamiast wielu niezależnych mikrousług.

## Czym jest modularny monolit?

**Monolit** to tradycyjnie jedna aplikacja, w której wszystkie funkcje żyją w jednym procesie i często w jednym dużym drzewie katalogów. **Modularny monolit** zachowuje jedno wdrożenie i współdzieloną infrastrukturę (np. jedna baza danych), ale wymusza podział na **moduły domenowe**:

- Każdy moduł grupuje swoje kontrolery, serwisy, repozytoria i encje.
- Zależności między modułami są widoczne w kodzie (łatwiej pilnować granic niż w „szufladkowanym” monolicie).
- W przyszłości wybrany moduł można wydzielić do osobnej usługi — bez przepisywania całego systemu naraz.

To kompromis między prostotą operacyjną monolitu a porządkiem architektonicznym zbliżonym do mikrousług **bez** ich kosztów (sieć, rozproszone transakcje, wiele pipeline’ów wdrożeń).

## Jak zaimplementowano to w Symfony (krok po kroku)

Poniżej opisane są decyzje odpowiadające strukturze tego repozytorium.

### 1. Układ katalogów

- `src/Modules/<NazwaModułu>/` — kod modułu funkcjonalnego, np. `SampleModule`.
  - `Controller/` — kontrolery HTTP z atrybutami routingu.
  - `Service/` — logika aplikacyjna modułu.
  - `Repository/` — repozytoria (np. Doctrine `ServiceEntityRepository`).
  - `Entity/` — encje ORM (mapowane osobno; **nie** rejestrowane automatycznie jako usługi DI).
  - `Resources/config/` — opcjonalna konfiguracja modułu (np. dodatkowe pliki YAML).
- `src/Shared/` — wspólny szkielet aplikacji (np. `Kernel`, w przyszłości współdzielone encje w `Shared/Entity/`).

### 2. Autoload (`composer.json`)

- Przestrzeń `App\Modules\` mapowana jest na `src/Modules/` (jawnie, przed ogólnym `App\` → `src/`), aby nazewnictwo pakietów było spójne z podziałem fizycznym.

### 3. Rejestracja usług (`config/services.yaml`)

- `App\` skanuje `src/` z **wyłączeniem** `src/Modules/`, żeby nie duplikować definicji.
- `App\Modules\` skanuje `src/Modules/` z wyłączeniem:
  - `Entity/` — encje nie są serwisami kontenera.
  - `DependencyInjection/` — zarezerwowane pod ewentualne rozszerzenia modułu.
  - `Resources/` — zasoby konfiguracyjne, nie klasy aplikacji.

Dzięki temu kontrolery, serwisy i repozytoria modułów są **autowire’owane** tak jak w standardowym projekcie Symfony.

### 4. Routing (`config/routes.yaml`)

- Zachowany jest import `routing.controllers` (standard Symfony 7).
- Dodany jest import z **globem** `../src/Modules/*/Controller/` z typem `attribute`, aby każdy moduł z katalogiem `Controller/` automatycznie udostępniał trasy z atrybutu `#[Route]`.

### 5. Doctrine (`config/packages/doctrine.yaml`)

- Osobne mapowanie dla encji współdzielonych: `src/Shared/Entity`, prefiks `App\Shared\Entity`.
- Osobne mapowanie dla modułów: katalog `src/Modules` z prefiksem `App\Modules`, aby encje w `*/Entity/` były widoczne dla ORM.

### 6. Kernel

- Klasa kernel znajduje się w `src/Shared/Kernel.php` (`App\Shared\Kernel`), a `public/index.php` oraz `bin/console` wskazują na nią — bootstrap pozostaje czytelny przy rosnącej liczbie modułów.

### 7. Narzędzia developerskie

- **PHPUnit** — szablon konfiguracji (`phpunit.dist.xml`, `tests/bootstrap.php`), środowisko testowe `APP_ENV=test`, `KERNEL_CLASS` ustawiony na `App\Shared\Kernel`.
- **Psalm** (`psalm.xml`) z wtyczką **Symfony** i plikiem kontenera w trybie dev (po `cache:warmup`).
- **PHP CS Fixer** (`.php-cs-fixer.dist.php`) z zestawem reguł `@Symfony`; skanowanie ograniczone do `src`, `tests`, `config`, `bin`, `public`, `migrations` (bez `vendor`).

## Stack technologiczny

| Warstwa | Technologia |
|--------|-------------|
| Język | PHP **8.2+** (projekt testowany m.in. na PHP 8.3) |
| Framework | **Symfony 7.4** (komponenty zgrupowane przez `symfony/framework-bundle`) |
| ORM / baza | **Doctrine ORM 3**, **Doctrine Bundle 2**, **Doctrine Migrations** |
| Konfiguracja | YAML, atrybuty PHP (`#[Route]`, mapowanie encji) |
| Testy | **PHPUnit 12** |
| Statyczna analiza | **Psalm 6** + `psalm/plugin-symfony` |
| Formatowanie kodu | **PHP CS Fixer 3** |
| HTTP (dev tests) | **BrowserKit**, **DomCrawler**, **CssSelector** |
| CI | **GitHub Actions** (PHPUnit, PHP CS Fixer, Psalm — patrz niżej) |
| Opcjonalnie | Docker: `compose.yaml` / `compose.override.yaml` (jeśli używasz lokalnego stacku kontenerów) |

## GitHub Actions

W repozytorium skonfigurowano ciągłą integrację w pliku [`.github/workflows/ci.yml`](.github/workflows/ci.yml).

- **Zdarzenia:** push oraz pull requesty do gałęzi `main`.
- **Środowisko:** `ubuntu-latest`, **PHP 8.2** z rozszerzeniami `ctype`, `iconv`, `mbstring`, `intl`, `pdo_sqlite`.
- **Zmienne w jobie:** m.in. `APP_SECRET`, `DATABASE_URL` wskazujące na plik **SQLite** w `var/ci.sqlite` (wyłącznie pod rozgrzanie cache i narzędzia — bez konieczności działającego serwera SQL).
- **Kolejność kroków:**
  1. `composer install`
  2. `php bin/console cache:warmup --env=dev` — wymagane m.in. przez Psalma z wtyczką Symfony (odczyt zrzutu kontenera DI)
  3. `composer test` (PHPUnit)
  4. `composer cs-check` (PHP CS Fixer, tryb `--dry-run`)
  5. `composer psalm`

Status ostatniego uruchomienia widać przy znaczku **CI** u góry pliku oraz w zakładce **Actions** na GitHubie:  
[https://github.com/gmaxsoft/SymfonyModularMonolith/actions](https://github.com/gmaxsoft/SymfonyModularMonolith/actions).

## Wymagania

- PHP z rozszerzeniami wymienionymi w `composer.json` (`ctype`, `iconv`, itd.).
- **Composer** 2.x.
- Silnik bazy zgodny z **Doctrine DBAL** (np. PostgreSQL, SQLite, MySQL — wg `DATABASE_URL`).
- W niektórych środowiskach Composer może zgłaszać konflikt z rozszerzeniem **Redis**; wtedy instalacja zależności może wymagać tymczasowego pominięcia wymagania platformy, np.  
  `composer install --ignore-platform-req=ext-redis`  
  (dopasuj do własnego środowiska).

## Uruchomienie projektu

```bash
# 1. Zależności
composer install

# (opcjonalnie, jeśli pojawi się konflikt ext-redis)
composer install --ignore-platform-req=ext-redis
```

Skonfiguruj zmienne środowiskowe. Domyślnie Symfony wczytuje `.env`; wrażliwe wartości trzymaj w `.env.local` (nie commituj ich).

```bash
# 2. Wygeneruj APP_SECRET (np.)
# php -r "echo bin2hex(random_bytes(16)).PHP_EOL;"

# 3. Ustaw DATABASE_URL w .env lub .env.local (przykład SQLite na start)
# DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_%kernel.environment%.db"

# 4. Migracje (gdy masz już poprawne DATABASE_URL)
php bin/console doctrine:migrations:migrate --no-interaction
```

Serwer deweloperski (wbudowany PHP):

```bash
php -S 127.0.0.1:8000 -t public
```

Lub [Symfony CLI](https://symfony.com/download): `symfony server:start`.

Przykładowy endpoint modułu demonstracyjnego: **GET** `http://127.0.0.1:8000/sample-module/hello`

## Przydatne polecenia

| Polecenie | Opis |
|-----------|------|
| `composer test` | PHPUnit |
| `composer psalm` | Analiza statyczna |
| `composer cs-check` / `composer cs-fix` | Styl kodu (dry-run / naprawa) |
| `php bin/console debug:router` | Lista tras |

## Licencja

Oprogramowanie jest własnością **Maxsoft**. Szczegóły w pliku [LICENSE](LICENSE).

---

**Maxsoft** — Symfony Modular Monolith
