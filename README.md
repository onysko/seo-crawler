# SEO Crawler
Fast and easy tool for crawling buggy links from your sitemap.

Just clone this repo, run ```php crawler.php http://example.com/sitemap.xml```, check created logs and improve your SEO quality.

## Configuration
This module has few simple parameters. Using them you can manage speed of crawling and also include/exclude some reports.

Let's check them:

```errorLog``` - boolean value for including/excluding error reports. (it means checking links to pages with 4XX or 5XX codes).

```redirectLog``` - boolean value for including/excluding redirect reports. (it means checking links to pages with 3XX codes).

```internalOnly``` - boolean value for including/excluding external links checking.

```streamCount``` - integer value to define count of parallel parsing streams. By default is 10, but can be set higher to improve crawling speed.

```excludeList``` - array of link patterns that crawler must exclude. It could be defined like ['/meta/', '/search/'] etc.
