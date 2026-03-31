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

Pełne drzewo katalogów, tabele opisów modułów i ścieżek w korzeniu — w sekcji [Struktura projektu](#struktura-projektu).

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

## Struktura projektu

Poniżej zestawiono katalogi i pliki istotne dla architektury oraz narzędzi. Pozycje `var/` i `vendor/` powstają lokalnie po uruchomieniu aplikacji lub `composer install` i **nie** są zwykle commitowane (patrz `.gitignore`).

```
.
├── .github/
│   └── workflows/
│       └── ci.yml              # GitHub Actions: PHPUnit, PHP CS Fixer, Psalm
├── bin/
│   ├── console                 # Konsola Symfony (polecenia aplikacji)
│   └── phpunit                 # Uruchamiacz PHPUnit z Composer
├── config/
│   ├── bundles.php             # Rejestracja pakietów (bundli)
│   ├── packages/               # Fragmenty konfiguracji per pakiet (Doctrine, Framework, routing…)
│   ├── routes/                 # Dodatkowe definicje routingu (np. framework)
│   ├── routes.yaml             # Główny import tras (w tym modułów)
│   ├── services.yaml           # Kontener DI: skanowanie src/ i src/Modules/
│   ├── preload.php
│   └── reference.php           # Referencja konfiguracji (generowana / narzędziowa)
├── migrations/                 # Klasy migracji Doctrine (wersjonowanie schematu bazy)
├── public/
│   └── index.php               # Front controller HTTP (wejście do aplikacji)
├── src/
│   ├── Controller/             # (szkielet Flex) — puste; kontrolery aplikacji są w modułach
│   ├── Entity/                 # (szkielet Flex) — puste; encje dzielone: Shared/Entity, moduły: Modules/*/Entity
│   ├── Modules/                # Moduły domenowe (logika „w paczkach”)
│   │   └── SampleModule/
│   │       ├── Controller/
│   │       ├── Entity/
│   │       ├── Repository/
│   │       ├── Resources/
│   │       │   └── config/     # Opcjonalna konfiguracja modułu (YAML)
│   │       └── Service/
│   ├── Repository/             # (szkielet Flex) — puste; repozytoria wewnątrz modułów
│   └── Shared/                 # Kod współdzielony całą aplikacją
│       ├── Entity/             # Encje wspólne (mapowanie Doctrine: alias App\Shared\Entity)
│       └── Kernel.php          # Kernel aplikacji (App\Shared\Kernel)
├── tests/
│   └── bootstrap.php           # Start PHPUnit (Dotenv, APP_DEBUG)
├── .editorconfig               # Ujednolicenie edytora (wcięcia, końce linii)
├── .env                        # Domyślne zmienne środowiskowe (szablon; sekrety → .env.local)
├── .env.dev / .env.test        # Warianty środowiska (test: KERNEL_CLASS, APP_SECRET)
├── .gitignore
├── .php-cs-fixer.dist.php      # Reguły PHP CS Fixer (@Symfony)
├── compose.yaml                # Docker Compose (opcjonalny stack)
├── compose.override.yaml       # Nadpisania lokalne dla Compose
├── composer.json / composer.lock
├── LICENSE                     # Licencja Maxsoft
├── phpunit.dist.xml            # Konfiguracja PHPUnit
├── psalm.xml                   # Analiza statyczna + wtyczka Symfony
├── README.md
└── symfony.lock                # Wersje recept Symfony Flex (powiązane z composer.lock)
```

### Opis katalogów i plików w korzeniu repozytorium

| Ścieżka | Opis |
|--------|------|
| `.github/workflows/` | Definicje **GitHub Actions** (ciągła integracja). |
| `bin/` | Skrypty wykonywalne z CLI: **`console`** (Symfony), **`phpunit`**. |
| `config/` | Cała konfiguracja aplikacji: **usługi**, **trasy**, **bundles**, pliki w **`packages/`** dla Doctrine, Framework, cache itd. |
| `migrations/` | Pliki migracji **Doctrine** — historia zmian schematu bazy. |
| `public/` | Jedyny katalog serwowany na zewnątrz; **`index.php`** przekazuje żądania do kernela. |
| `src/` | Kod źródłowy PHP: **moduły** (`Modules/`), **współdzielony** kod (`Shared/`), oraz puste szkielety `Controller` / `Entity` / `Repository` z Flexa (można usunąć lub wykorzystać poza modułami). |
| `tests/` | Testy automatyczne; **`bootstrap.php`** ładuje `.env` i przygotowuje środowisko `test`. |
| `var/` | Cache, logi, sesje — generowane w runtime (**gitignore**). |
| `vendor/` | Biblioteki z **Composera** (**gitignore**). |

### Moduł (`src/Modules/<Nazwa>/`)

| Podkatalog | Opis |
|------------|------|
| `Controller/` | Klasy obsługujące HTTP; trasy przez atrybut `#[Route]` (import z `config/routes.yaml`). |
| `Entity/` | Encje **Doctrine**; mapowane w `doctrine.yaml`; **nie** wchodzą do autowiringu jako serwisy. |
| `Repository/` | Repozytoria (np. `ServiceEntityRepository`); rejestrowane jako usługi **DI**. |
| `Service/` | Serwisy domenowe / aplikacyjne modułu. |
| `Resources/config/` | Opcjonalne YAML/XML modułu; domyślnie **wyłączone** z automatycznego skanowania usług — import ręczny, gdy potrzebny. |
| `DependencyInjection/` | (Opcjonalnie) rozszerzenia kontenera danego modułu — wyłączone ze skanowania w `services.yaml`. |

### Współdzielony kod (`src/Shared/`)

| Element | Opis |
|---------|------|
| `Kernel.php` | Główna klasa kernela (`App\Shared\Kernel`); **`public/index.php`** i **`bin/console`** ją ładują. |
| `Entity/` | Encje używane przez wiele modułów; prefiks nazewnictwa **`App\Shared\Entity`**. |

## Stack technologiczny

| Warstwa | Technologia |
|--------|-------------|
| Język | PHP **8.2+** (projekt testowany m.in. na PHP 8.3) |
| Framework | **Symfony 7.4** (komponenty zgrupowane przez `symfony/framework-bundle`) |
| ORM / baza | **Doctrine ORM 3**, **Doctrine Bundle 2**, **Doctrine Migrations** |
| Konfiguracja | YAML, atrybuty PHP (`#[Route]`, mapowanie encji) |
| Testy | **PHPUnit 11** (zgodność z PHP **8.2** w CI; lokalnie można użyć nowszego PHP) |
| Statyczna analiza | **Psalm 6** + `psalm/plugin-symfony` |
| Formatowanie kodu | **PHP CS Fixer 3** |
| HTTP (dev tests) | **BrowserKit**, **DomCrawler**, **CssSelector** |
| CI | **GitHub Actions** (PHPUnit, PHP CS Fixer, Psalm — patrz niżej) |
| Opcjonalnie | Docker: `compose.yaml` / `compose.override.yaml` (jeśli używasz lokalnego stacku kontenerów) |

## GitHub Actions

W repozytorium skonfigurowano ciągłą integrację w pliku [`.github/workflows/ci.yml`](.github/workflows/ci.yml).

- **Zdarzenia:** push oraz pull requesty do gałęzi `main`.
- **Środowisko:** `ubuntu-latest`, **PHP 8.2** z rozszerzeniami `ctype`, `iconv`, `mbstring`, `intl`, `pdo_sqlite` (PHPUnit w projekcie dobrane pod **PHP 8.2**; seria **PHPUnit 12** wymagałaby **PHP 8.3+**).
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
