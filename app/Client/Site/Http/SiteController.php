<?php

declare(strict_types=1);

namespace App\Client\Site\Http;

use App\Client\Models\SiteImage;
use App\Client\Site\Copy;
use App\Client\Site\Legal;
use App\Domain\Shared\Locales;
use App\Models\Company\Branch;
use App\Models\Company\PaymentTerm;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/** The public pages. Open, indexed, bilingual; no price anywhere. */
class SiteController
{
    public function home(): View
    {
        $categories = Copy::records('categories');
        $branches = $this->branches();
        $term = PaymentTerm::default();

        return view('client.site.home', [
            'promos' => SiteImage::query()->live(SiteImage::PROMO)->get(),
            'photos' => SiteImage::query()->live(SiteImage::PHOTO)->get(),
            'categories' => $categories,
            'partners' => array_slice(Copy::records('partners'), 0, 3),
            'branches' => $branches,
            'facts' => $this->facts(count($categories), $branches->count(), $term),
            'faq' => $this->faq($term),
        ]);
    }

    /**
     * The company at a glance, read from the system rather than typed: what it sells, the
     * brands, the branches it ships from, and the credit term of the default payment term.
     *
     * @return list<array{value: string, label: string}>
     */
    private function facts(int $categories, int $branches, ?PaymentTerm $term): array
    {
        return array_values(array_filter([
            $categories > 0 ? ['value' => (string) $categories, 'label' => __('Product categories')] : null,
            ['value' => (string) count(Copy::list('brands')), 'label' => __('Brands')],
            $branches > 0 ? ['value' => (string) $branches, 'label' => __('Branches')] : null,
            $term && $term->due_days > 0 ? ['value' => __(':days days', ['days' => $term->due_days]), 'label' => __('Credit term')] : null,
        ]));
    }

    /**
     * The questions, their numbers filled from the system so they never drift from the terms
     * page: :credit_days from the default payment term (30 when none is set, the company's
     * standard), :claim_days from the terms of sale.
     *
     * @return list<array{question: string, answer: string}>
     */
    private function faq(?PaymentTerm $term): array
    {
        $numbers = [
            ':credit_days' => (string) ($term && $term->due_days > 0 ? $term->due_days : 30),
            ':claim_days' => (string) (int) Copy::value('legal.terms.claim_days'),
        ];

        return array_values(array_filter(array_map(fn (array $item): array => [
            'question' => trim((string) ($item['question'] ?? '')),
            'answer' => strtr(trim((string) ($item['answer'] ?? '')), $numbers),
        ], Copy::records('faq')), fn (array $item): bool => $item['question'] !== '' && $item['answer'] !== ''));
    }

    public function about(): View
    {
        return view('client.site.about');
    }

    public function partners(): View
    {
        return view('client.site.partners', ['partners' => Copy::records('partners')]);
    }

    public function roadmap(): View
    {
        return view('client.site.roadmap', ['items' => Copy::records('roadmap')]);
    }

    public function contact(): View
    {
        return view('client.site.contact', ['branches' => $this->branches()]);
    }

    /** An instrument under Indonesian law: the whole page in Bahasa Indonesia, whatever the visitor chose. */
    public function privacy(): Response
    {
        return $this->legal(fn (Legal $legal) => $legal->privacy(), 'site.privacy');
    }

    public function terms(): Response
    {
        return $this->legal(fn (Legal $legal) => $legal->terms(), 'site.terms');
    }

    /** Rendered inside the Indonesian locale, chrome included: a view object would render later, after the locale came back. */
    private function legal(callable $page, string $route): Response
    {
        return Locales::using('id', fn () => response(view('client.site.legal', ['page' => $page(app(Legal::class)), 'route' => $route])->render()));
    }

    /** Two doors: staff and buyers sign in on different guards against different tables. */
    public function signIn(): View
    {
        return view('client.site.sign-in', [
            'portalLogin' => Filament::getPanel('portal')->getLoginUrl(),
            'adminLogin' => Filament::getPanel('admin')->getLoginUrl(),
        ]);
    }

    /** @return Collection<int, Branch> */
    private function branches(): Collection
    {
        return Branch::query()->where('is_active', true)->orderBy('code')->orderBy('name')->get();
    }
}
