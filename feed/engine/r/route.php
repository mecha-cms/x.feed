<?php

Route::over('*', function($form) use($config, $url) {

    $state = state('feed');
    $tag = state('tag');
    $p = state('page')['/'] ?? "";
    $out = "";
    $type = 'text/plain';
    $n = explode('/', $path = $this[0]);
    $n = array_pop($n);
    $chunk = $form['chunk'] ?? 25;
    $sort = array_replace([-1, 'time'], (array) ($form['sort'] ?? []));
    $i = $form['i'] ?? 1;
    $fn = $form['fn'] ?? null;
    $directory = rtrim(PAGE . DS . Path::D($path), DS);
    $test = defined('DEBUG') && DEBUG === X . DS . 'feed';

    // `./sitemap.xml`
    if (!empty($state['/']['sitemap']) && $path === $state['/']['sitemap']) {
        !$test && ($type = 'application/' . ($fn ? 'javascript' : 'xml'));
        $out .= '<?xml version="1.0" encoding="UTF-8"?>';
        $out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (g(PAGE, 0, true) as $k => $v) {
            if (!glob($k . DS . '*{page,archive}', GLOB_BRACE | GLOB_NOSORT) || File::exist([
                $k . DS . '.page',
                $k . DS . '.archive'
            ])) {
                continue;
            }
            $exist = File::exist([
                $k . '.page',
                $k . '.archive'
            ]);
            $out .= '<sitemap>';
            $out .= '<loc>' . $url . '/' . Path::R($k, PAGE, '/') . '/' . $state['/']['sitemap'] . '</loc>';
            $out .= '<lastmod>' . (new Date($exist ? filemtime($exist) : time()))->ISO8601 . '</lastmod>';
            $out .= '</sitemap>';
        }
        $out .= '</sitemapindex>';
    } else if ($page = File::exist([
        $directory . '.page',
        $directory . '.archive',
        $directory . DS . $p . '.page',
        $directory . DS . $p . '.archive',
    ])) {
        $page = new Page($page);
        $t = (new Date(time()))->format('r');
        // `./foo/sitemap.xml`
        // `./foo/bar/sitemap.xml`
        if ($path && $n === $state['/']['sitemap']) {
            !$test && ($type = 'application/' . ($fn ? 'javascript' : 'xml'));
            $out .= '<?xml version="1.0" encoding="UTF-8"?>';
            $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach (g($directory, 0, true) as $k => $v) {
                $out .= '<url>';
                $out .= '<loc>' . $url . '/' . ($r = Path::R($k, PAGE, '/')) . '</loc>';
                $level = b(1 - (substr_count($r, '/') * .1), .5, 1); // `0.5` to `1.0`
                $exist = File::exist([
                    $k . '.page',
                    $k . '.archive'
                ]);
                $out .= '<lastmod>' . (new Date($exist ? filemtime($exist) : null))->ISO8601 . '</lastmod>';
                $out .= '<changefreq>monthly</changefreq>';
                $out .= '<priority>' . $level . '</priority>';
                $out .= '</url>';
            }
            $out .= '</urlset>';
        // `./foo/feed.rss`
        // `./foo/bar/feed.rss`
        } else if (!empty($state['/']['rss']) && $n === $state['/']['rss']) {
            !$test && ($type = 'application/' . ($fn ? 'javascript' : 'rss+xml'));
            $out .= '<?xml version="1.0" encoding="UTF-8"?>';
            $out .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">';
            $out .= '<channel>';
            $out .= '<generator>Mecha ' . VERSION . '</generator>';
            $out .= '<title><![CDATA[' . ($page->title ? $page->title . ' | ' : "") . $config->title . ']]></title>';
            $out .= '<link>' . trim($url . '/' . $path, '/') . '</link>';
            $out .= '<description><![CDATA[' . ($page->description ?? $config->description) . ']]></description>';
            $out .= '<lastBuildDate>' . $t . '</lastBuildDate>';
            $out .= '<language>' . $config->language . '</language>';
            $out .= '<atom:link href="' . $url->clean . $url->query('&amp;', [
                'chunk' => $chunk,
                'i' => $i,
                'sort' => $sort
            ]) . '" rel="self"/>';
            $pages = Pages::from($directory)->sort($sort);
            $pages = array_chunk($pages->get(), $chunk);
            if ($i > 1) {
                $out .= '<atom:link href="' . $url->clean . $url->query('&amp;', [
                    'chunk' => $chunk,
                    'i' => $i - 1,
                    'sort' => $sort
                ]) . '" rel="prev"/>';
            }
            if (!empty($pages[$i])) {
                $out .= '<atom:link href="' . $url->clean . $url->query('&amp;', [
                    'chunk' => $chunk,
                    'i' => $i + 1,
                    'sort' => $sort
                ]) . '" rel="next"/>';
            }
            if (!empty($pages[$i - 1])) {
                foreach ($pages[$i - 1] as $page) {
                    $page = new Page($page);
                    $out .= '<item>';
                    $out .= '<title><![CDATA[' . $page->title . ']]></title>';
                    $out .= '<link>' . $page->url . '</link>';
                    $out .= '<description><![CDATA[' . $page->description . ']]></description>';
                    $out .= '<pubDate>' . $page->time->format('r') . '</pubDate>';
                    $out .= '<guid>' . $page->url . '</guid>';
                    if ($tag && $kinds = (array) $page->kind) {
                        foreach ($kinds as $k) {
                            $v = To::tag($k);
                            if ($f = File::exist([
                                TAG . DS . $v . '.page',
                                TAG . DS . $v . '.archive'
                            ])) {
                                $out .= '<category domain="' . $url->clean . '/' . $tag->path . '/' . $v . '"><![CDATA[' . (new Tag($f))->title . ']]></category>';
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
                if ($tag !== null && $tags = $page->tags) {
                    foreach ($tags as $v) {
                        $out .= '<category domain="' . $v->url . '"><![CDATA[' . $v->title . ']]></category>';
                    }
                }
                $out .= '</item>';
            }
            $out .= '</channel>';
            $out .= '</rss>';
        // `./foo/feed.json`
        // `./foo/bar/feed.json`
        } else if (!empty($state['/']['json']) && $n === $state['/']['json']) {
            !$test && ($type = 'application/' . ($fn ? 'javascript' : 'json'));
            $json = [
                0 => [
                    'generator' => 'Mecha ' . VERSION,
                    'title' => ($page->title ? $page->title . ' | ' : "") . $config->title,
                    'url' => trim($url . '/' . $path, '/'),
                    'current' => $url->clean . $url->query('&', [
                        'chunk' => $chunk,
                        'i' => $i,
                        'sort' => $sort
                    ]),
                    'description' => $page->description ?? $config->description,
                    'time' => (string) $page->time,
                    'update' => date('Y-m-d H:i:s', strtotime($t)),
                    'language' => $config->language
                ],
                1 => []
            ];
            if ($tag !== null && $tags = glob(TAG . DS . '*{page,archive}', GLOB_BRACE | GLOB_NOSORT)) {
                $json[0]['tags'] = [];
                foreach ($tags as $v) {
                    $v = new Tag($v);
                    $json[0]['tags'][$v->name] = is($v->get([
                        'title' => null,
                        'description' => null,
                        'time' => null,
                        'id' => 0
                    ]), function($v) {
                        return $v !== null;
                    });
                }
                ksort($json[0]['tags']);
            }
            $pages = Pages::from($directory)->sort($sort);
            $pages = array_chunk($pages->get(), $chunk);
            if ($i > 1) {
                $json[0]['prev'] = $url->clean . $url->query('&', [
                    'chunk' => $chunk,
                    'i' => $i - 1,
                    'sort' => $sort
                ]);
            }
            if (!empty($pages[$i])) {
                $json[0]['next'] = $url->clean . $url->query('&', [
                    'chunk' => $chunk,
                    'i' => $i + 1,
                    'sort' => $sort
                ]);
            }
            if (!empty($pages[$i - 1])) {
                foreach ($pages[$i - 1] as $page) {
                    $page = new Page($page);
                    $json[1][] = is([
                        'title' => $page->title,
                        'url' => $page->url,
                        'link' => $page->link,
                        'description' => $page->description,
                        'time' => (string) $page->time,
                        'kind' => $tag !== null ? (array) $page->kind : null,
                        'id' => $page->id
                    ], function($v) {
                        return $v !== null;
                    });
                }
            } else {
                $json[1][0] = is([
                    'title' => $page->title,
                    'url' => $page->url,
                    'link' => $page->link,
                    'description' => $page->description,
                    'time' => (string) $page->time,
                    'kind' => $tag ? (array) $page->kind : null,
                    'id' => $page->id
                ], function($v) {
                    return $v !== null;
                });
            }
            $out = $fn ? $json : json_encode($json);
        }
    }

    if ($out) {
        $i = 60 * 60 * 24; // 1 Day
        $this->status(200);
        $this->type($type, ['charset' => $config->charset]);
        $this->header([
            'Pragma' => 'private',
            'Cache-Control' => 'private, max-age=' . $i,
            'Expires' => gmdate('D, d M Y H:i:s', time() + $i) . ' GMT'
        ]);
        $this->_content($fn ? $fn . '(' . json_encode($out) . ');' : $out);
    }

});
