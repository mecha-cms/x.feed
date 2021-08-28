<?php namespace x;

function feed($content) {
    extract($GLOBALS, \EXTR_SKIP);
    return \strtr($content, ['</head>' => '<link href="' . $url->clean . '/feed.xml" rel="alternate" type="application/rss+xml" title="' . \i('RSS') . ' | ' . \w($state->title) . '"></head>']);
}

// Insert some HTML `<link>` that maps to the feed resource
if (!\has(['feed.json', 'feed.xml'], \basename($url->path))) {
    // Make sure to run the hook before `x\minify`
    \Hook::set('content', __NAMESPACE__ . "\\feed", 1.9);
}