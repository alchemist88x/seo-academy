# Dependencies & License Inventory — SEO Academy

All package versions and SPDX license identifiers below were extracted directly from the installed Composer and npm environment.

## 1. FOSS License Policy

- **Project License:** Apache License, Version 2.0 (`Apache-2.0`).
- **Permitted Upstream Licenses:** Permissive open-source licenses (`MIT`, `Apache-2.0`, `BSD-3-Clause`, `BSD-2-Clause`, `ISC`, `0BSD`).
- **Copyleft / Dual-License Management:**
  - Multi-licensed Composer packages (`nette/schema`, `nette/utils` under `BSD-3-Clause OR GPL-2.0-only OR GPL-3.0-only`) are utilized strictly under permissive `BSD-3-Clause` terms.
  - LGPL-licensed packages (`dompdf/dompdf` [LGPL-2.1], `dompdf/php-font-lib` [LGPL-2.1-or-later], `dompdf/php-svg-lib` [LGPL-3.0-or-later]) are used dynamically via standard Composer autoloading as separate runtime libraries without modification. Application source code interacting with Dompdf remains under Apache-2.0.
- **Zero Proprietary Runtime Dependencies:** No proprietary, closed-source, or non-commercial packages are required.
- **No Node.js Required in Production:** Production deployments run from pre-compiled assets in `public/build/`.

## 2. Verified Summary Counts

| Ecosystem | Production / Non-Dev | Dev | Total |
|---|---:|---:|---:|
| **Composer (`vendor/composer/installed.json`)** | 86 | 38 | **124** |
| **npm (`package-lock.json`)** | 45 *(build/optional)* | 104 | **149** |
| **Combined Total** | 131 | 142 | **273** |

### Composer License Breakdown (124 packages)

| SPDX License | Production | Dev | Total |
|---|---:|---:|---:|
| `MIT` | 75 | 14 | 89 |
| `BSD-3-Clause` | 5 | 24 | 29 |
| `BSD-3-Clause OR GPL-2.0-only OR GPL-3.0-only` *(elected BSD-3-Clause)* | 2 | 0 | 2 |
| `LGPL-2.1` *(Dompdf)* | 1 | 0 | 1 |
| `LGPL-2.1-or-later` *(FontLib)* | 1 | 0 | 1 |
| `LGPL-3.0-or-later` *(SvgLib)* | 1 | 0 | 1 |
| `Apache-2.0` | 1 | 0 | 1 |
| **Total** | **86** | **38** | **124** |

---

## 3. Composer Production Dependencies (86)

