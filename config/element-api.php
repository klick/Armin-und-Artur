<?php

use craft\elements\Entry;
use craft\helpers\UrlHelper;
use craft\elements\Asset;

return [
    'endpoints' => [
        'api/kurt.json' => function() {
            return [
                'elementType' => Entry::class,
                'cache' => false,
                'criteria' => ['section' => 'kurtLeben','level' => 1],
                'transformer' => function(Entry $entry) {
                    return [
                        'title' => $entry->title,
                        'url' => $entry->url,
                        'jsonUrl' => UrlHelper::url("kurt/{$entry->id}.json"),
                        'Beschreibender Titel' => $entry->descriptiveTitle,
                    ];
                },
            ];
        },
        'api/geschichten.json' => function() {
            return [
                'elementType' => Entry::class,
                'cache' => false,
                'elementsPerPage' => 500,
                'paginate' => false,
                'criteria' => [
                    'section' => 'geschichten',
                    'relatedTo' => 2015,
                ],
                'transformer' => function(Entry $entry) {
                    // $imagerx = Craft::$app->plugins->getPlugin('imager-x');
                    // $resizedImageSmall = $imagerx->imager->transformImage($entry->cover->one(), [
                    //     'width' => 400,
                    //     'ratio' => 1/1,
                    //     'format' => 'jpg'
                    // ]);
                    //Get tags as an array
                    // $tagsArray = [];
                    // $tags = $entry->tags->all();
                    // foreach ($tags as $tag) {
                    //     $tagsArray[] = $tag->title;
                    // }

                     // Add static tag
                    //$tagsArray[] = 'Flucht und Ankunft';
                
                    // $regex = '/<h2[^>]*>([\s\S]*?)<\/h2[^>]*>/m';
                    // $regex2 = '/<h6[^>]*>([\s\S]*?)<\/h6[^>]*>/m';
                    // $getName = '/<h2\b[^>]*>(.*?)<\/h2>/i';
                    // $replacement = '$1';
                    // preg_match($regex, $entry->body, $matches);
                    // $name = isset($matches[1]) ? $matches[1] : '';

                    // preg_match($regex2, $entry->body, $matches);
                    // $bio = isset($matches[1]) ? $matches[1] : '';
                    return [
                        'titel' => $entry->title,
                        'verfasser' => $entry->verfasser->one() ? $entry->verfasser->one()->title : null,
                        'id' => $entry->id,
                        'radtime' => $entry->readtime,
                        'kategorie' => $entry->kategorie->one()->title,

                        // 'Verfasser Alter' => $entry->writer->one()->age,
                        // 'Verfasser Geburtsjahr' => $entry->writer->one()->birth,
                        // 'Verfasser Land' => $entry->writer->one()->land->one()->title,
                        // 'Verfasser Herkunft' => $entry->writer->one()->address->address ? $entry->writer->one()->address->address : null,
                        // 'Autor ID' => $entry->autor->one()->id,
                        // 'Verfasser Biografie' => strlen($entry->writer->one()->body) ? strip_tags($entry->writer->one()->body) : null,
                        // 'Verfasser Bild' => $entry->writer->one()->portraitImgWriter->one() ? $entry->writer->one()->portraitImgWriter->one()->url : null,
                        // 'Zusammenfassung (Excerpt) DE' => $entry->summary ? strip_tags($entry->summary) : 'Lorem Ipsum dolor sit amet',
                        
                        // 'jsonUrl' => UrlHelper::url("api/poetry/{$entry->id}.json"),
                        // 'coverResized' => $resizedImageSmall ? craft\helpers\App::env('ROOT_URL') . $resizedImageSmall->url : null,
                        // 'coverCopyright' => (is_null($entry->cover->one()) ? 'n/a' : (is_null($entry->cover->one()->copyright) ? $entry->mieterName : $entry->cover->one()->copyright)),
                        // 'geschaeftsbezeichnung' => $entry->geschaeftsbezeichnung,
                        // 'website' => $entry->website,
                        // 'kontakt' => $entry->contact,
                        //'text' => strip_tags($entry->body, '<p><br><h2><h4><h6>'),
                        //'text' => strip_tags($entry->body, '<p><br><h2><h4><h6>'),
                        //'text' => strip_tags($entry->body, '<p><br><h2><h4><h6>'),
                        //'text' => strip_tags(preg_replace($regex, '', $entry->body), '<p><br><h2><h4><h6>'),
                        //'name' => strip_tags($name),
                        //'bio' => strip_tags($bio),
                        // 'wp ID' => $entry->wpId,
                        //'Eintrag ID' => $entry->id,
                        //'Verfasser ID' => $entry->writer->one()->id,
                        
                        // 'overlay' => $entry->overlay,
                        // 'oeffnungszeiten' => $entry->storeHours ? $entry->storeHours : ' ',
                    ];
                },
            ];
        },
        'api/geschichten-alle.json' => function() {
            return [
                'elementType' => Entry::class,
                'cache' => false,
                'elementsPerPage' => 0,
                'paginate' => false,
                'criteria' => [
                    'section' => 'geschichten',
                ],
                'transformer' => function(Entry $entry) {
                    return [
                        'title' => $entry->title,
                        'id' => $entry->id,
                        'verfasser' => $entry->verfasser->one() ? $entry->verfasser->one()->title : null,
                    ];
                },
            ];
        },
        // The free discovery catalogue. Full reading artefacts are deliberately
        // not exposed here; they are delivered by the x402-aware Story API.
        'api/v1/stories.json' => function() {
            return [
                'elementType' => Entry::class,
                'cache' => false,
                'elementsPerPage' => 0,
                'paginate' => false,
                'criteria' => [
                    'section' => 'geschichten',
                ],
                'transformer' => function(Entry $entry) {
                    /** @var \modules\storyapi\Module|null $storyApi */
                    $storyApi = \Craft::$app->getModule('story-api');
                    $reading = $storyApi?->getStories()->getCatalogItemByEntryId((int)$entry->id);

                    return [
                        'id' => $entry->id,
                        'title' => $entry->title,
                        'url' => $entry->url,
                        'author' => $entry->verfasser->one()?->title,
                        'readtimeMinutes' => $entry->readtime,
                        'reading' => $reading ? [
                            'available' => true,
                            'storyId' => $reading['id'],
                            'schemaVersion' => $reading['schemaVersion'],
                            'schemaUrl' => $reading['schemaUrl'],
                            'access' => $reading['access'],
                            'environment' => $reading['environment'],
                            'payment' => $reading['payment'],
                            'url' => $reading['readingUrl'],
                        ] : [
                            'available' => false,
                        ],
                    ];
                },
            ];
        },
        'api/verfasser.json' => function() {
            return [
                'elementType' => Entry::class,
                'cache' => false,
                'elementsPerPage' => 300,
                'paginate' => false,
                'criteria' => [
                    'section' => 'authors',
                    'siteId' => 1
                ],
                'transformer' => function(Entry $entry) {
                    $gedichteArray = [];
                    $gedichte = $entry->gedichte->all();
                    
                    foreach ($gedichte as $gedicht) {
                        //$gedichteArray[] = $gedicht->title . ' (ID: ' . $gedicht->id . ')';
                        $gedichteArray[] = $gedicht->title;
                    }

                    $gedichteString = implode(', ', $gedichteArray);
                    return [
                        'Eintrag ID' => $entry->id,
                        'Titel' => $entry->title,
                        'Alter' => $entry->age,
                        'Geburtsjahr' => $entry->birth,
                        'Herkunft'=> $entry->address->address,
                        'Herkunft Lat'=> $entry->address->lat,
                        'Herkunft Long'=> $entry->address->lng,
                        'Preisträger'=> $entry->preistraeger,
                        'Portraitbild'=> $entry->portraitImgWriter->one() ? $entry->portraitImgWriter->one()->url : null,
                        'Gedichte' => $gedichteString,
                        'Kurzbio' => strip_tags($entry->body, '<p>'),
                    ];
                },
            ];
        },
        'api/texte-afghanistan.json' => function() {
            return [
                'elementType' => Entry::class,
                'cache' => false,
                'elementsPerPage' => 300,
                'criteria' => [
                    'section' => 'poetry',
                    'relatedTo' => [
                        'targetElement' => 9,  // Category ID
                        'field' => 'land'      // Category field handle
                    ]
                ],
                'transformer' => function(Entry $entry) {
                    return [
                        'Autor' => $entry->writer->one()->title,
                        'Eintragsdatum' => $entry->postDate->format('Y'),
                        'Titel DE' => $entry->title,
                        'Zusammenfassung (Excerpt) DE' => $entry->summary ? strip_tags($entry->summary) : 'Lorem Ipsum dolor sit amet',

                    ];
                },
            ];
        },
        'api/lesungen.json' => function() {
            return [
                'elementType' => Entry::class,
                'cache' => false,
                'criteria' => ['section' => 'readings','offset' => 2,],
                'elementsPerPage' => 16,
                'transformer' => function(Entry $entry) {
                $featuredImageId = $entry->getFieldValue('featuredImageId');
                $assets = Asset::find()
                    ->anyStatus()
                    ->search($featuredImageId)
                    ->all();
                $thumbnailUrl = $assets ? $assets[0]->url : null;
                $featuredImage = $entry->featuredImage ? $entry->featuredImage->one() : Craft::$app->getElements()->getElementById(5246, Asset::class);
                $imagerx = Craft::$app->plugins->getPlugin('imager-x');
                $resizedImageSmall = $imagerx->imager->transformImage($featuredImage, [
                    'width' => 400,
                    'ratio' => 1/1,
                    'format' => 'jpg'
                ]);
                $tagsArray = [];
                $tags = $entry->tags->all();
                foreach ($tags as $tag) {
                    $tagsArray[] = $tag->title;
                }
                $pattern = '@\[.*?\]@';

                    return [
                        'title' => $entry->title,
                        //'text' => strip_tags(preg_replace($pattern, '', $entry->body),'<p><br><h6><h2><h4>'),
                        //'entry ID' => $entry->id,
                        //'postDate' => $entry->postDate,
                        //'slug' => $entry->slug,
                        //'thumbnail ID' => $featuredImageId,
                        'featured image resized' => $resizedImageSmall ? craft\helpers\App::env('ROOT_URL') . $resizedImageSmall->url : Craft::$app->getElements()->getElementById(5246, Asset::class)->url,
                        //'featured image' => $entry->featuredImage->one() ? $entry->featuredImage->one()->url : craft\helpers\App::env('ROOT_URL') . '/bilder/seoimage_2023-11-24-082233_lnzo.png',
                        // 'featured caption' => $entry->featuredImage->one() ? $entry->featuredImage->one()->imageCaption : null,
                        // 'featured copy' => $entry->featuredImage->one() ? $entry->featuredImage->one()->imgCopyright : null,
                        // 'featured wp id' => $entry->featuredImage->one() ? $entry->featuredImage->one()->wpId : null,
                        // // 'thumbnail URL' => $thumbnailUrl,
                        // 'wp post translation ID' => $entry->wpPostTranslations,
                        'datum start' => $entry->datumStart->format('d.m.Y'),
                        // 'datum ende' => $entry->datumEnde,
                        // 'preis' => $entry->price,
                        'location' => $entry->location->one() ? $entry->location->one()->title : null,
                        'ort' => $entry->location->one() ? $entry->location->one()->address->parts->city :null,
                        // 'tags' => $tagsArray,
                        // 'wp ID' => $entry->wpId,
                    ];
                },
            ];
        },
        'api/aktuelles.json' => function() {
            //Craft::$app->getResponse()->headers->set("Access-Control-Allow-Origin", "*");
            return [
                'elementType' => Entry::class,
                'cache' => false,
                'criteria' => ['section' => 'aktuelles','orderBy' => 'datumStart DESC'],
                'transformer' => function(Entry $entry) {
                    setlocale(LC_TIME, 'de_DE.UTF-8');
                    $imagerx = Craft::$app->plugins->getPlugin('imager-x');
                    $resizedImageSmall = $imagerx->imager->transformImage($entry->cover->one(), [
                        'width' => 400,
                        'ratio' => 1/1,
                        'format' => 'jpg'
                    ]);
                    return [
                        'kategorie' => $entry->kategorie->one()->title,
                        //'dachzeile' => $entry->kategorie->one()->title == 'Ausstellung' ? $entry->kategorie->one()->title . ' · ' . $entry->datumStart->format('d.m.Y') . '–' . $entry->finissageDatumEnde->format('d.m.Y') : $entry->kategorie->one()->title . ' · ' . strftime('%A', strtotime($entry->datumStart->format('l d.m.Y H:i'))) . ' · ' . $entry->datumStart->format('d.m.Y') . ' · ' . $entry->datumStart->format('H:i'),
                        //'dachzeile' => $entry->kategorie->one()->title . ' · ' . strftime('%A', strtotime($entry->datumStart->format('l d.m.Y H:i'))) . ' · ' . $entry->datumStart->format('d.m.Y') . ' · ' . $entry->datumStart->format('H:i'),
                        'coverResized' => craft\helpers\App::env('ROOT_URL') . $resizedImageSmall->url,
                        'title' => $entry->title,
                        'kurzbeschreibung' => $entry->summary,
                        'eintritt' => $entry->eintrittspreis == 0 ? 'Eintritt frei!' : ($entry->eintrittspreisErmaessigt != 0 ? 'Eintritt: ' . $entry->eintrittspreis . '€, Ermässigt: ' .  $entry->eintrittspreisErmaessigt . '€' : 'Eintritt: ' . $entry->eintrittspreis . '€'),
                        'eintritt short' => $entry->eintrittspreis == 0 ? 'frei!' : ($entry->eintrittspreisErmaessigt != 0 ? $entry->eintrittspreis . '€, Ermässigt: ' .  $entry->eintrittspreisErmaessigt . '€' : $entry->eintrittspreis . '€'),
                        'url' => $entry->url,
                        //'postDate' => $entry->postDate->format('d.m.Y'),
                        'jsonUrl' => UrlHelper::url("entry/{$entry->id}.json"),
                        //'cover' => $entry->cover->one()->url,
                        //'kategorie' => $entry->kategorie->one()->title,
                        //'datum' => $entry->datumStart,
                        //'datum2' => $entry->datumStart->format('d.m.Y H:i'),
                    ];
                },
            ];
        },
        'api/categories.json' => function() {
            return [
                'elementType' => craft\elements\Category::class,
                'criteria' => [
                    'group' => 'countries',
                ],
                'transformer' => function(\craft\elements\Category $category) {
                    return [
                        'id' => $category->id,
                        'title' => $category->title,
                        // Add more fields as needed
                    ];
                },
            ];
        },
        'entry/<entryId:\d+>.json' => function($entryId) {
            return [
                'elementType' => Entry::class,
                'cache' => false,
                'criteria' => ['id' => $entryId],
                'one' => true,
                'transformer' => function(Entry $entry) {
                    setlocale(LC_TIME, 'de_DE.UTF-8');
                    $imagerx = Craft::$app->plugins->getPlugin('imager-x');
                    $resizedImageSmall = $imagerx->imager->transformImage($entry->cover->one(), [
                        'width' => 800,
                        'format' => 'jpg'
                    ]);
                    return [
                        'dachzeile' =>$entry->kategorie->one()->title == 'Ausstellung' ? $entry->kategorie->one()->title . ' · ' . $entry->datumStart->format('d.m.Y') . '–' . $entry->finissageDatumEnde->format('d.m.Y') : $entry->kategorie->one()->title . ' · ' . strftime('%A', strtotime($entry->datumStart->format('l d.m.Y H:i'))) . ' · ' . $entry->datumStart->format('d.m.Y') . ' · ' . $entry->datumStart->format('H:i'),
                        'title' => $entry->title,
                        'url' => $entry->url,
                        'cover' => $entry->cover->one()->url,
                        'coverResized' => craft\helpers\App::env('ROOT_URL') . $resizedImageSmall->url,
                        'kurzbeschreibung' => $entry->description,
                        'eintritt' => $entry->eintrittspreis == 0 ? 'Eintritt frei!' : ($entry->eintrittspreisErmaessigt != 0 ? 'Eintritt: ' . $entry->eintrittspreis . '€, Ermässigt: ' .  $entry->eintrittspreisErmaessigt . '€' : 'Eintritt: ' . $entry->eintrittspreis . '€'),
                        'eintritt short' => $entry->eintrittspreis == 0 ? 'frei!' : ($entry->eintrittspreisErmaessigt != 0 ? $entry->eintrittspreis . '€, Ermässigt: ' .  $entry->eintrittspreisErmaessigt . '€' : $entry->eintrittspreis . '€'),
                        'url' => $entry->url,
                        'body' => strip_tags($entry->body),
                        //'datum' => $entry->datumStart,
                        'eröffnung/vernissage' => $entry->datumStart->format('d.m.Y'),
                        'finissage' => $entry->finissageDatumEnde ? $entry->finissageDatumEnde->format('d.m.Y') : null,
                        'laufzeit' => $entry->finissageDatumEnde ? $entry->datumStart->format('d.m.Y') . '–' . $entry->finissageDatumEnde->format('d.m.Y') : null,
                        'uhrzeit' => $entry->datumStart->format('H:i') != '00:00' ? $entry->datumStart->format('H:i') : null,
                        'kategorie' => $entry->kategorie->one()->title,
                    ];
                },
            ];
        },
    ]
];
