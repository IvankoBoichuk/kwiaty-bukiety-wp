# Кеш у проєкті

Як влаштовані кеші на сайті, що з ними відбувається під час деплою, як їх чистити вручну і як з'ясувати, яку версію сторінки віддає сайт.

Значення нижче зняті з dev (`dev.kwiaty-bukiety.com.pl`) у жовтні 2026. Налаштування плагіна LiteSpeed Cache зберігаються в базі, а не в репо, тому перед тим як на них покладатися, перевірте актуальні значення: `wp litespeed-option all`.

## Шари кешу

Запит проходить шари зверху вниз:

| Шар | Що кешує | Скільки | Ким керується |
|---|---|---|---|
| Браузер | Файли збірки `public/build/assets/*` | 1 рік (`max-age=31557600`) | Заголовки сервера |
| **Hostinger CDN** (`server: hcdn`, edge `fra-edge6…10`) | HTML сторінок для гостей | Головна 7 днів (`604800`), 404 1 година | Заголовками `X-LiteSpeed-*`, які надсилає плагін LiteSpeed Cache |
| PHP opcache | Скомпільований PHP | Перевіряє файли кожні 2 с | Налаштування PHP на хостингу |
| Acorn | Конфіг, маршрути, Blade-шаблони (`web/app/cache/acorn`) | До очищення | `wp acorn optimize:clear` |
| WP transients | Логотип, індекс міст (див. нижче) | 12 год – 1 доба | Код теми |

**Головне:** HTML кешує CDN Hostinger, а не LiteSpeed на сервері. Плагін LiteSpeed Cache тільки виставляє заголовки:

- `X-LiteSpeed-Cache-Control`: чи кешувати сторінку і на скільки;
- `X-LiteSpeed-Tag`: теги для вибіркового очищення;
- `X-LiteSpeed-Purge`: команда очистити кеш.

Edge-сервери CDN цих заголовків слухаються. Кожен edge має **свою** копію, тому два запити поспіль можуть отримати різні версії сторінки. Запит, який іде напряму на сервер в обхід CDN, кешованої копії не отримує ніколи.

Важливі налаштування плагіна (`litespeed.conf.*`):

- кешуються сторінки для гостей; для залогінених кеш приватний (`cache-priv`);
- `/cart/` не кешується (`cache-exc`);
- **мобільна версія кешується окремо** (`cache-mobile`), тобто на кожному edge одна копія для десктопа і одна для мобільних;
- 404 кешуються на 1 годину (`cache-ttl_status: 404 3600`);
- оптимізація CSS/JS у плагіні вимкнена, QUIC.cloud не використовується, object cache вимкнений.

## Чому після деплою може зникнути стиль

Vite додає до назв файлів хеш вмісту: `app-iLz64ato.css`. HTML посилається на конкретну назву.

1. Новий білд → нові назви файлів. `rsync --delete` видаляє старі.
2. Якщо на edge лишилась стара копія HTML, вона посилається на вже видалений `app-<старий хеш>.css` → 404 → сторінка без стилів.
3. Цей 404 теж кешується на годину.

Тому після кожного деплою кеш CDN **обов'язково** треба очистити і перевірити, що очищення спрацювало. Пайплайн робить це сам (див. далі).

Браузерний кеш тут не винен. Новий білд має нові назви файлів, тому браузер ніколи не підставить старий CSS замість нового.

## Що робить пайплайн

Кроки з [.woodpecker/ci.yaml](../.woodpecker/ci.yaml), які стосуються кешу, по порядку:

1. **`maintenance-on`**: вмикає режим обслуговування (`wp maintenance-mode activate`, запасний варіант — файл `web/wp/.maintenance`).
2. **`deploy`**: `rsync --delete`, потім `wp acorn optimize:clear`.
3. **`maintenance-off`**: вимикає режим обслуговування.
4. **`purge-litespeed`**: `wp litespeed-purge all`. Якщо падає, пайплайн червоний.
5. **`purge-hostinger-cache`**: `DELETE …/cache/clear` через API Hostinger. API відповідає тільки `"Request accepted"` і чистить асинхронно.
6. **`smoke-test`**: сайт відповідає 200.
7. **`verify-assets`**: 16 разів запитує головну (8 як десктоп, 8 як мобільний) і перевіряє, що всі файли `public/build/assets/*`, на які посилається HTML, є в щойно зібраному білді. Якщо ні, повторно запускає `purge-litespeed`, чекає 20 с і пробує ще раз, до 6 спроб.

Чому порядок саме такий:

- **Очищення LiteSpeed іде тільки після `maintenance-off`.** `wp litespeed-purge all` не чистить кеш сам. Він робить HTTP-запит до `admin-ajax.php` на публічному домені, і вже ця відповідь несе заголовок `X-LiteSpeed-Purge`, який чистять edge. У режимі обслуговування сайт відповідає 503, і очищення не відбувається. Так було на кожному деплої до жовтня 2026: помилку ховав `|| true`.
- **Перевірка робить кілька спроб.** Після очищення окремі edge кілька секунд, а іноді й хвилин, ще віддають стару копію.

## PHP 8.3 і `wp` на Hostinger

На хостингу дві версії PHP. `/usr/local/bin/wp` за замовчуванням запускається на PHP 8.2 і падає на перевірці Composer (`require php >= 8.3`), ще до того, як завантажиться WordPress. Робочий `wp` — це функція в `~/.bashrc`:

```bash
wp() { /opt/alt/php83/usr/bin/php /usr/local/bin/wp "$@"; }
```

Наслідки для скриптів через SSH:

