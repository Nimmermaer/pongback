<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Sending;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webicorns\Pongback\Sending\LinkExtractor;

final class LinkExtractorTest extends TestCase
{
    private const PAGE = 'https://www.my-site.example/blog/post';
    private const OWN_HOSTS = ['www.my-site.example', 'shop.my-site.example'];

    #[Test]
    public function returnsExternalHttpLinksWithoutFragmentsAndDuplicates(): void
    {
        $html = '<body><p>'
            . '<a href="https://other.example/article#comments">a</a>'
            . '<a href="https://OTHER.example/article/">duplicate</a>'
            . '<a href="http://second.example/?page=2">b</a>'
            . '<a href="/blog/other-post">own, relative</a>'
            . '<a href="https://shop.my-site.example/">own, other site</a>'
            . '<a href="#top">anchor</a>'
            . '<a href="mailto:me@other.example">mail</a>'
            . '<a href="javascript:alert(1)">js</a>'
            . '<a href="ftp://files.example/file.zip">ftp</a>'
            . '<a href=" https://third.example/page ">whitespace</a>'
            . '<a href="HTTPS://WWW.MY-SITE.EXAMPLE/upper">own, upper case</a>'
            . '<a href="">empty</a>'
            . '<a>no href</a>'
            . '</p></body>';

        self::assertSame(
            ['https://other.example/article', 'http://second.example/?page=2', 'https://third.example/page'],
            (new LinkExtractor())->extract($html, self::PAGE, self::OWN_HOSTS),
        );
    }

    #[Test]
    public function acceptsUpperCaseSchemes(): void
    {
        self::assertCount(1, (new LinkExtractor())->extract('<a href="HTTPS://other.example/">x</a>', self::PAGE, self::OWN_HOSTS));
    }

    #[Test]
    public function resolvesProtocolRelativeLinks(): void
    {
        self::assertSame(
            ['https://cdn.example/page'],
            (new LinkExtractor())->extract('<a href="//cdn.example/page">x</a>', self::PAGE, self::OWN_HOSTS),
        );
    }

    #[Test]
    public function onlyLooksAtTheMainContentIfThePageMarksItUp(): void
    {
        $html = '<body><nav><a href="https://social.example/me">nav</a></nav>'
            . '<main><a href="https://cited.example/source">content</a></main>'
            . '<footer><a href="https://partner.example/">footer</a></footer></body>';

        self::assertSame(['https://cited.example/source'], (new LinkExtractor())->extract($html, self::PAGE, self::OWN_HOSTS));
    }

    #[Test]
    public function fallsBackToArticleAndThenToTheBody(): void
    {
        $withArticle = '<body><a href="https://nav.example/">nav</a><article><a href="https://cited.example/">x</a></article></body>';
        $withoutBoth = '<body><a href="https://nav.example/">nav</a><div><a href="https://cited.example/">x</a></div></body>';

        self::assertSame(['https://cited.example/'], (new LinkExtractor())->extract($withArticle, self::PAGE, self::OWN_HOSTS));
        self::assertSame(['https://nav.example/', 'https://cited.example/'], (new LinkExtractor())->extract($withoutBoth, self::PAGE, self::OWN_HOSTS));
    }

    #[Test]
    public function ignoresLinksInScriptsAndTemplates(): void
    {
        $html = '<main><script>document.write(\'<a href="https://tracker.example/">x</a>\')</script>'
            . '<template><a href="https://template.example/">x</a></template>'
            . '<a href="https://real.example/">x</a></main>';

        self::assertSame(['https://real.example/'], (new LinkExtractor())->extract($html, self::PAGE, self::OWN_HOSTS));
    }

    #[Test]
    public function comparesOwnHostsCaseInsensitive(): void
    {
        self::assertSame([], (new LinkExtractor())->extract('<a href="https://WWW.My-Site.Example./x">x</a>', self::PAGE, ['WWW.MY-SITE.EXAMPLE']));
    }

    #[Test]
    public function limitsTheNumberOfLinks(): void
    {
        $html = '';
        for ($i = 1; $i <= LinkExtractor::MAX_LINKS + 10; $i++) {
            $html .= '<a href="https://site' . $i . '.example/">' . $i . '</a>';
        }

        $links = (new LinkExtractor())->extract($html, self::PAGE, self::OWN_HOSTS);

        self::assertCount(LinkExtractor::MAX_LINKS, $links);
        self::assertSame('https://site1.example/', $links[0]);
        self::assertSame('https://site' . LinkExtractor::MAX_LINKS . '.example/', $links[LinkExtractor::MAX_LINKS - 1]);
    }

    #[Test]
    public function returnsNothingForEmptyDocuments(): void
    {
        self::assertSame([], (new LinkExtractor())->extract('', self::PAGE, self::OWN_HOSTS));
    }
}
