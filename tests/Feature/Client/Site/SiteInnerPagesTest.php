<?php

namespace Tests\Feature\Client\Site;

use App\Client\Site\Copy;
use App\Client\Site\SiteSettings;
use Tests\TestCase;

/** The inner pages: the home's dark card at the top, and on About the profile beside the company's details. */
class SiteInnerPagesTest extends TestCase
{
    public function test_every_inner_page_opens_on_the_dark_band_with_a_drawing(): void
    {
        foreach (['/tentang-kami', '/mitra', '/kontak', '/rencana-pengembangan'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('site-page-hero--band', false)
                ->assertSee('site-page-hero__drawing', false);
        }
    }

    public function test_the_legal_pages_keep_the_plain_heading(): void
    {
        $this->get('/kebijakan-privasi')->assertOk()->assertDontSee('site-page-hero--band', false);
        $this->get('/syarat-penjualan')->assertOk()->assertDontSee('site-page-hero--band', false);
    }

    public function test_about_holds_the_profile_beside_the_company_details_and_each_category_once(): void
    {
        $html = $this->get('/tentang-kami')->assertOk()->getContent();

        $this->assertStringContainsString('class="site-profile__facts"', $html);
        $this->assertSame(count(Copy::records('categories')), substr_count($html, 'class="site-plate"'));
        $this->assertSame(count(Copy::records('steps')), substr_count($html, 'class="site-path__step"'));
        $this->assertStringContainsString('class="site-cta"', $html);
    }

    public function test_a_drawing_shown_twice_on_a_page_keeps_its_hatching_apart(): void
    {
        $html = $this->get('/tentang-kami')->assertOk()->getContent();

        preg_match_all('/<pattern id="([^"]+)"/', $html, $patterns);

        $this->assertGreaterThan(1, count($patterns[1]));
        $this->assertSame($patterns[1], array_values(array_unique($patterns[1])), 'an id repeated takes the first drawing\'s colour');
    }

    public function test_a_partner_carries_the_initials_of_its_first_two_words(): void
    {
        $this->partners([['name' => 'Karya Mitra Abadi', 'description' => 'Supplier', 'field' => 'Parts', 'country' => 'Indonesia', 'since' => '2021']]);

        $this->get('/mitra')->assertOk()
            ->assertSee('<span class="site-partner__tile" aria-hidden="true">KM</span>', false)
            ->assertSee('Karya Mitra Abadi')
            ->assertSee('class="site-cta"', false);
    }

    public function test_with_no_partner_the_page_says_so(): void
    {
        $this->partners([]);

        $this->get('/mitra')->assertOk()->assertSee('site-partners__empty', false);
    }

    public function test_the_first_question_stands_open_and_only_the_first(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<details[^>]*\bopen\b/', $html));
    }

    /** @param list<array<string, string>> $partners */
    private function partners(array $partners): void
    {
        app(SiteSettings::class)->set('partners', $partners);
    }
}
