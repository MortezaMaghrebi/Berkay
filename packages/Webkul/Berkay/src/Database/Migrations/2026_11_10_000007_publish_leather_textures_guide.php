<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Publish the guide linked from the home page without replacing an editor's copy. */
    public function up(): void
    {
        $slug = 'leather-textures-guide';

        if (DB::table('berkay_articles')->where('slug', $slug)->exists()) {
            return;
        }

        $title = 'آشنایی با انواع طرح چرم';
        $excerpt = 'چرم ساده و طرح‌دار از نزدیک چه تفاوتی دارند؟ با دیدن نمونه‌های بافت و نقش، انتخاب طرح کیف یا اکسسوری آسان‌تر می‌شود.';
        $body = <<<'HTML'
<h2>چرم ساده چه ظاهری دارد؟</h2>
<p>در یک طرح ساده، توجه بیشتر به رنگ، دوخت و فرم محصول جلب می‌شود. اگر انتخابتان بین دو نمونهٔ نزدیک است، عکس بزرگ‌تر را ببینید و یکنواختی رنگ را در قسمت‌های مختلف سطح مقایسه کنید.</p>
<h2>در چرم طرح‌دار به چه چیزهایی نگاه کنیم؟</h2>
<p>اندازه و تکرار نقش را در نظر بگیرید. طرحی که روی یک تکهٔ کوچک ظریف به نظر می‌رسد، ممکن است روی بدنهٔ یک کیف بزرگ جلوهٔ متفاوتی پیدا کند. دیدن عکسِ نزدیک در کنار تصویرِ کامل محصول کمک می‌کند نسبت نقش به اندازهٔ کیف را بهتر بسنجید.</p>
<h2>طرح را با کاربرد محصول مقایسه کنید</h2>
<p>اگر می‌خواهید کیف با لباس‌های مختلف هماهنگ شود، آن را کنار رنگ‌های پرکاربردتان تصور کنید. اگر نقش چرم بخش اصلی انتخاب شماست، از فروشنده عکس همان نمونه را در نور طبیعی بخواهید و برای اطلاع از جنس و شیوهٔ مراقبت، مشخصات همان محصول را بخوانید.</p>
HTML;
        $published = now();

        DB::table('berkay_articles')->insert([
            'title' => $title,
            'slug' => $slug,
            'category' => 'راهنمای خرید',
            'excerpt' => $excerpt,
            'body' => $body,
            'meta_title' => $title.' | مجلهٔ برکای',
            'meta_description' => $excerpt,
            'status' => 'published',
            'published_at' => $published,
            'created_at' => $published,
            'updated_at' => $published,
        ]);
    }

    /** Preserve the article and any editorial changes on rollback. */
    public function down(): void {}
};
