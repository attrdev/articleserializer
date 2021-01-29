<?php

    require('vendor/autoload.php');

    $html = '
        <h1>This is an H1 Heading</h1>
        <h2>This is an H2 Heading</h2>
        <p>This is a paragraph</p>
        <hr>
        <pre>
            (function(){
                console.log(\'this is some code\');
            })();
        </pre>
        <blockquote>
            <p>This is a quote</p>
            <p>
                <cite>
                    This is the quote citation
                </cite>
            </p>
        </blockquote>
        <div class="grid">
            <div class="column column-8">
                <p>8/12 column</p>
            </div>
            <div class="column column-4">
                <p>4/12 column</p>
            </div>
        </div>
        <div>
            <p>This is a layer</p>
        </div>
        <figure>
            <img src="/img.jpg" alt="Alt Text" width="1280" height="1280">
            <figcaption>Picture Caption</figcaption>
        </figure>
        <figure>
            <a target="_blank" href="/"><img src="/img.jpg" alt="Alt Text" width="1280" height="1280"></a>
            <figcaption>Picture Caption</figcaption>
        </figure>
        <figure>
            <div class="embed-responsive">
                test
            </div>
        </figure>
    ';

    print(sprintf('<pre>%s</pre>',htmlentities($html)));

    $blocks = Attraction\ArticleSerializer::serialize($html);
    print(sprintf('<pre>%s</pre>',$blocks));
    print('<br><br>');
    
    $unserialized = Attraction\ArticleSerializer::unserialize($blocks);
    print(sprintf('<pre>%s</pre>',htmlentities($unserialized)));

    $blocks = Attraction\ArticleSerializer::serialize($unserialized);
    print(sprintf('<pre>%s</pre>',$blocks));
