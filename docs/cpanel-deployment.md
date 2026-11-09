# انتشار دستی برکای از GitHub روی cPanel

این روش دقیقاً «کامپیوتر → GitHub → مخزن cPanel → برنامهٔ اصلی» است. پوش به origin/main به‌تنهایی سایت را منتشر نمی‌کند. بعد از هر پوش باید در cPanel ابتدا **Update from Remote** و سپس **Deploy HEAD Commit** را بزنید. GitHub Actions و Secretهای CPANEL_SSH لازم نیستند.

## مسیرهای این پروژه

- مخزن cPanel: `/home/charmber/repositories/Berkay`
- برنامهٔ اجرایی: `/home/charmber/berkay`
- Document Root دامنه و فایل‌های عمومی: `/home/charmber/public_html`
- فایل خصوصی تنظیم انتشار: `/home/charmber/.berkay-deploy.conf`

مسیرهای Sefaresh3d را استفاده نکنید. پوشهٔ public_html از قبل روی هاست وجود دارد؛ public نام پوشهٔ عمومی داخل مخزن لاراول است. هنگام انتشار، فقط محتوای public پروژه به public_html منتقل می‌شود. فایل index.php با مسیر درست برنامه ساخته می‌شود و public_path لاراول برای دستورات CLI و وب روی همان public_html تنظیم می‌شود. .env، vendor، storage و کدهای برنامه در پوشهٔ خصوصی berkay می‌مانند.

در فایل ~/.berkay-deploy.conf این مسیرها را تنظیم کنید:

```bash
APP_DIR="/home/charmber/berkay"
PUBLIC_DIR="/home/charmber/public_html"
PHP_BIN="/opt/cpanel/ea-php84/root/usr/bin/php"
COMPOSER_BIN="/bin/composer"
```

پوشهٔ berkay را در File Manager، کنار public_html ایجاد کنید. فایل .env برنامه در /home/charmber/berkay/.env قرار می‌گیرد. روی public_html موجود ابتدا بکاپ بگیرید؛ فایل‌های عمومی هم‌نام، از جمله index.php و .htaccess با نسخهٔ سایت برکای به‌روز می‌شوند. فایل‌های اضافی و آپلودهای سرور حذف نمی‌شوند.

## پیش‌نیازهای هاست

PHP 8.4 در وب و CLI، Composer 2، دیتابیس سازگار بگیستو، SSH، /bin/cp، flock، realpath، امکان symlink و Cron لازم‌اند. php مورد استفاده Composer باید همان PHP 8.4 باشد. همهٔ اکستنشن‌ها را composer check-platform-reqs بررسی می‌کند. خروجی Vite فروشگاه و ادمین در public/themes/.../build داخل Git موجود است؛ Node.js روی هاست برای انتشار لازم نیست.

## تنظیم اولیه

۱. مخزن موجود `git@github.com:MortezaMaghrebi/Berkay.git` را در cPanel → Git Version Control کلون کنید و شاخهٔ main را انتخاب کنید. برای مخزن خصوصی، کلید عمومی SSH هاست را به‌عنوان Deploy Key فقط خواندنی به همین مخزن اضافه کنید. کلید خصوصی و توکن را در مخزن قرار ندهید. وضعیت عمومی/خصوصی مخزن را خودتان در GitHub بررسی و در صورت نیاز Private کنید.

۲. فایل deploy/cpanel.conf.example را به `~/.berkay-deploy.conf` کپی کنید و مسیرها و PHP/Composer را با اطلاعات میزبان تنظیم کنید:

```bash
cp /home/charmber/repositories/Berkay/deploy/cpanel.conf.example ~/.berkay-deploy.conf
chmod 600 ~/.berkay-deploy.conf
mkdir -p ~/berkay
```

