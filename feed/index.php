<?php

namespace fn {
    function feed($yield) {
        global $config, $language, $url;
        $state = \Extend::state('feed');
        $out  = N;
        $out .= '<link href="' . $url . '/' . $state['path']['sitemap'] . '" rel="sitemap" type="application/xml" title="' . $language->sitemap__(\To::text($config->title), true) . '">' . N;
        $out .= '<link href="' . $url->clean . '/' . $state['path']['rss'] . '" rel="alternate" type="application/rss+xml" title="' . $language->rss__(\To::text($config->title), true) . '">' . N;
        return \str_replace('</head>', $out . '</head>', $yield);
    }
}

namespace {

    $state = \Extend::state('feed');
    $tag = \Extend::exist('tag') ? \Extend::state('tag') : false;

    \Route::lot('%*%', function($path) use($config, $state, $tag, $url) {

        $out = "";
        $type = 'text/plain';
        $n = \explode('/', $path);
        $n = \array_pop($n);
        $chunk = \HTTP::get('chunk', 25);
        $sort = \extend([-1, 'time'], (array) \HTTP::get('sort', []));
        $step = \HTTP::get('step', 1);
        $fn = \HTTP::get('fn');
        $directory = \rtrim(PAGE . DS . \Path::D($path), DS);
        $test = \defined('DEBUG') && DEBUG === EXTEND . DS . 'feed';
        $version = \Mecha::version();

        // `/sitemap.xml`
        if (!empty($state['path']['sitemap']) && $path === $state['path']['sitemap']) {
            $folders = \File::explore([PAGE, 0], true, []);
            !$test && ($type = 'application/' . ($fn ? 'javascript' : 'xml'));
            $out .= '<?xml version="1.0" encoding="UTF-8"?>';
            $out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            if ($folders) {
                foreach (\array_keys($folders) as $v) {
                    if (!\glob($v . DS . '*{page,archive}', \GLOB_BRACE | \GLOB_NOSORT) || \File::exist([
                        $v. DS . '$.page',
                        $v. DS . '$.archive'
                    ])) {
                        continue;
                    }
                    $exist = \File::exist([
                        $v . '.page',
                        $v . '.archive'
                    ]);
                    $out .= '<sitemap>';
                    $out .= '<loc>' . $url . '/' . \Path::R($v, PAGE, '/') . '/' . $state['path']['sitemap'] . '</loc>';
                    $out .= '<lastmod>' . (new \Date($exist ? \filemtime($exist) : null))->ISO8601 . '</lastmod>';
                    $out .= '</sitemap>';
                }
            }
            $out .= '</sitemapindex>';
        } else if ($page = \File::exist([
            $directory . '.page',
            $directory . '.archive',
            $directory . DS . $config->path . '.page',
            $directory . DS . $config->path . '.archive',
        ])) {
            $page = new \Page($page);
            $t = (new \Date)->format('r');
            // `/foo/sitemap.xml`
            // `/foo/bar/sitemap.xml`
            if ($path && $n === $state['path']['sitemap']) {
                $folders = \File::explore([\Path::F($page->path), 0], true, []);
                !$test && ($type = 'application/' . ($fn ? 'javascript' : 'xml'));
                $out .= '<?xml version="1.0" encoding="UTF-8"?>';
                $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
                if ($folders) {
                    foreach (\array_keys($folders) as $v) {
                        $out .= '<url>';
                        $out .= '<loc>' . $url . '/' . ($r = \Path::R($v, PAGE, '/')) . '</loc>';
                        $level = \b(1 - (\substr_count($r, '/') * .1), .5, 1); // `0.5` to `1.0`
                        $exist = \File::exist([
                            $v . '.page',
                            $v . '.archive'
                        ]);
                        $out .= '<lastmod>' . (new \Date($exist ? \filemtime($exist) : null))->ISO8601 . '</lastmod>';
                        $out .= '<changefreq>monthly</changefreq>';
                        $out .= '<priority>' . $level . '</priority>';
                        $out .= '</url>';
                    }
                }
                $out .= '</urlset>';
            // `/foo/feed.rss`
            // `/foo/bar/feed.rss`
            } else if (!empty($state['path']['rss']) && $n === $state['path']['rss']) {
                !$test && ($type = 'application/' . ($fn ? 'javascript' : 'rss+xml'));
                $out .= '<?xml version="1.0" encoding="UTF-8"?>';
                $out .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">';
                $out .= '<channel>';
                $out .= '<generator>Mecha ' . $version . '</generator>';
                $out .= '<title><![CDATA[' . ($page->title ? $page->title . ' | ' : "") . $config->title . ']]></title>';
                $out .= '<link>' . \trim($url . '/' . $path, '/') . '</link>';
                $out .= '<description><![CDATA[' . ($page->description ?: $config->description) . ']]></description>';
                $out .= '<lastBuildDate>' . $t . '</lastBuildDate>';
                $out .= '<language>' . $config->language . '</language>';
                $out .= '<atom:link href="' . $url->clean . \HTTP::query([
                    'chunk' => $chunk,
                    'sort' => $sort,
                    'step' => $step
                ]) . '" rel="self"/>';
                $pages = \Get::pages($directory, 'page', $sort, 'path');
                $pages = \array_chunk($pages->vomit(), $chunk);
                if ($step > 1) {
                    $out .= '<atom:link href="' . $url->clean . \HTTP::query([
                        'chunk' => $chunk,
                        'sort' => $sort,
                        'step' => $step - 1
                    ]) . '" rel="previous"/>';
                }
                if (!empty($pages[$step])) {
                    $out .= '<atom:link href="' . $url->clean . \HTTP::query([
                        'chunk' => $chunk,
                        'sort' => $sort,
                        'step' => $step + 1
                    ]) . '" rel="next"/>';
                }
                if (!empty($pages[$step - 1])) {
                    foreach ($pages[$step - 1] as $page) {
                        $page = new \Page($page);
                        $out .= '<item>';
                        $out .= '<title><![CDATA[' . $page->title . ']]></title>';
                        $out .= '<link>' . $page->url . '</link>';
                        $out .= '<description><![CDATA[' . $page->description . ']]></description>';
                        $out .= '<pubDate>' . $page->time->format('r') . '</pubDate>';
                        $out .= '<guid>' . $page->url . '</guid>';
                        if ($tag && $kinds = (array) $page->kind) {
                            foreach ($kinds as $k) {
                                $v = \To::tag($k);
                                if ($f = \File::exist([
                                    TAG . DS . $v . '.page',
                                    TAG . DS . $v . '.archive'
                                ])) {
                                    $out .= '<category domain="' . $url->clean . '/' . $tag->path . '/' . $v . '"><![CDATA[' . (new \Tag($f))->title . ']]></category>';
                                }
                            }
                        }
                        $out .= '</item>';
                    }
                } else {
                    $out .= '<item>';
                    $out .= '<title><![CDATA[' . $page->title . ']]></title>';
                    $out .= '<link>' . $page->url . '</link>';
                    $out .= '<description><![CDATA[' . $page->description . ']]></description>';
                    $out .= '<pubDate>' . $page->time->format('r') . '</pubDate>';
                    $out .= '<guid>' . $page->url . '</guid>';
                    if ($tag && $kinds = (array) $page->kind) {
                        foreach ($kinds as $k) {
                            $v = \To::tag($k);
                            if ($f = \File::exist([
                                TAG . DS . $v . '.page',
                                TAG . DS . $v . '.archive'
                            ])) {
                                $out .= '<category domain="' . $url->clean . '/' . $tag->path . '/' . $v . '"><![CDATA[' . (new \Tag($f))->title . ']]></category>';
                            }
                        }
                    }
                    $out .= '</item>';
                }
                $out .= '</channel>';
                $out .= '</rss>';
            // `/foo/feed.json`
            // `/foo/bar/feed.json`
            } else if (!empty($state['path']['json']) && $n === $state['path']['json']) {
                !$test && ($type = 'application/' . ($fn ? 'javascript' : 'json'));
                $json = [
                    0 => [
                        'generator' => 'Mecha ' . $version,
                        'title' => ($page->title ? $page->title . ' | ' : "") . $config->title,
                        'url' => \trim($url . '/' . $path, '/'),
                        'current' => $url->clean . \HTTP::query([
                            'chunk' => $chunk,
                            'sort' => $sort,
                            'step' => $step
                        ]),
                        'description' => $page->description ?: $config->description,
                        'time' => $page->time . "",
                        'update' => \date(DATE_WISE, \strtotime($t)),
                        'language' => $config->language
                    ],
                    1 => []
                ];
                if ($tag && $tags = \glob(TAG . DS . '*{page,archive}', \GLOB_BRACE | \GLOB_NOSORT)) {
                    $json[0]['tags'] = [];
                    foreach ($tags as $v) {
                        $page = new \Tag($v);
                        $json[0]['tags'][$page->slug] = \is($page->get([
                            'title' => null,
                            'description' => null,
                            'time' => null,
                            'id' => 0
                        ]), function($v) {
                            return $v !== null;
                        });
                    }
                    \ksort($json[0]['tags']);
                }
                $pages = \Get::pages($directory, 'page', $sort, 'path');
                $pages = \array_chunk($pages->vomit(), $chunk);
                if ($step > 1) {
                    $json[0]['previous'] = $url->clean . \HTTP::query([
                        'chunk' => $chunk,
                        'sort' => $sort,
                        'step' => $step - 1
                    ]);
                }
                if (!empty($pages[$step])) {
                    $json[0]['next'] = $url->clean . \HTTP::query([
                        'chunk' => $chunk,
                        'sort' => $sort,
                        'step' => $step + 1
                    ]);
                }
                if (!empty($pages[$step - 1])) {
                    foreach ($pages[$step - 1] as $page) {
                        $page = new \Page($page);
                        $json[1][] = \is([
                            'title' => $page->title,
                            'url' => $page->url,
                            'link' => $page->link,
                            'description' => $page->description,
                            'time' => $page->time . "",
                            'kind' => $tag ? (array) $page->kind : null,
                            'id' => $page->id
                        ], function($v) {
                            return $v !== null;
                        });
                    }
                } else {
                    $json[1][0] = \is([
                        'title' => $page->title,
                        'url' => $page->url,
                        'link' => $page->link,
                        'description' => $page->description,
                        'time' => $page->time . "",
                        'kind' => $tag ? (array) $page->kind : null,
                        'id' => $page->id
                    ], function($v) {
                        return $v !== null;
                    });
                }
                $out = $fn ? $json : \json_encode($json);
            }
        }

        if ($out) {
            $i = 60 * 60 * 24; // 1 Day
            \HTTP::status(200)->header([
                'Pragma' => 'private',
                'Cache-Control' => 'private, max-age=' . $i,
                'Expires' => \gmdate('D, d M Y H:i:s', \time() + $i) . ' GMT'
            ])->type($type, $config->charset);
            echo $fn ? $fn . '(' . \json_encode($out) . ');' : $out;
            return $out;
        }

    });

    // Insert some HTML `<link>` that maps to the feed resource
    if (!\has(\array_values($state['path']), \Path::B($url->path))) {
        // Make sure to run the hook before `fn\minify`
        \Hook::set('shield.yield', "fn\\feed", 1.9);
    }

}