| Package | Version | Type | SPDX License | Legal Review & Compatibility Note |
|---|---|---|---|---|
| `brick/math` | `1.0.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `carbonphp/carbon-doctrine-types` | `3.2.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `dflydev/dot-access-data` | `v3.0.3` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `doctrine/inflector` | `2.1.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `doctrine/lexer` | `3.0.3` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `dompdf/dompdf` | `v3.1.6` | Direct | `LGPL-2.1` | Approved for dynamic runtime use via Composer. Include LGPL-2.1 notice in redistribution packages. |
| `dompdf/php-font-lib` | `1.0.2` | Transitive | `LGPL-2.1-or-later` | Approved for dynamic runtime use via Composer. Include LGPL-2.1 notice in redistribution packages. |
| `dompdf/php-svg-lib` | `1.0.2` | Transitive | `LGPL-3.0-or-later` | Approved for dynamic runtime use via Composer. Retain LGPL-3.0 notice. |
| `dragonmantank/cron-expression` | `v3.6.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `egulias/email-validator` | `4.0.4` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `fruitcake/php-cors` | `v1.4.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `graham-campbell/result-type` | `v1.2.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `guzzlehttp/guzzle` | `8.3.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `guzzlehttp/promises` | `3.1.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `guzzlehttp/psr7` | `3.2.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `guzzlehttp/uri-template` | `v2.0.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `laravel/framework` | `v13.42.0` | Direct | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `laravel/prompts` | `v0.3.27` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `laravel/serializable-closure` | `v2.2.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `laravel/tinker` | `v3.1.0` | Direct | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `league/commonmark` | `2.10.7` | Transitive | `BSD-3-Clause` | Approved. Fully compatible with Apache-2.0. |
| `league/config` | `v1.2.0` | Transitive | `BSD-3-Clause` | Approved. Fully compatible with Apache-2.0. |
| `league/flysystem` | `3.36.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `league/flysystem-local` | `3.35.3` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `league/mime-type-detection` | `1.17.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `league/uri` | `7.10.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `league/uri-interfaces` | `7.10.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `livewire/livewire` | `v4.4.8` | Direct | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `masterminds/html5` | `2.12.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `monolog/monolog` | `3.12.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `nesbot/carbon` | `3.15.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `nette/schema` | `v1.4.0` | Transitive | `BSD-3-Clause, GPL-2.0-only, GPL-3.0-only` | Exercise BSD-3-Clause option. Fully compatible with Apache-2.0 and commercial distribution. |
| `nette/utils` | `v4.1.6` | Transitive | `BSD-3-Clause, GPL-2.0-only, GPL-3.0-only` | Exercise BSD-3-Clause option. Fully compatible with Apache-2.0 and commercial distribution. |
| `nikic/php-parser` | `v5.9.0` | Transitive | `BSD-3-Clause` | Approved. Fully compatible with Apache-2.0. |
| `nunomaduro/termwind` | `v2.4.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `phpoption/phpoption` | `1.10.0` | Transitive | `Apache-2.0` | Approved. Retain Apache-2.0 notice. |
| `psr/clock` | `1.0.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `psr/container` | `2.0.2` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `psr/event-dispatcher` | `1.0.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `psr/http-client` | `1.0.3` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `psr/http-factory` | `1.1.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `psr/http-message` | `2.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `psr/log` | `3.0.2` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `psr/simple-cache` | `3.0.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `psy/psysh` | `v0.12.24` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `ramsey/collection` | `2.1.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `ramsey/uuid` | `4.9.4` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `sabberworm/php-css-parser` | `v9.5.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `spatie/laravel-package-tools` | `1.93.3` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `spatie/laravel-permission` | `8.5.0` | Direct | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/clock` | `v7.4.21` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/console` | `v7.4.23` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/css-selector` | `v7.4.22` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/deprecation-contracts` | `v3.8.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/error-handler` | `v7.4.22` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/event-dispatcher` | `v7.4.17` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/event-dispatcher-contracts` | `v3.8.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/finder` | `v7.4.23` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/http-foundation` | `v7.4.23` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/http-kernel` | `v7.4.23` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/mailer` | `v7.4.22` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/mime` | `v7.4.22` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/polyfill-ctype` | `v1.37.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/polyfill-intl-grapheme` | `v1.43.3` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/polyfill-intl-idn` | `v1.43.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/polyfill-intl-normalizer` | `v1.43.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/polyfill-mbstring` | `v1.43.3` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/polyfill-php80` | `v1.43.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/polyfill-php82` | `v1.43.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/polyfill-php83` | `v1.43.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/polyfill-php84` | `v1.43.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/polyfill-php85` | `v1.43.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/polyfill-php86` | `v1.43.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/polyfill-time` | `v1.42.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/polyfill-uuid` | `v1.43.0` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/process` | `v7.4.22` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/routing` | `v7.4.23` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/service-contracts` | `v3.8.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/string` | `v7.4.22` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/translation` | `v7.4.23` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/translation-contracts` | `v3.8.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/uid` | `v7.4.21` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `symfony/var-dumper` | `v7.4.22` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |
| `tijsverkoyen/css-to-inline-styles` | `v2.4.0` | Transitive | `BSD-3-Clause` | Approved. Fully compatible with Apache-2.0. |
| `vlucas/phpdotenv` | `v5.8.0` | Transitive | `BSD-3-Clause` | Approved. Fully compatible with Apache-2.0. |
| `voku/portable-ascii` | `2.1.1` | Transitive | `MIT` | Approved. Fully compatible with Apache-2.0. |

---

## 4. Composer Dev Dependencies (38)

