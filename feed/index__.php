<?php

function fn_rss_item($page) {
    $date = $page->date;
    echo '<item>';
    echo '<title><![CDATA[' . To::text($page->title) . ']]></title>';
    echo '<link>' . $page->url . '</link>';
    echo '<description><![CDATA[' . To::text($page->description) . ']]></description>';
    echo '<pubDate>' . $date('r') . '</pubDate>';
    echo '<guid>' . $page->url . '</guid>';
    echo '</item>';
}

Route::hook('%*%', function($path) use($config, $url) {
    $p = explode('/', $path);
    $p = array_pop($p);
    $path = rtrim(PAGE . DS . Path::D($path), DS);
    $state = Extend::state(__DIR__);
    $slug = $state['slug'];
    if ($page = File::exist([
        $path . '.page',
        $path . DS . Path::B($path) . '.page',
        $path . DS . $config->slug . '.page',
        $path . DS . Path::B($path) . DS . $config->slug . '.page'
    ])) {
        $page = new Page($page);
        $t = (new Date())->format('r');
        if ($p === $slug['rss']) {
            HTTP::mime('text/xml', $config->charset);
            echo '<?xml version="1.0" encoding="UTF-8" ?>';
            echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">';
            echo '<channel>';
            echo '<generator>Mecha ' . Mecha::version() . '</generator>';
            echo '<title><![CDATA[' . To::text(($page->title ? $page->title . ' / ' : "") . $config->title) . ']]></title>';
            echo '<link>' . To::url($path) . '/</link>';
            echo '<description><![CDATA[' . To::text($page->description ?: $config->description) . ']]></description>';
            echo '<lastBuildDate>' . $t . '</lastBuildDate>';
            echo '<language>' . $config->language . '</language>';
            echo '<atom:link href="' . $url->current . '" rel="self" type="application/rss+xml"/>';
            if ($files = g($path, 'page')) {
                foreach ($files as $file) {
                    fn_rss_item(new Page($file));
                }
            } else {
                fn_rss_item($page);
            }
            echo '</channel>';
            echo '</rss>';
            exit;
        } else if ($p === $slug['json']) {
            HTTP::mime('application/json', $config->charset);
            $json = [
                [
                    'generator' => 'Mecha ' . Mecha::version(),
                    'title' => ($page->title ? $page->title . ' · ' : "") . $config->title,
                    'link' => To::url($path),
                    'description' => $page->description ?: $config->description,
                    'time' => date(DATE_WISE, strtotime($t)),
                    'language' => $config->language
                ], []
            ];
            if ($files = g($path, 'page')) {
                foreach ($files as $file) {
                    $page = new Page($file);
                    $json[1][] = [
                        'title' => $page->title,
                        'link' => $page->url,
                        'description' => $page->description,
                        'time' => $page->time,
                        'id' => $page->id
                    ];
                }
            } else {
                $json[1][0] = [
                    'title' => $page->title,
                    'link' => $page->url,
                    'description' => $page->description,
                    'time' => $page->time,
                    'id' => $page->id
                ];
            }
            $fn = Request::get('fn');
            echo ($fn ? $fn . '(' : "") . json_encode($json) . ($fn ? ');' : "");
            exit;
        }
    }
});