۳. در cPanel دیتابیس و کاربر با دسترسی به همان دیتابیس بسازید. فایل .env.example را یک بار به `~/berkay/.env` کپی کنید؛ مقادیر را روی سرور وارد کنید، از جمله:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-DOMAIN
BERKAY_PUBLIC_PATH=/home/charmber/public_html
APP_KEY=YOUR-PERMANENT-APPLICATION-KEY
APP_LOCALE=fa
APP_CURRENCY=IRT
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=CPANEL_DATABASE
DB_charmber=CPANEL_DATABASE_USER
DB_PASSWORD=PRIVATE_PASSWORD
CACHE_STORE=file
SESSION_DRIVER=file
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
```

نام دیتابیس/کاربر cPanel معمولاً پیشوند نام کاربری هاست را دارد. .env را با chmod 600 محافظت کنید. APP_KEY را هنگام به‌روزرسانی هرگز عوض نکنید. اگر دیتابیس محلی را با تنظیمات رمز‌شدهٔ موجود منتقل می‌کنید، کلید فعلی همان نصب را به‌صورت خصوصی منتقل کنید؛ کلید جدید باعث ناخوانا‌شدن داده‌های رمز‌شده می‌شود. برای نصب کاملاً خالی می‌توانید یک کلید دائمی با PHP بسازید:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

۴. **دیتابیس فعلی و تصاویر محصولات را جداگانه منتقل کنید.** برای حفظ ۱۸۴ محصول و تنوع‌ها، از دیتابیس محلی خروجی بگیرید و در دیتابیس هاست وارد کنید. محتوای storage/app/public را به همین مسیر در برنامهٔ سرور منتقل کنید. تصاویر/پیوست‌های خصوصی در storage/app/private نیز جداگانه منتقل شوند. دیتابیس و پوشه‌های storage را داخل مخزن Git یا public_html قرار ندهید. در تنظیمات کانال بگیستو hostname محلی را به دامنهٔ واقعی تغییر دهید. رمز ادمین پیش‌فرض را پیش از عمومی‌کردن سایت از پنل تغییر دهید.

۵. در Terminal هاست، بررسی بدون تغییر را اجرا کنید:

```bash
cd ~/repositories/Berkay
/bin/bash deploy/cpanel-deploy.sh --check
```

۶. در cPanel، Update from Remote و سپس Deploy HEAD Commit را بزنید. مهاجرت‌ها اجرا می‌شوند؛ هیچ seeder، نصب مجدد بگیستو یا migrate:fresh اجرا نمی‌شود. این مسیر برای انتقال فروشگاه موجود است. نصب کاملاً خالی به نصب اولیه و ایجاد کانال/حساب مدیر نیاز دارد؛ صرف migration جایگزین نصب بگیستو نیست.

## هر انتشار چه می‌کند؟

- نسخهٔ PHP، مسیرها، فایل‌های ساخت و وجود .env تولید را کنترل می‌کند؛ هم‌زمان فقط یک انتشار اجازه دارد.
- سایت موجود را به حالت نگهداری می‌برد.
- فقط فایل‌های ثبت‌شده در Git از پوشه‌های مجاز را با /bin/cp منتقل می‌کند. .git، .runtime، node_modules، تست‌ها، .env و storage محلی کپی نمی‌شوند.
- فایل‌های public را بدون حذف فایل‌های اضافی سرور منتقل می‌کند. لینک public_html/storage و کش تصاویر حفظ می‌شوند.
- وابستگی‌ها را از composer.lock بدون بسته‌های توسعه نصب می‌کند و نیازمندی‌های واقعی PHP را بررسی می‌کند.
- کش قبلی را پاک، package discovery و migration را اجرا می‌کند؛ لینک storage را در صورت نبودن می‌سازد.
- config/view/route cache را می‌سازد و درخواست راه‌اندازی دوبارهٔ صف‌ها را ثبت می‌کند.
- سایت را از حالت نگهداری خارج و شناسهٔ کامیت را در `.berkay-deployed` ثبت می‌کند.

اگر خطا رخ دهد، مراحل بعد اجرا نمی‌شوند. اگر نگهداری فعال شده باشد همان‌طور می‌ماند تا علت رفع شود. نسخهٔ پشتیبان دیتابیس و فایل‌ها را قبل از انتشار از cPanel بگیرید؛ اسکریپت پشتیبان‌گیری یا rollback خودکار ندارد. کپی کد درجا است، بنابراین انتشار اتمی و بدون downtime نیست. در صورت نیاز به بازگشت، کامیت قبلی را در Git بازگردانید و دوباره منتشر کنید؛ بازگرداندن migration/دیتابیس مرحلهٔ جدا و وابسته به بکاپ است. بعد از رفع خطا، در صورت نیاز php artisan up را از پوشهٔ برنامه اجرا کنید.

## Cron و صف

در cPanel → Cron Jobs با مسیر PHP واقعی هاست:

```cron
* * * * * cd /home/charmber/berkay && /opt/cpanel/ea-php84/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/charmber/berkay && /usr/bin/flock -n /home/charmber/.berkay-worker.lock /opt/cpanel/ea-php84/root/usr/bin/php artisan queue:work --queue=default,notifications --stop-when-empty --max-time=50 --tries=3 --timeout=45 >> storage/logs/queue-cron.log 2>&1
```

### پیامک و ورود مشتری

در پنل مدیریت، «مدیریت پیامک کاوه‌نگار» را باز کنید. ابتدا کلید API و نام الگوی تأییدشدهٔ ورود را ذخیره و ارسال آزمایشی را امتحان کنید. نمونهٔ متن و توکن‌های هر الگو در پنل مشخص است. ثبت‌نام، تغییر شماره و حذف حساب در صورت خالی بودن الگوی اختصاصی از الگوی ورود استفاده می‌کنند. برای رویدادهای سفارش، الگوی موردنیاز را وارد کنید. ارسال پیامک سفارش به worker صف نیاز دارد.

ورود مدیران از مسیر مدیریت خود سایت انجام می‌شود. مشتری‌های قبلی با شمارهٔ موبایل ثبت‌شده وارد می‌شوند. اگر شمارهٔ مشتری خالی، نامعتبر یا با حساب دیگری مشترک است، مالک فروشگاه ابتدا شماره را از «مدیریت کاربران» بررسی و اصلاح می‌کند.

اگر هاست worker دائمی با Supervisor فراهم می‌کند از همان استفاده کنید. راهکار Cron را فقط با تأیید محدودیت‌های پردازش میزبان فعال کنید. queue:restart به‌تنهایی worker را اجرا نمی‌کند. فایل‌های لاگ و فضای دیسک را مدیریت کنید.

## بررسی بعد از انتشار

Last Deployment Information باید موفق و شناسهٔ کامیت جدید باشد. صفحهٔ اول، دسته‌بندی، گالری محصول، انتخاب رنگ/قیمت/موجودی، ورود مشترک، پنل ادمین، آپلود تصویر، سبد و پرداخت آزمایشی را بررسی کنید. SSL دامنه، آدرس کانال، تنظیمات واقعی درگاه و پیامک و برداشتن noindex محلی فقط پس از آماده‌شدن فروشگاه برای انتشار عمومی بررسی شوند.

مرجع: https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-set-up-deployment/
مرجع فایل انتشار: https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-deployment/

## روش کپی بدون rsync

فایل .cpanel.yml اسکریپت cpanel-deploy.sh را اجرا می‌کند. آن اسکریپت برای انتقال فایل‌ها copy-files.sh را صدا می‌زند که فقط از /bin/cp استفاده می‌کند. نصب rsync لازم نیست.

فهرست مجاز: app، bootstrap، config، database، lang (اگر موجود باشد)، resources، routes، packages، public و فایل‌های artisan و composer.json و composer.lock. فایل‌های مخفی عمومی مانند public/.htaccess نیز کپی می‌شوند. فایل‌های .env، storage، cacheهای runtime، node_modules، تست‌ها، بکاپ دیتابیس و public/storage کپی نمی‌شوند. public/index.php جداگانه با مسیر واقعی برنامه ساخته می‌شود. فایل خصوصی یا آپلودی در مقصد حذف نمی‌شود.

این روش هیچ فایل منسوخی را خودکار حذف نمی‌کند. اگر تغییری حذف یک فایل کد قدیمی را لازم کند، مسیر دقیق آن در برنامهٔ انتشار همان تغییر بررسی و جداگانه مدیریت می‌شود؛ حذف کلی پوشه یا پاک‌سازی public_html انجام نمی‌شود.

مسیرها قبل از کپی با realpath بررسی می‌شوند؛ برنامه باید خارج از public_html و مخزن باشد، و ریشهٔ عمومی نباید مخزن یا برنامهٔ خصوصی را در خود داشته باشد. بررسی مسیر واقعی روی هاست از Terminal حساب charmber با دستورهای زیر انجام می‌شود:

```bash
realpath /home/charmber/repositories/Berkay
realpath /home/charmber/berkay
realpath /home/charmber/public_html
```

قبل از Deploy، در cPanel → Domains نیز تأیید کنید Document Root دامنه charmberkay.ir همان public_html است. بررسی محلی اسکریپت جایگزین بررسی مسیرهای واقعی هاست نیست.

بلاک‌های PHP handler و تنظیمات PHP که با نشان BEGIN/END cPanel-generated در .htaccess فعلی هاست مشخص شده‌اند هنگام به‌روزرسانی حفظ می‌شوند تا انتخاب PHP 8.4 دامنه از بین نرود. سایر تنظیمات اختصاصی خارج از این بلاک‌ها باید پیش از انتشار در نسخهٔ مناسب .htaccess بررسی شوند.