- `ssh host "cd … && wp …"` (команда рядком) працює: bash, запущений sshd, читає `.bashrc`.
- `ssh host "sh -s" <<EOF … wp … EOF` **не** працює: дочірній shell функції не бачить. У кроках maintenance тому і стоїть `export -f wp 2>/dev/null; … bash -s`.

## Як почистити кеш вручну

Спочатку переконайтесь, що сайт **не** в режимі обслуговування. Потім будь-яким із способів:

- **Адмінка WP:** LiteSpeed Cache → Toolbox → Purge → Purge All. Найпростіше, працює одразу.
- **SSH:**
  ```bash
  cd ~/domains/dev.kwiaty-bukiety.com.pl/public_html
  wp litespeed-purge all
  ```
  Має вивести `Success: Wyczyszczono wszystko!`. `Got 503` означає, що сайт у режимі обслуговування або лежить.
- **hPanel** → кеш сайту → Clear cache. Те саме робить API в пайплайні. Очищення асинхронне.

Після очищення почекайте 20–30 с і перевірте (наступний розділ).

## Як перевірити, яку версію віддає сайт

```bash
# Кілька запитів поспіль: кожен може потрапити на інший edge
for i in 1 2 3 4 5 6; do
  curl -sS -D /tmp/h -o /tmp/p https://dev.kwiaty-bukiety.com.pl/
  grep -iE 'x-hcdn-request-id|x-hcdn-cache-status|^age' /tmp/h | tr '\n' ' '
  grep -oE 'build/assets/app-[^"]+\.css' /tmp/p | head -1
done

# Який CSS правильний: його повертає маніфест (запит з ?x= обходить кеш)
curl -sS "https://dev.kwiaty-bukiety.com.pl/app/themes/sage/public/build/manifest.json?x=$RANDOM" \
  | grep -A6 '"resources/js/app.ts"' | grep -A1 '"css"'
```

Як читати заголовки:

- `x-hcdn-cache-status: HIT` + `age: N`: копія з edge, збережена N секунд тому.
- `x-hcdn-cache-status: DYNAMIC` або `MISS`: свіжа відповідь із сервера.
- `x-litespeed-cache: hit` на `HIT` означає лише, що edge віддав свою копію. На сервері сторінки не зберігаються.
- Для мобільної версії додайте `-A 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'`.

Якщо CSS у HTML не збігається з маніфестом, на цьому edge застарілий кеш: почистіть вручну.

## Кеш у коді теми

Окремого сховища для object cache (Redis/Memcached) немає. Тому `wp_cache_set()` живе **лише в межах одного запиту**, а між запитами зберігаються тільки transients (у базі).

| Де | Що | Як довго |
|---|---|---|
| [Support/Context.php](../web/app/themes/sage/app/Support/Context.php) | ID вкладення логотипу за URL (`sage_logo_id_*`) | transient, 1 доба |
| [Modules/LocalLinking/LocalPageRepository.php](../web/app/themes/sage/app/Modules/LocalLinking/LocalPageRepository.php) | Індекс міст (усі міста з посадковими сторінками), з якого працює пошук | transient `sage_city_index`, 12 год. Після імпорту таблиць `bi_*`: `wp transient delete sage_city_index` |
| [Api/Cities.php](../web/app/themes/sage/app/Api/Cities.php), [Api/PostalCode.php](../web/app/themes/sage/app/Api/PostalCode.php) | **Не кешуються** по запитах (див. нижче) | — |
| [Api/CartCount.php](../web/app/themes/sage/app/Api/CartCount.php), [Api/DeliverySchedule.php](../web/app/themes/sage/app/Api/DeliverySchedule.php) | **Не кешуються**: `litespeed_control_set_nocache` + `Cache-Control: no-store` | — |

**Відповіді пошуку не кешуйте в transients.** Ключ залежав би від того, що ввів користувач, тож кожне натискання клавіші, кожен префікс і кожен `limit` додавали б рядки в `wp_options`. А видаляються такі рядки лише після закінчення TTL. Крім того, transient — це теж запит до бази, тож він не швидший за один індексований `SELECT`. Transients годяться лише для небагатьох ключів, які не залежать від вводу користувача, як-от індекс міст.

Якщо новий endpoint віддає щось персональне (кошик, nonce, дані користувача), вимкніть для нього кеш так само, як у `CartCount` / `DeliverySchedule`. Інакше CDN роздасть одну відповідь усім.

Кошик у браузері тримається на cookie сесії WooCommerce (`wp_woocommerce_session_*`). Клієнт Store API ([woo-store-api/client.ts](../web/app/themes/sage/resources/js/modules/woo-store-api/client.ts)) **не** бере `Cart-Token` з відповідей. Запит із `Cart-Token` працює з окремою сесією токена і cookie не отримує. Тому товар, доданий з токеном, видно на сторінці, але після перезавантаження кошик порожній. Так і було, коли на закешованій сторінці товару прострочений nonce давав 403 із токеном нового гостя, а повторний запит ішов уже з ним.

## Відомі обмеження

- **Головна живе в кеші CDN 7 днів.** Зміни контенту в адмінці плагін LiteSpeed чистить за тегами сам. Зміни коду — тільки через деплой або ручне очищення.
- **Відсутній файл збірки обробляє WordPress,** і 404 кешується на годину. В `web/.htaccess` (на сервері, не в репо) немає правила, яке б віддавало для `/app/themes/*/public/build/*` простий 404 без WordPress.
- **Perf-тести на задеплоєному сайті** ненадійні, поки є ризик застарілих копій на edge. Деталі в [tests/perf/README.md](../tests/perf/README.md).
