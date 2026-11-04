<?php

namespace Tests\Feature\Client\Site;

use App\Client\Site\Http\SiteContentSecurityPolicy;
use App\Client\Site\Sitemap;
use App\Client\Site\SiteSettings;
use App\Domain\Pengaturan\Preferensi;
use App\Domain\Pengaturan\PreferensiKey;
use App\Models\Company\Branch;
use App\Models\Company\PaymentTerm;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The public site: every page open, no price anywhere, the legal pages Indonesian, the strict policy, robots and the sitemap. */
class SiteRoutesTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function pages(): array
    {
        return [
            'home' => ['/'], 'about' => ['/tentang-kami'], 'partners' => ['/mitra'], 'roadmap' => ['/rencana-pengembangan'],
            'contact' => ['/kontak'], 'privacy' => ['/kebijakan-privasi'], 'terms' => ['/syarat-penjualan'], 'sign in' => ['/masuk'],
        ];
    }

    #[DataProvider('pages')]
    public function test_every_page_renders_without_a_login_in_indonesian_with_its_canonical_and_schema(string $path): void
    {
        $response = $this->get($path)->assertOk()
            ->assertSee('<html lang="id"', false)
            ->assertSee('rel="canonical" href="'.url($path === '/' ? '' : $path).'"', false)
            ->assertSee('"@context":"https://schema.org"', false)
            ->assertSee(config('app.name'));
        $html = $response->getContent();

        $this->assertDoesNotMatchRegularExpression('/\bRp\b|\bIDR\b/', $html, "{$path} shows a price");
        $this->assertStringNotContainsString('<script nonce', $html, 'no inline script: the site runs under a strict policy');
        $this->assertStringNotContainsString(' style="', $html, 'no inline style');
        $this->assertStringNotContainsStringIgnoringCase('livewire', $html, 'nothing the site does not use is loaded');
    }

    public function test_the_root_is_the_site_and_no_longer_a_redirect_to_the_panel(): void
    {
        $this->get('/')->assertOk()->assertSee('<main', false);
    }

    public function test_the_site_carries_a_second_stricter_content_policy_the_panels_do_not(): void
    {
        $policies = $this->get('/')->assertOk()->headers->all('content-security-policy');
        $this->assertCount(2, $policies, 'the base policy and the site policy, both enforced');
        $this->assertContains(SiteContentSecurityPolicy::POLICY, $policies);
        $this->assertStringNotContainsString("'unsafe-inline'", SiteContentSecurityPolicy::POLICY);

        $this->assertCount(1, $this->get('/admin/login')->headers->all('content-security-policy'));
    }

    public function test_the_legal_pages_stay_indonesian_whatever_the_cookie_says(): void
    {
        app(Preferensi::class)->set(PreferensiKey::CreditNoticeDays, 120);
        app(Preferensi::class)->set(PreferensiKey::CreditFreezeDays, 150);

        $privacy = $this->withCookie('bahasa', 'en')->get('/kebijakan-privasi')->assertOk()
            ->assertSee('<html lang="id"', false)->assertSee('Kebijakan Privasi')->assertSee('UU PDP')->assertSee('bahasa')
            ->assertDontSee('Privacy policy')->assertDontSee('Sign in');
        $this->assertStringContainsString('Kebijakan privasi', $privacy->getContent(), 'the chrome is Indonesian too');

        $this->withCookie('bahasa', 'en')->get('/syarat-penjualan')->assertOk()
            ->assertSee('<html lang="id"', false)->assertSee('Syarat Penjualan')
            ->assertSee('120 hari')->assertSee('150 hari', false)->assertSee('2% per bulan')
            ->assertDontSee('Terms of sale');
    }

    public function test_the_contact_page_lists_the_active_branches_with_a_map_link(): void
    {
        Branch::query()->create(['name' => 'Jakarta', 'code' => 'JKT', 'used_all_user' => true, 'is_active' => true, 'is_default' => true, 'address' => 'Jl. Contoh No. 1, Jakarta', 'phone_number' => '+62 21 555 0100', 'latitude' => -6.2, 'longitude' => 106.816]);
        Branch::query()->create(['name' => 'Surabaya', 'code' => 'SBY', 'used_all_user' => false, 'is_active' => true, 'address' => 'Jl. Pahlawan 2, Surabaya']);
        Branch::query()->create(['name' => 'Closed', 'code' => 'OLD', 'used_all_user' => false, 'is_active' => false]);

        $this->get('/kontak')->assertOk()
            ->assertSee('Jl. Contoh No. 1, Jakarta')->assertSee('+62 21 555 0100')
            ->assertSee('https://www.google.com/maps?q=-6.2', false)
            ->assertSee('Surabaya')->assertDontSee('Closed');
        $this->get('/')->assertOk()->assertSee('Surabaya')->assertDontSee('Closed');
    }

    public function test_the_home_shows_the_company_at_a_glance_read_from_the_system(): void
    {
        PaymentTerm::query()->create(['name' => 'Net 30', 'due_days' => 30, 'is_default' => true]);
        Branch::query()->create(['name' => 'Jakarta', 'code' => 'JKT', 'used_all_user' => true, 'is_active' => true]);
        Branch::query()->create(['name' => 'Surabaya', 'code' => 'SBY', 'used_all_user' => false, 'is_active' => true]);

        $html = $this->withCookie('bahasa', 'en')->get('/')->assertOk()
            ->assertSee('site-hero__mark', false)
            ->assertSee('site-glance', false)
            ->assertSeeInOrder(['Product categories', '4', 'Brands', '7'])
            ->assertSee('Branches')->assertSee('Credit term')->assertSee('30 days')
            ->getContent();
        $this->assertMatchesRegularExpression('/<dt[^>]*>\s*Branches\s*<\/dt>\s*<dd[^>]*>\s*2\s*<\/dd>/', $html, 'the active branches are counted');

        PaymentTerm::query()->update(['is_default' => false]);
        $this->withCookie('bahasa', 'en')->get('/')->assertOk()->assertDontSee('Credit term');
    }

    public function test_the_home_shows_what_a_buyer_gets_in_the_portal_without_an_amount(): void
    {
        $this->withCookie('bahasa', 'en')->get('/')->assertOk()
            ->assertSee('What you get after sign-in')
            ->assertSeeInOrder(['Repeat your last order', 'Your free credit', 'your own prices', 'surat jalan', 'Open invoices'])
            ->assertSee('site-portal-mock', false);
        $this->withCookie('bahasa', 'id')->get('/')->assertOk()->assertSee('Ulangi pesanan terakhir');
    }

    public function test_the_faq_takes_its_numbers_from_the_payment_term_and_the_terms_of_sale(): void
    {
        PaymentTerm::query()->create(['name' => 'Net 45', 'due_days' => 45, 'is_default' => true]);
        app(SiteSettings::class)->set('legal.terms.claim_days', 5);

        $html = $this->withCookie('bahasa', 'en')->get('/')->assertOk()
            ->assertSee('Frequently asked questions')
            ->assertSee('<details', false)
            ->assertSee('45 days')->assertSee('5 working days')
            ->assertSee('"@type":"FAQPage"', false)
            ->getContent();
        $this->assertStringNotContainsString(':credit_days', $html);
        $this->assertStringNotContainsString(':claim_days', $html);

        $this->withCookie('bahasa', 'id')->get('/')->assertOk()->assertSee('45 hari')->assertSee('5 hari kerja');
    }

    public function test_the_whatsapp_button_dials_the_digits_and_hides_without_a_number(): void
    {
        app(SiteSettings::class)->set('contact.whatsapp', '+62 812-3456-7890');
        $this->get('/tentang-kami')->assertOk()
            ->assertSee('href="https://wa.me/6281234567890"', false)
            ->assertSee('site-whatsapp', false);
        $this->get('/')->assertOk()->assertSee('site-hero__whatsapp', false);

        app(SiteSettings::class)->forget();
        \App\Client\Models\SiteSetting::query()->delete();
        config(['site.contact.whatsapp' => '']);
        $this->get('/tentang-kami')->assertOk()->assertDontSee('site-whatsapp', false)->assertDontSee('wa.me', false);
    }

    public function test_the_cookie_notice_waits_hidden_for_the_script_and_the_privacy_policy_names_its_storage(): void
    {
        $this->withCookie('bahasa', 'en')->get('/')->assertOk()
            ->assertSee('data-site-notice hidden', false)
            ->assertSee('Only technical cookies')
            ->assertSee('href="'.route('site.privacy').'"', false);
        $this->get('/kebijakan-privasi')->assertOk()->assertSee('penyimpanan lokal');
    }

    public function test_the_sign_in_page_offers_the_portal_and_the_panel(): void
    {
        $this->get('/masuk')->assertOk()
            ->assertSee('href="'.url('/portal/login').'"', false)
            ->assertSee('href="'.url('/admin/login').'"', false);
    }

    public function test_robots_blocks_the_panels_and_the_sitemap_lists_the_public_pages_only(): void
    {
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin')->assertSee('Disallow: /portal')->assertSee('Sitemap: '.url('/sitemap.xml'));

        $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        foreach (Sitemap::ROUTES as $name) {
            $sitemap->assertSee('<loc>'.route($name).'</loc>', false);
        }
        $this->assertSame(count(Sitemap::ROUTES), substr_count($sitemap->getContent(), '<loc>'));
        $sitemap->assertDontSee('/admin')->assertDontSee('/portal');
    }
}
