<?php namespace _\lot\x;

function feed($content) {
    global $language, $site, $url;
    $state = \State::get('x.feed', true);
    return \str_replace('</head>', '<link href="' . $url . $state['path']['sitemap'] . '" rel="sitemap" type="application/xml" title="' . $language->sitemapTitle(\w($site->title), true) . '"><link href="' . $url->clean . $state['path']['rss'] . '" rel="alternate" type="application/rss+xml" title="' . $language->rssTitle(\w($site->title), true) . '"></head>', $content);
}

// Insert some HTML `<link>` that maps to the feed resource
if (!\has(\array_values((array) \State::get('x.feed.path', true) ?? []), (string) \Path::B((string) $url->path))) {
    // Make sure to run the hook before `_\minify`
    \Hook::set('content', __NAMESPACE__ . "\\feed", 1.9);
}