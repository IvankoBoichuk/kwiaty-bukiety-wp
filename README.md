# WooCommerce Starter Repo

## Опис

Це стартовий репозиторій для розробки сайту на WordPress + WooCommerce.

В основі використовується Bedrock — більш зручна та сучасна структура WordPress-проєкту, де залежності керуються через Composer, а код і конфігурація організовані акуратніше, ніж у стандартній збірці WordPress.

## Для чого цей репозиторій

Цей репозиторій потрібен як готова база для запуску інтернет-магазину. Його можна використовувати для:

- створення магазину на WooCommerce;
- додавання товарів, категорій та атрибутів;
- налаштування кошика, checkout і оформлення замовлень;
- підключення оплат, доставки та інших інтеграцій;
- подальшої кастомної розробки теми та функціоналу.

## Що вже є в проєкті

- WordPress як CMS;
- WooCommerce як основа магазину;
- Bedrock для зручної структури проєкту;
- підтримка Composer для керування залежностями;
- тема на базі Sage для подальшої кастомізації фронтенду.

## Структура проєкту

- [web](web) — публічна частина сайту;
- [web/app](web/app) — теми, плагіни, mu-плагіни, uploads;
- [web/wp](web/wp) — ядро WordPress;
- [config](config) — конфігурація середовищ;
- [composer.json](composer.json) — PHP-залежності проєкту.

## Локальний запуск

Зазвичай робота з проєктом відбувається так:

1. встановити залежності через Composer;
2. налаштувати файл середовища з локальними змінними;
3. підняти локальну базу даних;
4. запустити сайт у локальному середовищі.

## Коротко

Якщо простими словами: це не просто чистий WordPress, а вже підготовлена стартова база саме під WooCommerce сайт, яку можна брати за основу для магазину й далі розвивати під потрібний дизайн та бізнес-логіку.

## Розгортання

Деплой у `dev` робить Woodpecker (`.woodpecker/deploy.yaml`) на пуш у гілку `dev`.
GitHub Actions (`.github/workflows/deploy.yml`) лишився ручним запасним варіантом.
Прод-кроки в конвеєрі поки закоментовані.

Конвеєр збирає тему, ставить composer-залежності, **окремо збирає editor-assets
плагіна `frontenda-blocks`** (він тримає `blocks/section/build/` у `.gitignore`,
тож composer-архів приходить без зібраного JS), розкладає все через
`rsync --delete` і скидає кеш Acorn.

### Що треба на сервері окремо

- `.env` — не деплоїться (виключений із rsync і з git). Для `dev` став
  `WP_ENV=staging`, інакше або сайт індексується, або в публіку світять помилки.
- `.htaccess` — теж не деплоїться.
- Таблиця поштових індексів:

  ```bash
  wp acorn postal-codes:install
  wp postal-codes import data/postal-codes.csv
  ```

  Без неї автокомпліт міста й індексу в чекауті не працює.

### Перенести прод на локальну машину

```bash
mysqldump -h <host> -u <user> -p --single-transaction --default-character-set=utf8mb4 <database> > stage-$(date +%F-%H%M).sql
tar -czf uploads-$(date +%F-%H%M).tar.gz web/app/uploads
```

Локально:

```bash
mysql -h database -u wordpress -ppassword wordpress < stage-<timestamp>.sql
tar -xzf uploads-<timestamp>.tar.gz
wp search-replace 'https://dev.kwiaty-bukiety.com.pl' 'http://localhost:8084'
```

## Блоки

Секції сторінок будуються з `fa/section` — блока з плагіна
[frontenda-blocks](https://github.com/IvankoBoichuk/frontenda-blocks), який
ставиться через composer. Variant і layout секції обирають PHP-шаблон:
спершу шукається `web/app/themes/sage/frontenda-blocks/section/<variant>-<layout>.php`
у темі, і лише потім шаблон плагіна. Кожен такий файл — тонкий місток до
однойменного Blade у `resources/views/frontenda-blocks/section/`.

Після змін у плагіні:

```bash
composer update frontenda/frontenda-blocks
cd web/app/plugins/frontenda-blocks && bun install && bun run build
```

## Примітка


README написаний у спрощеному вигляді, щоб швидко зрозуміти призначення репозиторію без зайвої технічної інформації.
