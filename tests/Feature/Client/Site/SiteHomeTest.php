<?php

namespace Tests\Feature\Client\Site;

use App\Client\Models\SiteImage;
use App\Client\Site\Copy;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The home: one slot under the hero, promos when live, the categories until then; a category never shows twice. */
class SiteHomeTest extends TestCase
{
    public function test_with_no_live_promo_the_categories_take_the_carousel_and_show_once(): void
    {
        $this->promo(['show_until' => today()->subDay()]); // over: as good as none

        $html = $this->get('/')->assertOk()->getContent();
        $categories = count(Copy::records('categories'));

        $this->assertGreaterThan(1, $categories);
        $this->assertStringContainsString('site-promos--categories', $html);
        $this->assertSame($categories, substr_count($html, 'site-promo--category'), 'one slide each');
        $this->assertSame($categories, substr_count($html, 'class="site-promos__tab"'), 'one tab each');
        $this->assertStringContainsString('data-prev', $html);
        $this->assertStringContainsString('data-next', $html);
        $this->assertStringNotContainsString('class="site-categories"', $html, 'no second list of the same four');
        $this->assertStringNotContainsString('site-promo--poster', $html);
    }

    public function test_a_live_promo_takes_the_carousel_whole_and_the_categories_stand_as_cards(): void
    {
        $promo = $this->promo(['show_until' => today()->addDays(10)]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('site-promo--poster', $html);
        $this->assertStringContainsString('src="'.$promo->url().'"', $html);
        $this->assertStringContainsString('alt="Promo akhir tahun"', $html, 'the words inside an image are said in text too');
        $this->assertStringContainsString('Berlaku sampai', $html);
        $this->assertStringContainsString('class="site-categories"', $html);
        $this->assertStringNotContainsString('site-promos--categories', $html);
        $this->assertStringNotContainsString('data-prev', $html, 'one slide has nothing to page through');
    }

    public function test_several_live_promos_page_with_arrows_and_dots(): void
    {
        $this->promo();
        $this->promo();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-prev', $html);
        $this->assertStringContainsString('data-next', $html);
        $this->assertSame(2, substr_count($html, 'data-dot'));
    }

    public function test_the_brands_are_on_the_about_page_not_the_home(): void
    {
        $brand = Copy::list('brands')[0];

        $this->get('/')->assertOk()->assertDontSee($brand);
        $this->get(route('site.about'))->assertOk()->assertSee($brand);
    }

    public function test_the_roadmap_is_in_the_footer_not_the_header(): void
    {
        [$top, $bottom] = explode('<main', (string) $this->get('/')->assertOk()->getContent());
        $roadmap = 'href="'.route('site.roadmap').'"';

        $this->assertStringNotContainsString($roadmap, $top);
        $this->assertStringContainsString($roadmap, $bottom);
    }

    public function test_a_category_with_no_drawing_of_its_own_gets_the_carton(): void
    {
        $known = view('client.site.partials.category-drawing', ['name' => 'BEARING PART', 'class' => 'x'])->render();
        $other = view('client.site.partials.category-drawing', ['name' => 'BRAKE PART', 'class' => 'x'])->render();

        $this->assertStringContainsString('class="x"', $other);
        $this->assertNotSame($known, $other);
        $this->assertStringContainsString('M180 30', $other);
    }

    /** @param array<string, mixed> $attributes */
    private function promo(array $attributes = []): SiteImage
    {
        Storage::fake('public');

        return SiteImage::query()->create($attributes + [
            'kind' => SiteImage::PROMO,
            'title' => ['id' => 'Promo akhir tahun', 'en' => 'Year-end promo'],
            'text' => ['id' => 'Berlaku untuk pelanggan.', 'en' => 'For customers.'],
            'image_path' => 'promo/post.png',
            'link' => '/kontak',
            'is_active' => true,
            'sort' => 0,
        ]);
    }
}