| Package | Version | Type | SPDX License | Description |
|---|---|---|---|---|
| `composer/semver` | `3.6.0` | Transitive | `MIT` | Version comparison library that offers utilities, version constraint parsing and validation. |
| `fakerphp/faker` | `v1.24.1` | Direct | `MIT` | Faker is a PHP library that generates fake data for you. |
| `filp/whoops` | `2.19.3` | Transitive | `MIT` | php error handling for cool kids |
| `hamcrest/hamcrest-php` | `v3.0.0` | Transitive | `BSD-3-Clause` | This is the PHP port of Hamcrest Matchers |
| `laravel/agent-detector` | `v2.0.5` | Transitive | `MIT` | Detect if code is running in an AI agent or automated development environment |
| `laravel/boost` | `v2.10.4` | Direct | `MIT` | Laravel Boost accelerates AI-assisted development by providing the essential context and structure that AI needs to generate high-quality, Laravel-specific code. |
| `laravel/mcp` | `v1.2.0` | Transitive | `MIT` | Rapidly build MCP servers for your Laravel applications. |
| `laravel/pail` | `v1.3.0` | Direct | `MIT` | Easily delve into your Laravel application's log files directly from the command line. |
| `laravel/pao` | `v1.1.5` | Direct | `MIT` | Agent-optimized output for PHP testing tools |
| `laravel/pint` | `v1.32.3` | Direct | `MIT` | An opinionated code formatter for PHP. |
| `laravel/roster` | `v1.0.0` | Transitive | `MIT` | Detect packages & approaches in use within a Laravel project |
| `mockery/mockery` | `1.6.15` | Direct | `BSD-3-Clause` | Mockery is a simple yet flexible PHP mock object framework |
| `myclabs/deep-copy` | `1.14.0` | Transitive | `MIT` | Create deep copies (clones) of your objects |
| `nunomaduro/collision` | `v8.9.6` | Direct | `MIT` | Cli error handling for console/command-line PHP applications. |
| `phar-io/manifest` | `2.0.4` | Transitive | `BSD-3-Clause` | Component for reading phar.io manifest information from a PHP Archive (PHAR) |
| `phar-io/version` | `3.2.1` | Transitive | `BSD-3-Clause` | Library for handling version information and constraints |
| `phpunit/php-code-coverage` | `12.5.8` | Transitive | `BSD-3-Clause` | Library that provides collection, processing, and rendering functionality for PHP code coverage information. |
| `phpunit/php-file-iterator` | `6.0.2` | Transitive | `BSD-3-Clause` | FilterIterator implementation that filters files based on a list of suffixes. |
| `phpunit/php-invoker` | `6.0.0` | Transitive | `BSD-3-Clause` | Invoke callables with a timeout |
| `phpunit/php-text-template` | `5.0.1` | Transitive | `BSD-3-Clause` | Simple template engine. |
| `phpunit/php-timer` | `8.0.0` | Transitive | `BSD-3-Clause` | Utility class for timing |
| `phpunit/phpunit` | `12.5.43` | Direct | `BSD-3-Clause` | The PHP Unit Testing framework. |
| `sebastian/cli-parser` | `4.2.1` | Transitive | `BSD-3-Clause` | Library for parsing CLI options |
| `sebastian/comparator` | `7.1.8` | Transitive | `BSD-3-Clause` | Provides the functionality to compare PHP values for equality |
| `sebastian/complexity` | `5.0.0` | Transitive | `BSD-3-Clause` | Library for calculating the complexity of PHP code units |
| `sebastian/diff` | `7.0.1` | Transitive | `BSD-3-Clause` | Diff implementation |
| `sebastian/environment` | `8.1.2` | Transitive | `BSD-3-Clause` | Provides functionality to handle HHVM/PHP environments |
| `sebastian/exporter` | `7.0.3` | Transitive | `BSD-3-Clause` | Provides the functionality to export PHP variables for visualization |
| `sebastian/global-state` | `8.0.3` | Transitive | `BSD-3-Clause` | Snapshotting of global state |
| `sebastian/lines-of-code` | `4.0.1` | Transitive | `BSD-3-Clause` | Library for counting the lines of code in PHP source code |
| `sebastian/object-enumerator` | `7.0.0` | Transitive | `BSD-3-Clause` | Traverses array structures and object graphs to enumerate all referenced objects |
| `sebastian/object-reflector` | `5.0.0` | Transitive | `BSD-3-Clause` | Allows reflection of object attributes, including inherited and non-public ones |
| `sebastian/recursion-context` | `7.0.1` | Transitive | `BSD-3-Clause` | Provides functionality to recursively process PHP variables |
| `sebastian/type` | `6.0.4` | Transitive | `BSD-3-Clause` | Collection of value objects that represent the types of the PHP type system |
| `sebastian/version` | `6.0.2` | Transitive | `BSD-3-Clause` | Library that helps with managing the version number of Git-hosted PHP projects |
| `staabm/side-effects-detector` | `1.0.5` | Transitive | `MIT` | A static analysis tool to detect side effects in PHP code |
| `symfony/yaml` | `v7.4.23` | Transitive | `MIT` | Loads and dumps YAML files |
| `theseer/tokenizer` | `2.0.1` | Transitive | `BSD-3-Clause` | A small library for converting tokenized PHP source code into XML and potentially other formats |

