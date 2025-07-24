<?php

class HomebannerapiBannerApiModuleFrontController extends ModuleFrontController
{
    private $responseStatus = 200;
    private $responseData = null;
    private $includeAllBanners = true; // Sempre attivo per trovare tutti i banner
    
    /**
     * Log API call if logging is enabled
     * 
     * @param string $status Response status code
     * @param array $data Response data
     * @return void
     */
    /**
     * Estrae il banner da IqitElementor analizzando l'HTML della homepage
     * 
     * @return array Banner data
     */
    private function extractIqitElementorBanner()
    {
        $context = Context::getContext();
        $slides = [];

        // Attiva temporaneamente il logging per il debug
        $tempLogging = true;

        // Assicuriamoci che la cartella logs esista
        $logsDir = _PS_MODULE_DIR_.'homebannerapi/logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }

        // Ottieni l'HTML della homepage usando cURL invece di file_get_contents
        $homepage = '';

        // Verifichiamo se cURL è disponibile
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, _PS_BASE_URL_);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Segui i redirect
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);         // Fino a 5 redirect
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);          // Timeout di 30 secondi
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Non verificare il certificato SSL
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

            $homepage = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Log informazioni cURL
            if ($tempLogging) {
                $logFile = $logsDir.'/curl_debug_'.date('Y-m-d').'.log';
                file_put_contents($logFile, "cURL HTTP Code: $httpCode\n", FILE_APPEND);
                file_put_contents($logFile, "cURL Error (se presente): $curlError\n", FILE_APPEND);
                file_put_contents($logFile, "URL richiesto: " . _PS_BASE_URL_ . "\n", FILE_APPEND);
            }
        } else {
            // Fallback a file_get_contents se cURL non è disponibile
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n",
                    'timeout' => 30,
                    'follow_location' => 1,
                    'max_redirects' => 5
                ]
            ];
            $streamContext = stream_context_create($opts);
            $homepage = @file_get_contents(_PS_BASE_URL_, false, $streamContext);

            if ($tempLogging) {
                $logFile = $logsDir.'/file_get_contents_debug_'.date('Y-m-d').'.log';
                file_put_contents($logFile, "file_get_contents usato come fallback\n", FILE_APPEND);
                file_put_contents($logFile, "URL richiesto: " . _PS_BASE_URL_ . "\n", FILE_APPEND);
                if ($homepage === false) {
                    file_put_contents($logFile, "Errore: Impossibile scaricare con file_get_contents\n", FILE_APPEND);
                }
            }
        }

        // Log HTML se il logging è abilitato
        if ($tempLogging) {
            $logFile = $logsDir.'/iqit_html_'.date('Y-m-d').'.log';
            file_put_contents($logFile, "Homepage HTML Length: " . strlen($homepage) . "\n", FILE_APPEND);

            // Log dell'URL usato
            file_put_contents($logFile, "URL richiesto: " . _PS_BASE_URL_ . "\n", FILE_APPEND);

            // Salva un campione per il debug se abbiamo ricevuto contenuto
            if ($homepage !== false && strlen($homepage) > 0) {
                $htmlSample = substr($homepage, 0, 50000); // Primi 50KB
                file_put_contents($logsDir.'/html_sample.html', $htmlSample);
            } else {
                file_put_contents($logFile, "ERRORE: Impossibile scaricare l'HTML della homepage\n", FILE_APPEND);
            }
        }

        // Inizializza le variabili per le immagini
        $desktopImage = '';
        $mobileImage = '';
        $desktopLink = $context->link->getPageLink('index');
        $mobileLink = $context->link->getPageLink('index');

        // Se non siamo riusciti a scaricare l'HTML, proviamo con un metodo alternativo
        if ($homepage === false || strlen($homepage) == 0) {
            if ($tempLogging) {
                $logFile = $logsDir.'/extraction_error_'.date('Y-m-d').'.log';
                file_put_contents($logFile, "FALLBACK: Primo tentativo fallito, provo metodo diretto\n", FILE_APPEND);
            }

            // Proviamo il metodo diretto - cerchiamo nelle cartelle img/cms e upload
            $bannerCandidates = [
                '/img/cms/home-banner.jpg',
                '/img/cms/banner.jpg',
                '/img/cms/homepage.jpg',
                '/img/cms/slider.jpg',
                '/img/cms/home-slider.jpg',
                '/upload/banner.jpg',
                '/upload/homepage.jpg',
                '/img/banner.jpg'
            ];

            // Verifichiamo se uno di questi file esiste
            $foundBanner = null;
            foreach ($bannerCandidates as $bannerPath) {
                $bannerUrl = _PS_BASE_URL_ . $bannerPath;

                // Verifichiamo se il file esiste
                if (function_exists('curl_init')) {
                    $ch = curl_init($bannerUrl);
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_exec($ch);
                    $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($responseCode == 200) {
                        $foundBanner = $bannerUrl;
                        break;
                    }
                } else {
                    // Fallback a file_get_contents
                    $headers = @get_headers($bannerUrl);
                    if ($headers && strpos($headers[0], '200') !== false) {
                        $foundBanner = $bannerUrl;
                        break;
                    }
                }
            }

            if ($foundBanner) {
                if ($tempLogging) {
                    $logFile = $logsDir.'/extraction_success_'.date('Y-m-d').'.log';
                    file_put_contents($logFile, "SUCCESSO: Trovato banner diretto: $foundBanner\n", FILE_APPEND);
                }

                return [
                    'image' => $foundBanner,
                    'image_mobile' => $foundBanner,
                    'link' => $desktopLink,
                    'link_mobile' => $mobileLink,
                    'title' => 'MAFRA Homepage Banner',
                    'description' => 'Banner della homepage MAFRA (direct)',
                    'source' => 'Direct Banner Path',
                    'slides' => [[
                        'image' => $foundBanner,
                        'title' => 'Banner Homepage',
                        'description' => 'Banner principale',
                        'link' => $desktopLink
                    ]]
                ];
            }

            // Se tutti i tentativi falliscono, usiamo il placeholder
            if ($tempLogging) {
                $logFile = $logsDir.'/extraction_error_'.date('Y-m-d').'.log';
                file_put_contents($logFile, "FALLBACK: Tutti i tentativi falliti, usando placeholder\n", FILE_APPEND);
            }

            return [
                'image' => _PS_BASE_URL_ . '/img/cms/placeholder.jpg',
                'image_mobile' => _PS_BASE_URL_ . '/img/cms/placeholder.jpg',
                'link' => $desktopLink,
                'link_mobile' => $mobileLink,
                'title' => 'Banner Non Disponibile',
                'description' => 'Impossibile scaricare l\'HTML della homepage',
                'source' => 'IqitElementor Homepage (Error)',
                'slides' => []
            ];
        }

        // Cerca direttamente carousel/slider o sezioni di banner
        $carouselPatterns = [
            '/<div[^>]*class="[^"]*carousel[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>/s',  // Bootstrap carousel
            '/<div[^>]*class="[^"]*swiper-container[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>/s',  // Swiper
            '/<div[^>]*class="[^"]*slick-slider[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>/s',  // Slick
            '/<section[^>]*class="[^"]*banner[^"]*"[^>]*>(.*?)<\/section>/s',  // Banner section
            '/<div[^>]*class="[^"]*home-slider[^"]*"[^>]*>(.*?)<\/div>/s',  // Home slider
            '/<div[^>]*class="[^"]*hero-section[^"]*"[^>]*>(.*?)<\/div>/s',  // Hero section
            '/<div[^>]*class="[^"]*d-none[^"]*d-\w+-block[^"]*"[^>]*>(.*?)<\/div>/s',  // Elementi nascosti su mobile
            '/<div[^>]*class="[^"]*hidden-\w+[^"]*"[^>]*>(.*?)<\/div>/s',  // Elementi con classe hidden
            '/<div[^>]*data-mobile-carousel="[^"]*"[^>]*>(.*?)<\/div>/s',  // Carousel con attributo mobile
            '/<div[^>]*class="[^"]*elementor-hidden-phone[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>/s',  // Elementor nascosto su mobile
            '/<div[^>]*class="[^"]*elementor-hidden-desktop[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>/s'  // Elementor nascosto su desktop
        ];

        $carouselHTML = '';
        foreach ($carouselPatterns as $pattern) {
            if (preg_match($pattern, $homepage, $matches)) {
                $carouselHTML = $matches[0];
                break;
            }
        }

        // Se abbiamo trovato un carousel/banner, cerchiamo le immagini al suo interno
        $carouselImages = [];
        if (!empty($carouselHTML)) {
            if ($tempLogging) {
                $logFile = $logsDir.'/carousel_found_'.date('Y-m-d').'.log';
                file_put_contents($logFile, "Carousel/Banner HTML trovato!\n", FILE_APPEND);
                file_put_contents($logFile, "Lunghezza HTML: " . strlen($carouselHTML) . "\n", FILE_APPEND);
            }

            preg_match_all('/<img[^>]*src="([^"]+)"[^>]*>/s', $carouselHTML, $carouselMatches);
            if (!empty($carouselMatches[1])) {
                $carouselImages = $carouselMatches[1];

                if ($tempLogging) {
                    $logFile = $logsDir.'/carousel_images_'.date('Y-m-d').'.log';
                    file_put_contents($logFile, "Immagini trovate nel carousel: " . count($carouselImages) . "\n", FILE_APPEND);
                    foreach ($carouselImages as $idx => $img) {
                        file_put_contents($logFile, ($idx+1) . ". " . $img . "\n", FILE_APPEND);
                    }
                }
            }
        }

        // Cerca tutte le immagini src nell'HTML
        preg_match_all('/<img[^>]*src="([^"]+)"[^>]*>/s', $homepage, $imgMatches);

        if ($tempLogging && !empty($imgMatches[1])) {
            $logFile = $logsDir.'/images_found_'.date('Y-m-d').'.log';
            $logContent = "Immagini totali trovate con pattern base: " . count($imgMatches[1]) . "\n";
            foreach ($imgMatches[1] as $idx => $img) {
                $logContent .= ($idx+1) . ". " . $img . "\n";
            }
            file_put_contents($logFile, $logContent, FILE_APPEND);
        }

        // Trova le immagini nei vari contesti dell'homepage
        $patterns = [
            // Priorità alta - specifiche per carousel/slider
            '/<div[^>]*class="[^"]*carousel-item[^"]*"[^>]*>\s*.*?<img[^>]*src="([^"]+)"[^>]*>/s',  // Bootstrap carousel
            '/<div[^>]*class="[^"]*swiper-slide[^"]*"[^>]*>\s*.*?<img[^>]*src="([^"]+)"[^>]*>/s',  // Swiper slider
            '/<div[^>]*class="[^"]*slick-slide[^"]*"[^>]*>\s*.*?<img[^>]*src="([^"]+)"[^>]*>/s',   // Slick slider
            '/<div[^>]*class="[^"]*banner-slider[^"]*"[^>]*>\s*.*?<img[^>]*src="([^"]+)"[^>]*>/s',  // Generic banner slider
            '/<div[^>]*id="[^"]*homeslider[^"]*"[^>]*>\s*.*?<img[^>]*src="([^"]+)"[^>]*>/s',       // Home slider
            '/<div[^>]*class="[^"]*hero-banner[^"]*"[^>]*>\s*.*?<img[^>]*src="([^"]+)"[^>]*>/s',    // Hero banner
            // Priorità media - generici per elementi banner
            '/<div[^>]*class="[^"]*elementor-image[^"]*"[^>]*>\s*<img[^>]*src="([^"]+)"[^>]*>/s',           // elementor-image
            '/<div[^>]*class="[^"]*iqit-element[^"]*"[^>]*>\s*<img[^>]*src="([^"]+)"[^>]*>/s', // iqit-element
            '/<figure[^>]*>\s*<img[^>]*src="([^"]+)"[^>]*>/s',                         // figure > img
            '/<div[^>]*class="[^"]*banner[^"]*"[^>]*>\s*<img[^>]*src="([^"]+)"[^>]*>/s',    // div banner > img
            '/<div[^>]*class="[^"]*slider[^"]*"[^>]*>\s*<img[^>]*src="([^"]+)"[^>]*>/s'     // div slider > img
        ];

        // Log dei pattern se debugging attivo
        if ($tempLogging) {
            $logFile = _PS_MODULE_DIR_.'homebannerapi/logs/patterns_'.date('Y-m-d').'.log';
            file_put_contents($logFile, "Pattern di ricerca applicati: " . count($patterns) . "\n", FILE_APPEND);
        }

        // Se abbiamo trovato immagini nel carousel, diamo loro alta priorità
        $allImages = [];

        // Aggiungi prima le immagini dal carousel se ce ne sono
        if (!empty($carouselImages)) {
            foreach ($carouselImages as $img) {
                if (!in_array($img, $allImages)) {
                    $allImages[] = $img;
                }
            }
        }

        // Cerca con ogni pattern
        foreach ($patterns as $index => $pattern) {
            preg_match_all($pattern, $homepage, $matches);

            if (!empty($matches[1])) {
                if ($tempLogging) {
                    $logFile = _PS_MODULE_DIR_.'homebannerapi/logs/pattern_'.$index.'_'.date('Y-m-d').'.log';
                    file_put_contents($logFile, "Pattern $index - Trovate: " . count($matches[1]) . "\n", FILE_APPEND);
                    foreach ($matches[1] as $idx => $img) {
                        file_put_contents($logFile, ($idx+1) . ". " . $img . "\n", FILE_APPEND);
                    }
                }

                foreach ($matches[1] as $img) {
                    // Accetta qualsiasi immagine, non solo quelle in img/cms/
                    if (!in_array($img, $allImages)) {
                        $allImages[] = $img;
                    }
                }
            }
        }

        // Aggiungi anche le immagini generiche trovate
        if (!empty($imgMatches[1])) {
            foreach ($imgMatches[1] as $img) {
                if (!in_array($img, $allImages) && (strpos($img, '.jpg') !== false || strpos($img, '.png') !== false || strpos($img, '.gif') !== false)) {
                    // Accetta solo immagini con estensione valida
                    $allImages[] = $img;
                }
            }
        }

        // Log delle immagini totali trovate
        if ($tempLogging) {
            $logFile = _PS_MODULE_DIR_.'homebannerapi/logs/all_images_'.date('Y-m-d').'.log';
            file_put_contents($logFile, "Immagini totali trovate: " . count($allImages) . "\n", FILE_APPEND);
            foreach ($allImages as $idx => $img) {
                file_put_contents($logFile, ($idx+1) . ". " . $img . "\n", FILE_APPEND);
            }
        }

        // Filtra solo le immagini che sembrano essere banner (grandi dimensioni o nomi significativi)
        $bannerKeywords = ['banner', 'slide', 'carousel', 'hero', 'header', 'homepage', 'promo', 'feature', 'main'];
        // Pattern da escludere (piccole icone, elementi UI)
        $excludePatterns = ['icon', 'logo', 'payment', 'delivery', 'social', 'chat', 'support', 'envelop', 'user', 'cart', 'menu', 'search'];

        // Pattern specifici per immagini adattate ai dispositivi
        $devicePatterns = [
            'mobile' => ['mobile', 'mob', 'smartphone', 'phone', 'sm-'],
            'desktop' => ['desktop', 'desk', 'large', 'xl-', 'lg-']
        ];
        $possibleBanners = [];

        // Prima controlliamo dimensioni delle immagini contenute nei filename
        $sizePattern = '/-([0-9]+)x([0-9]+)\./'; // pattern per trovare dimensioni tipo -1200x800.
        $dimensionedImages = [];

        foreach ($allImages as $img) {
            if (preg_match($sizePattern, $img, $matches)) {
                $width = (int)$matches[1];
                $height = (int)$matches[2];

                // Se l'immagine ha dimensioni grandi (larghezza > 800px), potrebbe essere un banner
                if ($width > 800) {
                    $dimensionedImages[$img] = $width * $height; // Salviamo l'area dell'immagine per ordinare
                }
            }
        }

        // Ordiniamo le immagini per dimensione (dal più grande al più piccolo)
        arsort($dimensionedImages);

        // Prendiamo le prime 3 immagini più grandi
        $largeImages = array_slice(array_keys($dimensionedImages), 0, 3);

        // Ora controlliamo le keyword nel filename
        foreach ($allImages as $img) {
            // Salta immagini molto piccole (tipicamente icone) se contengono pattern di esclusione
            $shouldExclude = false;
            foreach ($excludePatterns as $pattern) {
                if (stripos(basename($img), $pattern) !== false) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude) {
                continue; // Salta questa immagine
            }

            // Se è già tra le immagini grandi, aggiungiamola
            if (in_array($img, $largeImages)) {
                $possibleBanners[] = $img;
                continue;
            }

            // Controlla se contiene parole chiave nel nome del file
            $isKeywordMatch = false;
            foreach ($bannerKeywords as $keyword) {
                if (stripos(basename($img), $keyword) !== false) {
                    $isKeywordMatch = true;
                    break;
                }
            }

            // Se contiene una keyword o è in /img/cms/ e non è un'icona piccola, considerala un possibile banner
            if (($isKeywordMatch || strpos($img, '/img/cms/') !== false) && !$shouldExclude) {
                $possibleBanners[] = $img;
            }
        }

        // Se non abbiamo trovato banner probabili, cerca immagini nella directory slider se esiste
        if (empty($possibleBanners)) {
            foreach ($allImages as $img) {
                if (strpos($img, '/img/slider/') !== false || strpos($img, '/img/banner/') !== false) {
                    $possibleBanners[] = $img;
                }
            }
        }

        // Ordiniamo i possibili banner dando priorità a quelli che sembrano più probabili
        $scoredBanners = [];

        foreach ($possibleBanners as $img) {
            $score = 0;
            $filename = basename($img);

            // Aumenta il punteggio basato su parole chiave nel nome del file
            foreach ($bannerKeywords as $idx => $keyword) {
                if (stripos($filename, $keyword) !== false) {
                    $score += 10 - $idx; // Parole chiave all'inizio dell'array hanno priorità maggiore
                }
            }

            // Bonus per posizione dell'immagine
            if (strpos($img, '/img/slider/') !== false) $score += 15;
            if (strpos($img, '/img/banner/') !== false) $score += 15;
            if (strpos($img, '/img/cms/') !== false) $score += 5;

            // Bonus per immagini .jpg e .png (più probabili per banner)
            if (preg_match('/\.(jpg|jpeg|png)$/i', $filename)) $score += 5;

            // Penalità per immagini che sembrano icone
            foreach ($excludePatterns as $pattern) {
                if (stripos($filename, $pattern) !== false) {
                    $score -= 20;
                    break;
                }
            }

            // Bonus per dimensioni nel nome file
            if (preg_match($sizePattern, $img, $matches)) {
                $width = (int)$matches[1];
                $height = (int)$matches[2];
                if ($width > 1000) $score += 10;
                else if ($width > 800) $score += 5;
            }

            $scoredBanners[$img] = $score;
        }

        // Ordina i banner per punteggio (decrescente)
        arsort($scoredBanners);

        // Log dei banner con punteggio
        if ($tempLogging) {
            $logFile = $logsDir.'/scored_banners_'.date('Y-m-d').'.log';
            file_put_contents($logFile, "Banner con punteggio: " . count($scoredBanners) . "\n", FILE_APPEND);
            foreach ($scoredBanners as $img => $score) {
                file_put_contents($logFile, "Score $score: $img\n", FILE_APPEND);
            }
        }

        // Se abbiamo trovato possibili banner, usali, altrimenti usa tutte le immagini
        $imagesToUse = !empty($scoredBanners) ? array_keys($scoredBanners) : $allImages;

        // Log dei possibili banner
        if ($tempLogging) {
            $logFile = $logsDir.'/possible_banners_'.date('Y-m-d').'.log';
            file_put_contents($logFile, "Possibili banner trovati: " . count($possibleBanners) . "\n", FILE_APPEND);
            foreach ($possibleBanners as $idx => $img) {
                file_put_contents($logFile, ($idx+1) . ". " . $img . "\n", FILE_APPEND);
            }
        }

        // Filtra ulteriormente le immagini prima di creare le slide
        $finalImages = [];
        $excludedFiles = ['logo', 'payment', 'delivery', 'support', 'chat', 'icon', 'envelop', 'principiante'];

        foreach ($imagesToUse as $slideImage) {
            $basename = basename($slideImage);
            $isExcluded = false;

            // Controlla se è un'immagine da escludere
            foreach ($excludedFiles as $exclude) {
                if (stripos($basename, $exclude) !== false) {
                    $isExcluded = true;
                    break;
                }
            }

            // Salta le immagini escluse
            if ($isExcluded) {
                continue;
            }

            // Aggiungi solo se non è già presente
            if (!in_array($slideImage, $finalImages)) {
                $finalImages[] = $slideImage;
            }
        }

        // Se non abbiamo trovato nessuna immagine valida dopo il filtraggio, verifichiamo se esistono alcuni file standard
        if (empty($finalImages)) {
            $standardBanners = [
                _PS_BASE_URL_ . '/img/cms/homepage-banner.jpg',
                _PS_BASE_URL_ . '/img/cms/home-banner.jpg',
                _PS_BASE_URL_ . '/img/cms/banner.jpg',
                _PS_BASE_URL_ . '/img/cms/slider.jpg'
            ];

            foreach ($standardBanners as $banner) {
                // Verifica se il file esiste con una richiesta HTTP
                if (function_exists('curl_init')) {
                    $ch = curl_init($banner);
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    curl_exec($ch);
                    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($statusCode == 200) {
                        $finalImages[] = $banner;
                    }
                }
            }
        }

        // Log delle immagini finali
        if ($tempLogging) {
            $logFile = $logsDir.'/final_images_'.date('Y-m-d').'.log';
            file_put_contents($logFile, "Immagini finali: " . count($finalImages) . "\n", FILE_APPEND);
            foreach ($finalImages as $idx => $img) {
                file_put_contents($logFile, ($idx+1) . ". " . $img . "\n", FILE_APPEND);
            }
        }

        // Crea slide dalle immagini trovate (massimo 6)
        foreach ($finalImages as $index => $slideImage) {
            if (count($slides) < 6) {
                            // Determina se l'immagine è probabilmente per mobile o desktop
                            $isMobile = (strpos(strtolower($slideImage), 'mobile') !== false || 
                       strpos(strtolower($slideImage), 'mob') !== false);
                            $isDesktop = (strpos(strtolower($slideImage), 'desktop') !== false || 
                         strpos(strtolower($slideImage), 'desk') !== false);

                            $slides[] = [
                    'image' => $slideImage,
                    'title' => 'Slide ' . ($index + 1),
                    'description' => 'Banner della homepage',
                    'link' => $context->link->getPageLink('index'),
                    'device_type' => $isMobile ? 'mobile' : ($isDesktop ? 'desktop' : 'all')
                ];
            }
        }

        // Cerca specificamente i carousel per desktop e mobile con classi Elementor
        $desktopSlides = [];
        $mobileSlides = [];
        $pairSlides = []; // Array per memorizzare coppie desktop/mobile

        // Estrai tutti i carousel dalla pagina
        $allCarousels = [];

        // Trova tutti i carousel desktop (elementor-hidden-phone)
        if (preg_match_all('/<div[^>]*class="[^"]*elementor-widget[^"]*elementor-element[^"]*elementor-hidden-phone[^"]*"[^>]*>.*?<div[^>]*class="[^"]*elementor-image-carousel-wrapper[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/s', $homepage, $desktopCarouselMatches)) {
            foreach ($desktopCarouselMatches[1] as $carouselHtml) {
                $allCarousels['desktop'][] = $carouselHtml;
            }
        }

        // Trova tutti i carousel mobile (elementor-hidden-desktop)
        if (preg_match_all('/<div[^>]*class="[^"]*elementor-widget[^"]*elementor-element[^"]*elementor-hidden-desktop[^"]*"[^>]*>.*?<div[^>]*class="[^"]*elementor-image-carousel-wrapper[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/s', $homepage, $mobileCarouselMatches)) {
            foreach ($mobileCarouselMatches[1] as $carouselHtml) {
                $allCarousels['mobile'][] = $carouselHtml;
            }
        }

        // Log di debug
        $logFile = _PS_MODULE_DIR_.'homebannerapi/logs/elementor_extraction_'.date('Y-m-d').'.log';
        file_put_contents($logFile, "Desktop carousel trovati: " . (isset($allCarousels['desktop']) ? count($allCarousels['desktop']) : 0) . "\n", FILE_APPEND);
        file_put_contents($logFile, "Mobile carousel trovati: " . (isset($allCarousels['mobile']) ? count($allCarousels['mobile']) : 0) . "\n", FILE_APPEND);

        // Estrai le slide desktop
        if (isset($allCarousels['desktop'])) {
            foreach ($allCarousels['desktop'] as $desktopCarousel) {
                // Estrai le slide dal carousel desktop (sia data-src che src)
                // Escludiamo le slide con classe swiper-slide-duplicate
                preg_match_all('/<div[^>]*class="[^"]*swiper-slide(?!.*swiper-slide-duplicate)[^"]*"[^>]*>.*?<img[^>]*(?:data-src|src)="([^"]+)"[^>]*>.*?<\/div>/s', $desktopCarousel, $slideMatches);

                if (!empty($slideMatches[1])) {
                    foreach ($slideMatches[1] as $idx => $img) {
                        if (!preg_match('/data:image\/svg\+xml/', $img)) { // Skip SVG placeholders
                            // Cerca di capire se questa slide ha un link
                            $link = $context->link->getPageLink('index');
                            if (preg_match('/<div[^>]*class="[^"]*swiper-slide[^"]*"[^>]*>.*?<a[^>]*href="([^"]+)"[^>]*>.*?<img[^>]*src="' . preg_quote($img, '/') . '"[^>]*>.*?<\/a>/s', $desktopCarousel, $linkMatch)) {
                                $link = $linkMatch[1];
                            }

                            $desktopSlides[] = [
                                'image' => $img,
                                'title' => 'Desktop Slide ' . ($idx + 1),
                                'description' => 'Banner per desktop',
                                'link' => $link,
                                'device_type' => 'desktop',
                                'position' => $idx
                            ];
                        }
                    }
                }
            }
        }

        // Estrai le slide mobile
        if (isset($allCarousels['mobile'])) {
            foreach ($allCarousels['mobile'] as $mobileCarousel) {
                // Estrai le slide dal carousel mobile (sia data-src che src)
                // Escludiamo le slide con classe swiper-slide-duplicate
                preg_match_all('/<div[^>]*class="[^"]*swiper-slide(?!.*swiper-slide-duplicate)[^"]*"[^>]*>.*?<img[^>]*(?:data-src|src)="([^"]+)"[^>]*>.*?<\/div>/s', $mobileCarousel, $slideMatches);

                if (!empty($slideMatches[1])) {
                    foreach ($slideMatches[1] as $idx => $img) {
                        if (!preg_match('/data:image\/svg\+xml/', $img) && strpos($img, 'svg+xml') === false) { // Skip SVG placeholders
                            // Cerca di capire se questa slide ha un link
                            $link = $context->link->getPageLink('index');
                            if (preg_match('/<div[^>]*class="[^"]*swiper-slide[^"]*"[^>]*>.*?<a[^>]*href="([^"]+)"[^>]*>.*?<img[^>]*(?:data-src|src)="' . preg_quote($img, '/') . '"[^>]*>.*?<\/a>/s', $mobileCarousel, $linkMatch)) {
                                $link = $linkMatch[1];
                            }

                            $mobileSlides[] = [
                                'image' => $img,
                                'title' => 'Mobile Slide ' . ($idx + 1),
                                'description' => 'Banner per mobile',
                                'link' => $link,
                                'device_type' => 'mobile',
                                'position' => $idx
                            ];
                        }
                    }
                }
            }
        }

        // Matching tra desktop e mobile usando la posizione e il nome del file
        // Organizziamo le slide mobile per posizione per un accesso più veloce
        $mobileSlidesByPosition = [];
        $usedMobileSlides = [];
        
        foreach ($mobileSlides as $mSlide) {
            if (isset($mSlide['position'])) {
                $mobileSlidesByPosition[$mSlide['position']] = $mSlide;
            }
        }
        
        foreach ($desktopSlides as $dSlide) {
            $baseDesktopName = str_replace('_desktop_', '_', basename($dSlide['image']));
            $baseDesktopName = preg_replace('/_?\d+x\d+/', '', $baseDesktopName); // Rimuovi dimensioni

            $matchedMobile = false;
            
            // Prima prova a matchare per posizione (priorità più alta)
            if (isset($dSlide['position']) && isset($mobileSlidesByPosition[$dSlide['position']]) && 
                !in_array($mobileSlidesByPosition[$dSlide['position']]['image'], $usedMobileSlides)) {
                $mSlide = $mobileSlidesByPosition[$dSlide['position']];
                $pairSlides[] = [
                    'desktop' => $dSlide,
                    'mobile' => $mSlide
                ];
                $usedMobileSlides[] = $mSlide['image'];
                $matchedMobile = true;
            }
            
            // Se non ha matchato per posizione, prova con il nome del file
            if (!$matchedMobile) {
                foreach ($mobileSlides as $mSlide) {
                    // Salta le slide mobile già usate
                    if (in_array($mSlide['image'], $usedMobileSlides)) {
                        continue;
                    }
                    
                    $baseMobileName = str_replace('_mobile_', '_', basename($mSlide['image']));
                    $baseMobileName = preg_replace('/_?\d+x\d+/', '', $baseMobileName); // Rimuovi dimensioni
                    
                    // Miglioriamo il matching basato sul nome del file
                    // Rimuoviamo estensioni e numeri per un confronto più pulito
                    $cleanDesktopName = preg_replace('/\.\w+$/', '', $baseDesktopName); // Rimuovi estensione
                    $cleanDesktopName = preg_replace('/[0-9]+/', '', $cleanDesktopName); // Rimuovi numeri
                    $cleanDesktopName = strtolower(trim($cleanDesktopName)); // Normalizza
                    
                    $cleanMobileName = preg_replace('/\.\w+$/', '', $baseMobileName); // Rimuovi estensione
                    $cleanMobileName = preg_replace('/[0-9]+/', '', $cleanMobileName); // Rimuovi numeri
                    $cleanMobileName = strtolower(trim($cleanMobileName)); // Normalizza
                    
                    // Se i nomi puliti hanno una buona somiglianza
                    $similarity = similar_text($cleanDesktopName, $cleanMobileName) / max(strlen($cleanDesktopName), strlen($cleanMobileName));
                    if ($similarity > 0.6) {
                        $pairSlides[] = [
                            'desktop' => $dSlide,
                            'mobile' => $mSlide
                        ];
                        $usedMobileSlides[] = $mSlide['image'];
                        $matchedMobile = true;
                        break;
                    }
                }
            }

            // Se non abbiamo trovato un mobile corrispondente
            if (!$matchedMobile) {
                $pairSlides[] = [
                    'desktop' => $dSlide,
                    'mobile' => null
                ];
            }
        }

        // Aggiungi eventuali mobile slides non abbinate
        $pairedMobileImages = [];
        foreach ($pairSlides as $pair) {
            if ($pair['mobile']) {
                $pairedMobileImages[] = $pair['mobile']['image'];
            }
        }

        foreach ($mobileSlides as $mSlide) {
            if (!in_array($mSlide['image'], $pairedMobileImages)) {
                $pairSlides[] = [
                    'desktop' => null,
                    'mobile' => $mSlide
                ];
            }
        }

        // Creiamo le slide finali dalle coppie trovate
        $finalSlides = [];

        // Prima aggiungiamo tutte le slide esistenti
        $finalSlides = $slides;

        // Poi aggiungiamo le coppie desktop/mobile trovate
        foreach ($pairSlides as $pair) {
            if ($pair['desktop']) {
                // Verifica se questa slide desktop è già inclusa
                $alreadyIncluded = false;
                foreach ($finalSlides as $existingSlide) {
                    if ($existingSlide['image'] == $pair['desktop']['image']) {
                        $alreadyIncluded = true;
                        break;
                    }
                }

                if (!$alreadyIncluded) {
                    $finalSlides[] = $pair['desktop'];
                }
            }

            if ($pair['mobile']) {
                // Verifica se questa slide mobile è già inclusa
                $alreadyIncluded = false;
                foreach ($finalSlides as $existingSlide) {
                    if ($existingSlide['image'] == $pair['mobile']['image']) {
                        $alreadyIncluded = true;
                        break;
                    }
                }

                if (!$alreadyIncluded) {
                    $finalSlides[] = $pair['mobile'];
                }
            }
        }

        // Aggiorniamo le slide finali
        $slides = $finalSlides;

        // Se ci sono slide trovate, usa la prima come immagine principale
        if (!empty($slides)) {
            // Trova un'immagine desktop per l'immagine principale
            $desktopImage = '';
            $mobileImage = '';

            // Prima cerca nelle slide con device_type desktop
            foreach ($slides as $slide) {
                if ($slide['device_type'] === 'desktop' && empty($desktopImage)) {
                    $desktopImage = $slide['image'];
                } else if ($slide['device_type'] === 'mobile' && empty($mobileImage)) {
                    $mobileImage = $slide['image'];
                }
            }

            // Se non troviamo immagini specifiche per device, usa le prime disponibili
            if (empty($desktopImage)) {
                $desktopImage = $slides[0]['image'];
            }

            if (empty($mobileImage)) {
                if (count($slides) > 1) {
                    $mobileImage = $slides[1]['image'];
                } else {
                    $mobileImage = $desktopImage;
                }
            }
        }

        // Se non abbiamo trovato immagini, usa i placeholder
        if (empty($desktopImage)) {
            $desktopImage = _PS_BASE_URL_ . '/img/cms/placeholder.jpg';
        }

        if (empty($mobileImage)) {
            $mobileImage = $desktopImage; // Usa la stessa immagine se manca quella mobile
        }

        // Titolo e descrizione fissi
        $title = 'MAFRA Homepage Banner';
        $description = 'Banner della homepage MAFRA';

        // Debug finale
        if ($tempLogging) {
            $logFile = _PS_MODULE_DIR_.'homebannerapi/logs/extraction_result_'.date('Y-m-d').'.log';
            $result = [
                'image' => $desktopImage,
                'image_mobile' => $mobileImage,
                'link' => $desktopLink,
                'link_mobile' => $mobileLink,
                'title' => $title,
                'description' => $description,
                'slides_count' => count($slides)
            ];
            file_put_contents($logFile, json_encode($result, JSON_PRETTY_PRINT), FILE_APPEND);
        }

        // Debug finale
        if ($tempLogging) {
            $logFile = $logsDir.'/extraction_result_'.date('Y-m-d').'.log';
            $result = [
                'image' => $desktopImage,
                'image_mobile' => $mobileImage,
                'link' => $desktopLink,
                'link_mobile' => $mobileLink,
                'title' => $title,
                'description' => $description,
                'slides_count' => count($slides)
            ];
            file_put_contents($logFile, json_encode($result, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        }

        // Prepara le coppie desktop/mobile per la risposta
        $desktopMobilePairs = [];
        
        // Log per debug delle coppie
        if ($tempLogging) {
            $logFile = $logsDir.'/pairs_debug_'.date('Y-m-d').'.log';
            file_put_contents($logFile, "Numero totale di coppie trovate: " . count($pairSlides) . "\n", FILE_APPEND);
        }
        
        foreach ($pairSlides as $pair) {
            // Aggiungi solo coppie valide (almeno uno tra desktop e mobile deve esistere)
            if ($pair['desktop'] || $pair['mobile']) {
                $desktopMobilePairs[] = [
                    'desktop' => $pair['desktop'] ? $pair['desktop']['image'] : null,
                    'mobile' => $pair['mobile'] ? $pair['mobile']['image'] : null,
                    'desktop_link' => $pair['desktop'] ? $pair['desktop']['link'] : null,
                    'mobile_link' => $pair['mobile'] ? $pair['mobile']['link'] : null
                ];
                
                // Log per debug
                if ($tempLogging) {
                    $desktopImg = $pair['desktop'] ? basename($pair['desktop']['image']) : 'null';
                    $mobileImg = $pair['mobile'] ? basename($pair['mobile']['image']) : 'null';
                    file_put_contents($logFile, "Coppia: Desktop=$desktopImg, Mobile=$mobileImg\n", FILE_APPEND);
                }
            }
        }
        
        // Assicuriamoci di avere esattamente 6 coppie
        if (count($desktopMobilePairs) < 6) {
            // Se abbiamo meno di 6 coppie, aggiungiamo coppie vuote fino a 6
            $missingPairs = 6 - count($desktopMobilePairs);
            for ($i = 0; $i < $missingPairs; $i++) {
                $desktopMobilePairs[] = [
                    'desktop' => null,
                    'mobile' => null,
                    'desktop_link' => null,
                    'mobile_link' => null
                ];
            }
            
            if ($tempLogging) {
                file_put_contents($logFile, "Aggiunte $missingPairs coppie vuote per arrivare a 6\n", FILE_APPEND);
            }
        } else if (count($desktopMobilePairs) > 6) {
            // Se abbiamo più di 6 coppie, prendiamo solo le prime 6
            $desktopMobilePairs = array_slice($desktopMobilePairs, 0, 6);
            
            if ($tempLogging) {
                file_put_contents($logFile, "Limitate le coppie a 6\n", FILE_APPEND);
            }
        }
        
        if ($tempLogging) {
            file_put_contents($logFile, "Numero finale di coppie: " . count($desktopMobilePairs) . "\n", FILE_APPEND);
        }

        return [
            'image' => $desktopImage,
            'image_mobile' => $mobileImage,
            'link' => $desktopLink,
            'link_mobile' => $mobileLink,
            'title' => $title,
            'description' => $description,
            'source' => 'IqitElementor Homepage',
            'slides' => $slides,
            'desktop_mobile_pairs' => $desktopMobilePairs
        ];
    }

    private function logApiCall($status, $data)
    {
        if (!(bool)Configuration::get('HBAPI_LOGGING')) {
            return;
        }
        
        $logFile = _PS_MODULE_DIR_.'homebannerapi/logs/api_'.date('Y-m-d').'.log';
        $ip = Tools::getRemoteAddr();
        $timestamp = date('Y-m-d H:i:s');
        $source = Configuration::get('HBAPI_SOURCE');
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
        
        // Autenticazione sempre tramite header
        $authMethod = 'Header';
        
        // Create log entry
        $logEntry = sprintf(
            "[%s] IP: %s | Status: %d | Source: %s | Auth: %s | UA: %s | Response: %s\n",
            $timestamp,
            $ip,
            $status,
            $source,
            $authMethod,
            $userAgent,
            json_encode($data)
        );
        
        // Write to log file
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    public function initContent()
    {
        parent::initContent();
        header('Content-Type: application/json');

        if (!(bool)Configuration::get('HBAPI_ENABLED')) {
            $this->responseStatus = 403;
            $this->responseData = ['error' => 'API disabled'];
            http_response_code($this->responseStatus);
            echo json_encode($this->responseData);
            $this->logApiCall($this->responseStatus, $this->responseData);
            exit;
        }

        // Get token exclusively from Authorization header
        $token = null;
        $headers = getallheaders();

        // Accetta solo l'header Authorization con Bearer token
        if (isset($headers['Authorization']) || isset($headers['authorization'])) {
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : $headers['authorization'];
            // Check if the header starts with "Bearer "
            if (strpos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7);
            } else {
                $token = $authHeader;
            }
        }

        
        if ($token !== Configuration::get('HBAPI_TOKEN')) {
            $this->responseStatus = 401;
            $this->responseData = ['error' => 'Invalid token. Use Authorization header with Bearer token'];
            http_response_code($this->responseStatus);
            echo json_encode($this->responseData);
            $this->logApiCall($this->responseStatus, $this->responseData);
            exit;
        }

        $cacheFile = _PS_MODULE_DIR_.'homebannerapi/cache/banner.json';

        // Serve cache if available and valid
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 600) {
            $this->responseStatus = 200;
            $this->responseData = json_decode(file_get_contents($cacheFile), true);

            // Aggiungi debug info sulla provenienza della cache
            if ((bool)Configuration::get('HBAPI_LOGGING')) {
                $this->responseData['cache_info'] = [
                    'from_cache' => true,
                    'cache_age' => time() - filemtime($cacheFile),
                    'cache_file' => basename($cacheFile),
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
            }

            echo json_encode($this->responseData, JSON_PRETTY_PRINT);
            $this->logApiCall($this->responseStatus, $this->responseData);
            exit;
        }

        $source = Configuration::get('HBAPI_SOURCE');
        $context = Context::getContext();

        if ($source === 'iqit_elementor') {
            // Estrai le immagini direttamente dall'HTML della homepage
            $banner = $this->extractIqitElementorBanner();
            $this->responseStatus = 200;
            $this->responseData = $banner;
            echo json_encode($this->responseData, JSON_PRETTY_PRINT);
            $this->logApiCall($this->responseStatus, $this->responseData);
            exit;
        } else {
            // Banner statico personalizzato per l'opzione 'custom'
            $customBanner = Configuration::get('HBAPI_CUSTOM_BANNER', _PS_BASE_URL_ . '/img/cms/placeholder.jpg');
            $customBannerMobile = Configuration::get('HBAPI_CUSTOM_BANNER_MOBILE', $customBanner);

            // Se il banner mobile non è impostato, usa lo stesso del desktop
            if (empty($customBannerMobile)) {
                $customBannerMobile = $customBanner;
            }

            $banner = [
                'image' => $customBanner,
                'image_mobile' => $customBannerMobile,
                'link' => $context->link->getPageLink('index'),
                'link_mobile' => $context->link->getPageLink('index'),
                'title' => 'Banner Statico',
                'description' => 'Banner personalizzato dal modulo API',
                'source' => 'Static Banner',
                'slides' => [[
                    'image' => $customBanner,
                    'title' => 'Banner Principale',
                    'description' => 'Banner personalizzato',
                    'link' => $context->link->getPageLink('index')
                ]]
            ];
        }

        // Ensure cache dir exists
        if (!is_dir(dirname($cacheFile))) {
            mkdir(dirname($cacheFile), 0755, true);
        }

        file_put_contents($cacheFile, json_encode($banner));

        $this->responseStatus = 200;
        $this->responseData = $banner;
        echo json_encode($this->responseData, JSON_PRETTY_PRINT);
        $this->logApiCall($this->responseStatus, $this->responseData);
        exit;
    }
}
