<?php namespace _\lot\x\feed;

function json($any = null) {
    extract($GLOBALS, \EXTR_SKIP);
    $t = $_SERVER['REQUEST_TIME'];
    $f = \LOT . \DS . 'page' . \DS . ($any ?? \trim($state->path, '/'));
    $page = new \Page(\File::exist([
        $f . '.page',
        $f . '.archive'
    ]) ?: null);
    $exist = $page->exist;
    $chunk = \Get::get('chunk') ?? 25;
    $i = \Get::get('i') ?? 1;
    $sort = \Get::get('sort') ?? [-1, 'time'];
    $fn = \Get::get('fn');
    $images = null !== \State::get('x.image');
    $tags = null !== \State::get('x.tag');
    $out = [
        0 => [
            'generator' => 'Mecha ' . \VERSION,
            'title' => ($exist ? $page->title : \i('Error')) . ' | ' . $state->title,
            'url' => $url . $url->path,
            'current' => $url->clean . $url->query('&', [
                'chunk' => $chunk,
                'i' => $i,
                'sort' => $sort
            ]),
            'description' => \strtr($page->description ?? $state->description, [
                '<p>' => "",
                '</p>' => ""
            ]) ?: null,
            'time' => (string) $page->time,
            'language' => $state->language
        ],
        1 => null
    ];
    if ($tags) {
        $out[0]['tags'] = [];
        foreach (\g(\LOT . \DS . 'tag', 'page') as $k => $v) {
            $tag = new \Tag($k);
            $out[0]['tags'][$tag->name] = [
                'title' => $tag->title,
                'description' => \strtr($tag->description, [
                    '<p>' => "",
                    '</p>' => ""
                ]) ?: null,
                'time' => (string) $tag->time,
                'id' => $tag->id
            ];
            \ksort($out[0]['tags']);
        }
    }
    $pages = \Pages::from($f, 'page')->sort($sort);
    $pages = \array_chunk($pages->get(), $chunk);
    if ($i > 1) {
        $out[0]['prev'] = $url->clean . $url->query('&', [
            'chunk' => $chunk,
            'i' => $i - 1,
            'sort' => $sort
        ]);
    }
    if (!empty($pages[$i])) {
        $out[0]['next'] = $url->clean . $url->query('&', [
            'chunk' => $chunk,
            'i' => $i + 1,
            'sort' => $sort
        ]);
    }
    if (!empty($pages[$i - 1])) {
        $out[1] = [];
        foreach ($pages[$i - 1] as $k => $page) {
            $page = new \Page($page);
            $out[1][$k] = [
                'title' => $page->title,
                'description' => \strtr($page->description, [
                    '<p>' => "",
                    '</p>' => ""
                ]) ?: null,
                'image' => $images ? $page->image(72, 72) : null,
                'link' => $page->link,
                'url' => $page->url,
                'time' => (string) $page->time,
                'id' => $page->id
            ];
            if ($tags) {
                $out[1][$k]['kind'] = (array) $page->kind;
            }
        }
    } else if ($exist) {
        $out[1] = [];
        $out[1][0] = [
            'title' => $page->title,
            'description' => \strtr($page->description, [
                '<p>' => "",
                '</p>' => ""
            ]) ?: null,
            'image' => $images ? $page->image(72, 72) : null,
            'link' => $page->link,
            'url' => $page->url,
            'time' => (string) $page->time,
            'id' => $page->id
        ];
        if ($tags) {
            $out[1][0]['kind'] = (array) $page->kind;
        }
    } else {
        $this->status(404);
    }
    $i = 60 * 60 * 24; // Cache output for a day
    $this->lot($exist ? [
        'Cache-Control' => 'max-age=' . $i . ', private',
        'Expires' => \gmdate('D, d M Y H:i:s', $t + $i) . ' GMT',
        'Pragma' => 'private'
    ] : [
        'Cache-Control' => 'max-age=0, must-revalidate, no-cache, no-store',
        'Expires' => '0',
        'Pragma' => 'no-cache'
    ]);
    $this->type('application/' . ($fn ? 'javascript' : 'json'));
    $this->content(($fn ? $fn . '(' : "") . \json_encode($out, \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_AMP | \JSON_UNESCAPED_UNICODE) . ($fn ? ');' : ""));
}

