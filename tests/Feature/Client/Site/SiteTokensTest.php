<?php

namespace Tests\Feature\Client\Site;

use PHPUnit\Framework\TestCase;

/** One world: the site repeats the panel's tokens because its content policy keeps it apart; they may not drift, and no colour lives outside them. */
class SiteTokensTest extends TestCase
{
    public function test_every_token_the_site_shares_with_the_panel_has_the_panels_value(): void
    {
        $panel = $this->tokens('resources/css/filament/admin/theme.css');
        $site = $this->tokens('resources/css/site.css');

        $this->assertNotEmpty($site);
        foreach ($site as $token => $value) {
            $this->assertArrayHasKey($token, $panel, "{$token} is not one of the panel's tokens");
            $this->assertSame($panel[$token], $value, "{$token} has drifted from the panel");
        }
    }

    public function test_the_site_keeps_every_colour_in_its_tokens(): void
    {
        $rules = substr($this->css('resources/css/site.css'), (int) strpos($this->css('resources/css/site.css'), '@layer base'));

        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|\brgba?\(/i', $rules);
    }

    /**
     * The shared tokens of a stylesheet, "light --ae-canvas" and "dark --ae-canvas".
     *
     * @return array<string, string>
     */
    private function tokens(string $file): array
    {
        $tokens = [];
        $selectors = [];
        $buffer = '';

        foreach (str_split($this->css($file)) as $character) {
            if ($character === '{') {
                $selectors[] = trim($buffer);
                $buffer = '';
            } elseif ($character === '}') {
                $theme = preg_match('/\.dark\b|prefers-color-scheme:\s*dark/', implode(' ', $selectors)) === 1 ? 'dark' : 'light';
                preg_match_all('/(--(?:ae|duration|ease|scale)-[a-z0-9-]+)\s*:\s*([^;]+);/', $buffer, $declarations, PREG_SET_ORDER);
                foreach ($declarations as [, $name, $value]) {
                    $tokens["{$theme} {$name}"] = trim((string) preg_replace('/\s+/', ' ', $value));
                }
                array_pop($selectors);
                $buffer = '';
            } else {
                $buffer .= $character;
            }
        }

        return $tokens;
    }

    private function css(string $file): string
    {
        $css = (string) file_get_contents(dirname(__DIR__, 4).'/'.$file);

        return (string) preg_replace('~/\*.*?\*/~s', '', $css);
    }
}