---

## 5. npm Dependencies (149)

> Note: `package.json` declares only `devDependencies` and `optionalDependencies` (`@laravel/multiplex`). None of these packages run in production.

| Package | Version | SPDX License | Scope |
|---|---|---|---|
| `@alcalzone/ansi-tokenize` | `0.3.1` | `MIT` | build/optional |
| `@emnapi/core` | `1.11.3` | `MIT` | dev (optional) |
| `@emnapi/core` | `2.0.0-beta.2` | `MIT` | dev (optional) |
| `@emnapi/runtime` | `1.11.3` | `MIT` | dev (optional) |
| `@emnapi/runtime` | `2.0.0-beta.2` | `MIT` | dev (optional) |
| `@emnapi/wasi-threads` | `1.2.3` | `MIT` | dev (optional) |
| `@emnapi/wasi-threads` | `2.2.0` | `MIT` | dev (optional) |
| `@jridgewell/gen-mapping` | `0.3.13` | `MIT` | dev |
| `@jridgewell/remapping` | `2.3.5` | `MIT` | dev |
| `@jridgewell/resolve-uri` | `3.1.2` | `MIT` | dev |
| `@jridgewell/sourcemap-codec` | `1.6.0` | `MIT` | dev |
| `@jridgewell/trace-mapping` | `0.3.31` | `MIT` | dev |
| `@laravel/multiplex` | `0.4.5` | `MIT` | build/optional |
| `@napi-rs/wasm-runtime` | `1.2.2` | `MIT` | dev (optional) |
| `@napi-rs/wasm-runtime` | `1.2.6` | `MIT` | dev (optional) |
| `@oxc-project/types` | `0.156.0` | `MIT` | dev |
| `@rolldown/binding-android-arm-eabi` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-android-arm64` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-darwin-arm64` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-darwin-x64` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-freebsd-x64` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-linux-arm-gnueabihf` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-linux-arm64-gnu` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-linux-arm64-musl` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-linux-ppc64-gnu` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-linux-s390x-gnu` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-linux-x64-gnu` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-linux-x64-musl` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-openharmony-arm64` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-wasm32-wasi` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-win32-arm64-msvc` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/binding-win32-x64-msvc` | `1.2.19` | `MIT` | dev (optional) |
| `@rolldown/pluginutils` | `1.1.0` | `MIT` | dev |
| `@tailwindcss/forms` | `0.5.11` | `MIT` | dev |
| `@tailwindcss/node` | `4.3.5` | `MIT` | dev |
| `@tailwindcss/oxide` | `4.3.5` | `MIT` | dev |
| `@tailwindcss/oxide-android-arm64` | `4.3.5` | `MIT` | dev (optional) |
| `@tailwindcss/oxide-darwin-arm64` | `4.3.5` | `MIT` | dev (optional) |
| `@tailwindcss/oxide-darwin-x64` | `4.3.5` | `MIT` | dev (optional) |
| `@tailwindcss/oxide-freebsd-x64` | `4.3.5` | `MIT` | dev (optional) |
| `@tailwindcss/oxide-linux-arm-gnueabihf` | `4.3.5` | `MIT` | dev (optional) |
| `@tailwindcss/oxide-linux-arm64-gnu` | `4.3.5` | `MIT` | dev (optional) |
| `@tailwindcss/oxide-linux-arm64-musl` | `4.3.5` | `MIT` | dev (optional) |
| `@tailwindcss/oxide-linux-x64-gnu` | `4.3.5` | `MIT` | dev (optional) |
| `@tailwindcss/oxide-linux-x64-musl` | `4.3.5` | `MIT` | dev (optional) |
| `@tailwindcss/oxide-wasm32-wasi` | `4.3.5` | `MIT` | dev (optional) |
| `@tailwindcss/oxide-win32-arm64-msvc` | `4.3.5` | `MIT` | dev (optional) |
| `@tailwindcss/oxide-win32-x64-msvc` | `4.3.5` | `MIT` | dev (optional) |
| `@tailwindcss/typography` | `0.5.20` | `MIT` | dev |
| `@tailwindcss/vite` | `4.3.5` | `MIT` | dev |
| `@tybys/wasm-util` | `0.10.3` | `MIT` | dev (optional) |
| `@tybys/wasm-util` | `0.10.4` | `MIT` | dev (optional) |
| `ansi-escapes` | `7.3.0` | `MIT` | build/optional |
| `ansi-regex` | `6.6.0` | `MIT` | build/optional |
| `ansi-styles` | `6.2.3` | `MIT` | build/optional |
| `ansi-styles` | `6.2.3` | `MIT` | dev |
| `ansi-styles` | `6.2.3` | `MIT` | build/optional |
| `ansi-styles` | `6.2.3` | `MIT` | build/optional |
| `ansi-styles` | `6.2.3` | `MIT` | build/optional |
| `ansi-styles` | `7.0.0` | `MIT` | build/optional |
| `auto-bind` | `5.0.1` | `MIT` | build/optional |
| `chalk` | `5.6.2` | `MIT` | build/optional |
| `cli-boxes` | `4.0.1` | `MIT` | build/optional |
| `cli-cursor` | `4.0.0` | `MIT` | build/optional |
| `cli-truncate` | `6.2.0` | `MIT` | build/optional |
| `cliui` | `9.0.1` | `ISC` | dev |
| `code-excerpt` | `4.0.0` | `MIT` | build/optional |
| `commander` | `15.1.0` | `MIT` | build/optional |
| `concurrently` | `10.0.7` | `MIT` | dev |
| `convert-to-spaces` | `2.0.1` | `MIT` | build/optional |
| `cssesc` | `3.0.0` | `MIT` | dev |
| `detect-libc` | `2.1.2` | `Apache-2.0` | dev |
| `emoji-regex` | `10.6.0` | `MIT` | dev |
| `enhanced-resolve` | `5.26.1` | `MIT` | dev |
| `environment` | `1.1.0` | `MIT` | build/optional |
| `es-toolkit` | `1.53.2` | `MIT` | build/optional |
| `escalade` | `3.2.0` | `MIT` | dev |
| `escape-string-regexp` | `2.0.0` | `MIT` | build/optional |
| `fdir` | `6.5.0` | `MIT` | dev |
| `fsevents` | `2.3.3` | `MIT` | dev (optional) |
| `get-caller-file` | `2.0.5` | `ISC` | dev |
| `get-east-asian-width` | `1.7.0` | `MIT` | build/optional |
| `graceful-fs` | `4.2.11` | `ISC` | dev |
| `indent-string` | `5.0.0` | `MIT` | build/optional |
| `ink` | `7.1.1` | `MIT` | build/optional |
| `is-fullwidth-code-point` | `5.1.0` | `MIT` | build/optional |
| `is-in-ci` | `2.0.0` | `MIT` | build/optional |
| `jiti` | `2.7.0` | `MIT` | dev |
| `laravel-vite-plugin` | `3.2.1` | `MIT` | dev |
| `lightningcss` | `1.33.0` | `MPL-2.0` | dev |
| `lightningcss-android-arm64` | `1.33.0` | `MPL-2.0` | dev (optional) |
| `lightningcss-darwin-arm64` | `1.33.0` | `MPL-2.0` | dev (optional) |
| `lightningcss-darwin-x64` | `1.33.0` | `MPL-2.0` | dev (optional) |
| `lightningcss-freebsd-x64` | `1.33.0` | `MPL-2.0` | dev (optional) |
| `lightningcss-linux-arm-gnueabihf` | `1.33.0` | `MPL-2.0` | dev (optional) |
| `lightningcss-linux-arm64-gnu` | `1.33.0` | `MPL-2.0` | dev (optional) |
| `lightningcss-linux-arm64-musl` | `1.33.0` | `MPL-2.0` | dev (optional) |
| `lightningcss-linux-x64-gnu` | `1.33.0` | `MPL-2.0` | dev (optional) |
| `lightningcss-linux-x64-musl` | `1.33.0` | `MPL-2.0` | dev (optional) |
| `lightningcss-win32-arm64-msvc` | `1.33.0` | `MPL-2.0` | dev (optional) |
| `lightningcss-win32-x64-msvc` | `1.33.0` | `MPL-2.0` | dev (optional) |
| `magic-string` | `1.4.5` | `MIT` | dev |
| `mimic-fn` | `2.1.0` | `MIT` | build/optional |
| `mini-svg-data-uri` | `1.4.4` | `MIT` | dev |
| `nanoid` | `3.3.20` | `MIT` | dev |
| `onetime` | `5.1.2` | `MIT` | build/optional |
| `patch-console` | `2.0.0` | `MIT` | build/optional |
| `picocolors` | `1.1.1` | `ISC` | dev |
| `picomatch` | `2.3.2` | `MIT` | dev |
| `picomatch` | `4.0.7` | `MIT` | dev |
| `postcss` | `8.5.32` | `MIT` | dev |
| `postcss-selector-parser` | `6.0.10` | `MIT` | dev |
| `react` | `19.3.1` | `MIT` | build/optional |
| `react-reconciler` | `0.33.0` | `MIT` | build/optional |
| `restore-cursor` | `4.0.0` | `MIT` | build/optional |
| `rolldown` | `1.2.19` | `MIT` | dev |
| `rxjs` | `7.8.2` | `Apache-2.0` | dev |
| `scheduler` | `0.27.0` | `MIT` | build/optional |
| `shell-quote` | `1.12.0` | `MIT` | dev |
| `signal-exit` | `3.0.7` | `ISC` | build/optional |
| `slice-ansi` | `10.0.1` | `MIT` | build/optional |
| `slice-ansi` | `9.0.1` | `MIT` | build/optional |
| `source-map-js` | `1.2.2` | `BSD-3-Clause` | dev |
| `stack-utils` | `2.0.6` | `MIT` | build/optional |
| `string-width` | `7.2.0` | `MIT` | dev |
| `string-width` | `7.2.0` | `MIT` | dev |
| `string-width` | `8.3.0` | `MIT` | build/optional |
| `strip-ansi` | `7.2.0` | `MIT` | build/optional |
| `supports-color` | `10.2.2` | `MIT` | dev |
| `tagged-tag` | `1.0.0` | `MIT` | build/optional |
| `tailwindcss` | `4.3.5` | `MIT` | dev |
| `tapable` | `2.3.3` | `MIT` | dev |
| `terminal-size` | `4.0.1` | `MIT` | build/optional |
| `tinyglobby` | `0.2.17` | `MIT` | dev |
| `tree-kill` | `1.2.2` | `MIT` | dev |
| `tslib` | `2.8.1` | `0BSD` | dev (optional) |
| `tslib` | `2.9.1` | `0BSD` | dev |
| `type-fest` | `5.10.0` | `(MIT OR CC0-1.0)` | build/optional |
| `util-deprecate` | `1.0.2` | `MIT` | dev |
| `vite` | `8.3.8` | `MIT` | dev |
| `vite-plugin-full-reload` | `1.2.0` | `MIT` | dev |
| `widest-line` | `6.0.0` | `MIT` | build/optional |
| `wrap-ansi` | `10.0.2` | `MIT` | build/optional |
| `wrap-ansi` | `9.0.2` | `MIT` | dev |
| `ws` | `8.22.2` | `MIT` | build/optional |
| `y18n` | `5.0.8` | `ISC` | dev |
| `yargs` | `18.0.0` | `MIT` | dev |
| `yargs-parser` | `22.0.0` | `ISC` | dev |
| `yoga-layout` | `3.2.1` | `MIT` | build/optional |