function xml($any = null) {
    extract($GLOBALS, \EXTR_SKIP);
    $t = $_SERVER['REQUEST_TIME'];
    $f = \LOT . \DS . 'page' . \DS . ($any ?? \trim($state->path, '/'));
    $page = new \Page(\File::exist([
        $f . '.page',
        $f . '.archive'
    ]) ?: null);
    $exist = $page->exist;
    $chunk = \Get::get('chunk') ?? 25;
    $i = \Get::get('i') ?? 1;
    $sort = \Get::get('sort') ?? [-1, 'time'];
    $fn = \Get::get('fn');
    $images = null !== \State::get('x.image');
    $tags = null !== \State::get('x.tag');
    $out = "";
    $out .= '<?xml version="1.0" encoding="UTF-8"?>';
    $out .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">';
    $out .= '<channel>';
    $out .= '<generator>Mecha ' . \VERSION . '</generator>';
    $out .= '<title><![CDATA[' . ($exist ? $page->title : \i('Error')) . ' | ' . $state->title . ']]></title>';
    $out .= '<link>' . $url . $url->path . '</link>';
    $out .= '<description><![CDATA[' . \strtr($page->description ?? $state->description, [
        '<p>' => "",
        '</p>' => ""
    ]) . ']]></description>';
    $out .= '<lastBuildDate>' . \date('r', $t) . '</lastBuildDate>';
    $out .= '<language>' . $state->language . '</language>';
    $out .= '<atom:link href="' . $url->clean . $url->query('&amp;', [
        'chunk' => $chunk,
        'i' => $i,
        'sort' => $sort
    ]) . '" rel="self"/>';
    $pages = \Pages::from($f, 'page')->sort($sort);
    $pages = \array_chunk($pages->get(), $chunk);
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
        foreach ($pages[$i - 1] as $k => $v) {
            $page = new \Page($v);
            $out .= '<item>';
            $out .= '<title><![CDATA[' . $page->title . ']]></title>';
            $out .= '<link>' . $page->url . '</link>';
            $out .= '<description><![CDATA[' . \strtr($page->description, [
                '<p>' => "",
                '</p>' => ""
            ]) . ']]></description>';
            $out .= '<pubDate>' . $page->time->format('r') . '</pubDate>';
            $out .= '<guid>' . $page->url . '</guid>';
            if ($images && $image = $page->image(72, 72)) {
                $out .= '<image>';
                $out .= '<title>' . \basename($link = $page->image) . '</title>';
                $out .= '<url>' . $image . '</url>';
                $out .= '<link>' . $link . '</link>';
                $out .= '</image>';
            }
            if ($tags) {
                foreach ($page->tags as $tag) {
                    $out .= '<category domain="' . $tag->url . '"><![CDATA[' . $tag->title . ']]></category>';
                }
            }
            $out .= '</item>';
        }
    } else if ($exist) {
        $out .= '<item>';
        $out .= '<title><![CDATA[' . $page->title . ']]></title>';
        $out .= '<link>' . $page->url . '</link>';
        $out .= '<description><![CDATA[' . \strtr($page->description, [
            '<p>' => "",
            '</p>' => ""
        ]) . ']]></description>';
        $out .= '<pubDate>' . $page->time->format('r') . '</pubDate>';
        $out .= '<guid>' . $page->url . '</guid>';
        if ($images && $image = $page->image(72, 72)) {
            $out .= '<image>';
            $out .= '<title>' . \basename($link = $page->image) . '</title>';
            $out .= '<url>' . $image . '</url>';
            $out .= '<link>' . $link . '</link>';
            $out .= '</image>';
        }
        if ($tags) {
            foreach ($page->tags as $tag) {
                $out .= '<category domain="' . $tag->url . '"><![CDATA[' . $tag->title . ']]></category>';
            }
        }
        $out .= '</item>';
    } else {
        $this->status(404);
    }
    $out .= '</channel>';
    $out .= '</rss>';
    $i = 60 * 60 * 24; // Cache output for a day
    $this->lot($exist ? [
        'Cache-Control' => 'max-age=' . $i . ', private',
        'Expires' => \gmdate('D, d M Y H:i:s', $t + $i) . ' GMT',
        'Pragma' => 'private'
    ] : [
        'Cache-Control' => 'max-age=0, must-revalidate, no-cache, no-store',
        'Expires' => '0',
        'Pragma' => 'no-cache'
    ]);
    $this->type('application/' . ($fn ? 'javascript' : 'rss+xml'));
    $this->content($fn ? $fn . '(' . \json_encode($out, \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_AMP | \JSON_UNESCAPED_UNICODE) . ');' : $out);
}

\Route::set(['feed.json', '*/feed.json'], __NAMESPACE__ . "\\json", 10);
\Route::set(['feed.xml', '*/feed.xml'], __NAMESPACE__ . "\\xml", 10);
