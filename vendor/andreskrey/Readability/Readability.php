<?php

namespace andreskrey\Readability;

use andreskrey\Readability\Nodes\DOM\DOMDocument;
use andreskrey\Readability\Nodes\DOM\DOMElement;
use andreskrey\Readability\Nodes\DOM\DOMNode;
use andreskrey\Readability\Nodes\DOM\DOMText;
use andreskrey\Readability\Nodes\NodeUtility;
use Psr\Log\LoggerInterface;

/**
 * Class Readability.
 */
class Readability
{
    /**
     * Main DOMDocument where all the magic happens.
     *
     * @var DOMDocument
     */
    protected $dom;

    /**
     * Title of the article.
     *
     * @var string|null
     */
    protected $title = null;

    /**
     * Final DOMDocument with the fully parsed HTML.
     *
     * @var DOMDocument|null
     */
    protected $content = null;

    /**
     * Excerpt of the article.
     *
     * @var string|null
     */
    protected $excerpt = null;

    /**
     * Main image of the article.
     *
     * @var string|null
     */
    protected $image = null;

    /**
     * Author of the article. Extracted from the byline tags and other social media properties.
     *
     * @var string|null
     */
    protected $author = null;

    /**
     * Direction of the text.
     *
     * @var string|null
     */
    protected $direction = null;

    /**
     * Configuration object.
     *
     * @var Configuration
     */
    private $configuration;

    /**
     * Logger object.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Collection of attempted text extractions.
     *
     * @var array
     */
    private $attempts = [];

    /**
     * @var array
     */
    private $defaultTagsToScore = [
        'section',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'p',
        'td',
        'pre',
    ];

    /**
     * @var array
     */
    private $alterToDIVExceptions = [
        'div',
        'article',
        'section',
        'p',
    ];

    /**
     * Readability constructor.
     *
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
        $this->logger = $this->configuration->getLogger();
    }

    /**
     * Main parse function.
     *
     * @param $html
     *
     * @throws ParseException
     *
     * @return array|bool
     */
    public function parse($html)
    {
        $this->logger->info('*** Starting parse process...');

        $this->dom = $this->loadHTML($html);

        // Checking for minimum HTML to work with.
        if (!($root = $this->dom->getElementsByTagName('body')->item(0)) || !$root->firstChild) {
            $this->logger->emergency('No body tag present or body tag empty');

            throw new ParseException('Invalid or incomplete HTML.');
        }

        $this->getMetadata();

        $this->getMainImage();

        while (true) {
            $root = $root->firstChild;

            $elementsToScore = $this->getNodes($root);
            $this->logger->debug(sprintf('Elements to score: \'%s\'', count($elementsToScore)));

            $result = $this->rateNodes($elementsToScore);

            /*
             * Now that we've gone through the full algorithm, check to see if
             * we got any meaningful content. If we didn't, we may need to re-run
             * grabArticle with different flags set. This gives us a higher likelihood of
             * finding the content, and the sieve approach gives us a higher likelihood of
             * finding the -right- content.
             */

            $length = mb_strlen(preg_replace(NodeUtility::$regexps['onlyWhitespace'], '', $result->textContent));

            $this->logger->info(sprintf('[Parsing] Article parsed. Amount of words: %s. Current threshold is: %s', $length, $this->configuration->getWordThreshold()));

            $parseSuccessful = true;

            if ($result && $length < $this->configuration->getWordThreshold()) {
                $this->dom = $this->loadHTML($html);
                $root = $this->dom->getElementsByTagName('body')->item(0);
                $parseSuccessful = false;

                if ($this->configuration->getStripUnlikelyCandidates()) {
                    $this->logger->debug('[Parsing] Threshold not met, trying again setting StripUnlikelyCandidates as false');
                    $this->configuration->setStripUnlikelyCandidates(false);
                    $this->attempts[] = ['articleContent' => $result, 'textLength' => $length];
                } elseif ($this->configuration->getWeightClasses()) {
                    $this->logger->debug('[Parsing] Threshold not met, trying again setting WeightClasses as false');
                    $this->configuration->setWeightClasses(false);
                    $this->attempts[] = ['articleContent' => $result, 'textLength' => $length];
                } elseif ($this->configuration->getCleanConditionally()) {
                    $this->logger->debug('[Parsing] Threshold not met, trying again setting CleanConditionally as false');
                    $this->configuration->setCleanConditionally(false);
                    $this->attempts[] = ['articleContent' => $result, 'textLength' => $length];
                } else {
                    $this->logger->debug('[Parsing] Threshold not met, searching across attempts for some content.');
                    $this->attempts[] = ['articleContent' => $result, 'textLength' => $length];

                    // No luck after removing flags, just return the longest text we found during the different loops
                    usort($this->attempts, function ($a, $b) {
                        return $a['textLength'] < $b['textLength'];
                    });

                    // But first check if we actually have something
                    if (!$this->attempts[0]['textLength']) {
                        $this->logger->emergency('[Parsing] Could not parse text, giving up :(');

                        throw new ParseException('Could not parse text.');
                    }

                    $this->logger->debug('[Parsing] Threshold not met, but found some content in previous attempts.');

                    $result = $this->attempts[0]['articleContent'];
                    $parseSuccessful = true;
                    break;
                }
            } else {
                break;
            }
        }

        if ($parseSuccessful) {
            $result = $this->postProcessContent($result);

            // If we haven't found an excerpt in the article's metadata, use the article's
            // first paragraph as the excerpt. This can be used for displaying a preview of
            // the article's content.
            if (!$this->getExcerpt()) {
                $this->logger->debug('[Parsing] No excerpt text found on metadata, extracting first p node and using it as excerpt.');
                $paragraphs = $result->getElementsByTagName('p');
                if ($paragraphs->length > 0) {
                    $this->setExcerpt(trim($paragraphs->item(0)->textContent));
                }
            }

            $this->setContent($result);

            $this->logger->info('*** Parse successful :)');

            return true;
        }
    }

    /**
     * Creates a DOM Document object and loads the provided HTML on it.
     *
     * Used for the first load of Readability and subsequent reloads (when disabling flags and rescanning the text)
     * Previous versions of Readability used this method one time and cloned the DOM to keep a backup. This caused bugs
     * because cloning the DOM object keeps a relation between the clone and the original one, doing changes in both
     * objects and ruining the backup.
     *
     * @param string $html
     *
     * @return DOMDocument
     */
    private function loadHTML($html)
    {
        $this->logger->debug('[Loading] Loading HTML...');

        // To avoid throwing a gazillion of errors on malformed HTMLs
        libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'utf-8');

        if (!$this->configuration->getSubstituteEntities()) {
            // Keep the original HTML entities
            $dom->substituteEntities = false;
        }

        if ($this->configuration->getNormalizeEntities()) {
            $this->logger->debug('[Loading] Normalized entities via mb_convert_encoding.');
            // Replace UTF-8 characters with the HTML Entity equivalent. Useful to fix html with mixed content
            $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        }

        if ($this->configuration->getSummonCthulhu()) {
            $this->logger->debug('[Loading] Removed script tags via regex H̶͈̩̟̬̱͠E̡̨̬͔̳̜͢͠ ̡̧̯͉̩͙̩̹̞̠͎͈̹̥̠͞ͅͅC̶͉̞̘̖̝̗͓̬̯͍͉̤̬͢͢͞Ò̟̘͉͖͎͉̱̭̣̕M̴̯͈̻̱̱̣̗͈̠̙̲̥͘͞E̷̛͙̼̲͍͕̹͍͇̗̻̬̮̭̱̥͢Ş̛̟͔̙̜̤͇̮͍̙̝̀͘');
            $html = preg_replace('/<script\b[^>]*>([\s\S]*?)<\/script>/', '', $html);
        }

        // Prepend the XML tag to avoid having issues with special characters. Should be harmless.
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $dom->encoding = 'UTF-8';

        $this->removeScripts($dom);

        $this->prepDocument($dom);

        $this->logger->debug('[Loading] Loaded HTML successfully.');

        return $dom;
    }

    /**
     * Tries to guess relevant info from metadata of the html. Sets the results in the Readability properties.
     */
    private function getMetadata()
    {
        $this->logger->debug('[Metadata] Retrieving metadata...');

        $values = [];
        // Match "description", or Twitter's "twitter:description" (Cards)
        // in name attribute.
        $namePattern = '/^\s*((twitter)\s*:\s*)?(description|title|image)\s*$/i';

        // Match Facebook's Open Graph title & description properties.
        $propertyPattern = '/^\s*og\s*:\s*(description|title|image)\s*$/i';

        foreach ($this->dom->getElementsByTagName('meta') as $meta) {
            /* @var DOMNode $meta */
            $elementName = $meta->getAttribute('name');
            $elementProperty = $meta->getAttribute('property');

            if (in_array('author', [$elementName, $elementProperty])) {
                $this->logger->info(sprintf('[Metadata] Found author: \'%s\'', $meta->getAttribute('content')));
                $this->setAuthor($meta->getAttribute('content'));
                continue;
            }

            $name = null;
            if (preg_match($namePattern, $elementName)) {
                $name = $elementName;
            } elseif (preg_match($propertyPattern, $elementProperty)) {
                $name = $elementProperty;
            }

            if ($name) {
                $content = $meta->getAttribute('content');
                if ($content) {
                    // Convert to lowercase and remove any whitespace
                    // so we can match below.
                    $name = preg_replace('/\s/', '', strtolower($name));
                    $values[$name] = trim($content);
                }
            }
        }
        if (array_key_exists('description', $values)) {
            $this->logger->info(sprintf('[Metadata] Found excerpt in \'description\' tag: \'%s\'', $values['description']));
            $this->setExcerpt($values['description']);
        } elseif (array_key_exists('og:description', $values)) {
            // Use facebook open graph description.
            $this->logger->info(sprintf('[Metadata] Found excerpt in \'og:description\' tag: \'%s\'', $values['og:description']));
            $this->setExcerpt($values['og:description']);
        } elseif (array_key_exists('twitter:description', $values)) {
            // Use twitter cards description.
            $this->logger->info(sprintf('[Metadata] Found excerpt in \'twitter:description\' tag: \'%s\'', $values['twitter:description']));
            $this->setExcerpt($values['twitter:description']);
        }

        $this->setTitle($this->getArticleTitle());

        if (!$this->getTitle()) {
            if (array_key_exists('og:title', $values)) {
                // Use facebook open graph title.
                $this->logger->info(sprintf('[Metadata] Found title in \'og:title\' tag: \'%s\'', $values['og:title']));
                $this->setTitle($values['og:title']);
            } elseif (array_key_exists('twitter:title', $values)) {
                // Use twitter cards title.
                $this->logger->info(sprintf('[Metadata] Found title in \'twitter:title\' tag: \'%s\'', $values['twitter:title']));
                $this->setTitle($values['twitter:title']);
            }
        }

        if (array_key_exists('og:image', $values) || array_key_exists('twitter:image', $values)) {
            if (array_key_exists('og:image', $values)) {
                $this->logger->info(sprintf('[Metadata] Found main image in \'og:image\' tag: \'%s\'', $values['og:image']));
                $this->setImage($values['og:image']);
            } else {
                $this->logger->info(sprintf('[Metadata] Found main image in \'twitter:image\' tag: \'%s\'', $values['twitter:image']));
                $this->setImage($values['twitter:image']);
            }
        }
    }

    /**
     * Returns all the images of the parsed article.
     *
     * @return array
     */
    public function getImages()
    {
        $result = [];
        if ($this->getImage()) {
            $result[] = $this->getImage();
        }

        if (null == $this->getDOMDocument()) {
            return $result;
        }

        foreach ($this->getDOMDocument()->getElementsByTagName('img') as $img) {
            if ($src = $img->getAttribute('src')) {
                $result[] = $src;
            }
        }

        if ($this->configuration->getFixRelativeURLs()) {
            foreach ($result as &$imgSrc) {
                $imgSrc = $this->toAbsoluteURI($imgSrc);
            }
        }

        $result = array_unique(array_filter($result));

        return $result;
    }

    /**
     * Tries to get the main article image. Will only update the metadata if the getMetadata function couldn't
     * find a correct image.
     */
    public function getMainImage()
    {
        $imgUrl = false;

        if ($this->getImage() !== null) {
            $imgUrl = $this->getImage();
        }

        if (!$imgUrl) {
            foreach ($this->dom->getElementsByTagName('link') as $link) {
                /** @var \DOMElement $link */
                /*
                 * Check for the rel attribute, then check if the rel attribute is either img_src or image_src, and
                 * finally check for the existence of the href attribute, which should hold the image url.
                 */
                if ($link->hasAttribute('rel') && ($link->getAttribute('rel') === 'img_src' || $link->getAttribute('rel') === 'image_src') && $link->hasAttribute('href')) {
                    $imgUrl = $link->getAttribute('href');
                    break;
                }
            }
        }

        if (!empty($imgUrl) && $this->configuration->getFixRelativeURLs()) {
            $this->setImage($this->toAbsoluteURI($imgUrl));
        }
    }

    /**
     * Returns the title of the html. Prioritizes the title from the metadata against the title tag.
     *
     * @return string|null
     */
    private function getArticleTitle()
    {
        $originalTitle = null;

        if ($this->getTitle()) {
            $originalTitle = $this->getTitle();
        } else {
            $this->logger->debug('[Metadata] Could not find title in metadata, searching for the title tag...');
            $titleTag = $this->dom->getElementsByTagName('title');
            if ($titleTag->length > 0) {
                $this->logger->info(sprintf('[Metadata] Using title tag as article title: \'%s\'', $titleTag->item(0)->nodeValue));
                $originalTitle = $titleTag->item(0)->nodeValue;
            }
        }

        if ($originalTitle === null) {
            return null;
        }

        $curTitle = $originalTitle;
        $titleHadHierarchicalSeparators = false;

        /*
         * If there's a separator in the title, first remove the final part
         *
         * Sanity warning: if you eval this match in PHPStorm's "Evaluate expression" box, it will return false
         * I can assure you it works properly if you let the code run.
         */
        if (preg_match('/ [\|\-\\\\\/>»] /i', $curTitle)) {
            $titleHadHierarchicalSeparators = (bool)preg_match('/ [\\\\\/>»] /', $curTitle);
            $curTitle = preg_replace('/(.*)[\|\-\\\\\/>»] .*/i', '$1', $originalTitle);

            $this->logger->info(sprintf('[Metadata] Found hierarchical separators in title, new title is: \'%s\'', $curTitle));

            // If the resulting title is too short (3 words or fewer), remove
            // the first part instead:
            if (count(preg_split('/\s+/', $curTitle)) < 3) {
                $curTitle = preg_replace('/[^\|\-\\\\\/>»]*[\|\-\\\\\/>»](.*)/i', '$1', $originalTitle);
                $this->logger->info(sprintf('[Metadata] Title too short, using the first part of the title instead: \'%s\'', $curTitle));
            }
        } elseif (strpos($curTitle, ': ') !== false) {
            // Check if we have an heading containing this exact string, so we
            // could assume it's the full title.
            $match = false;
            for ($i = 1; $i <= 2; $i++) {
                foreach ($this->dom->getElementsByTagName('h' . $i) as $hTag) {
                    // Trim texts to avoid having false negatives when the title is surrounded by spaces or tabs
                    if (trim($hTag->nodeValue) === trim($curTitle)) {
                        $match = true;
                    }
                }
            }

            // If we don't, let's extract the title out of the original title string.
            if (!$match) {
                $curTitle = substr($originalTitle, strrpos($originalTitle, ':') + 1);

                $this->logger->info(sprintf('[Metadata] Title has a colon in the middle, new title is: \'%s\'', $curTitle));

                // If the title is now too short, try the first colon instead:
                if (count(preg_split('/\s+/', $curTitle)) < 3) {
                    $curTitle = substr($originalTitle, strpos($originalTitle, ':') + 1);
                    $this->logger->info(sprintf('[Metadata] Title too short, using the first part of the title instead: \'%s\'', $curTitle));
                } elseif (count(preg_split('/\s+/', substr($curTitle, 0, strpos($curTitle, ':')))) > 5) {
                    // But if we have too many words before the colon there's something weird
                    // with the titles and the H tags so let's just use the original title instead
                    $curTitle = $originalTitle;
                }
            }
        } elseif (mb_strlen($curTitle) > 150 || mb_strlen($curTitle) < 15) {
            $hOnes = $this->dom->getElementsByTagName('h1');

            if ($hOnes->length === 1) {
                $curTitle = $hOnes->item(0)->nodeValue;
                $this->logger->info(sprintf('[Metadata] Using title from an H1 node: \'%s\'', $curTitle));
            }
        }

        $curTitle = trim($curTitle);

        /*
         * If we now have 4 words or fewer as our title, and either no
         * 'hierarchical' separators (\, /, > or ») were found in the original
         * title or we decreased the number of words by more than 1 word, use
         * the original title.
         */
        $curTitleWordCount = count(preg_split('/\s+/', $curTitle));
        $originalTitleWordCount = count(preg_split('/\s+/', preg_replace('/[\|\-\\\\\/>»]+/', '', $originalTitle))) - 1;

        if ($curTitleWordCount <= 4 &&
            (!$titleHadHierarchicalSeparators || $curTitleWordCount !== $originalTitleWordCount)) {
            $curTitle = $originalTitle;

            $this->logger->info(sprintf('Using title from an H1 node: \'%s\'', $curTitle));
        }

        return $curTitle;
    }

    /**
     * Convert URI to an absolute URI.
     *
     * @param $uri string URI to convert
     *
     * @return string
     */
    private function toAbsoluteURI($uri)
    {
        list($pathBase, $scheme, $prePath) = $this->getPathInfo($this->configuration->getOriginalURL());

        // If this is already an absolute URI, return it.
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9\+\-\.]*:/', $uri)) {
            return $uri;
        }

        // Scheme-rooted relative URI.
        if (substr($uri, 0, 2) === '//') {
            return $scheme . '://' . substr($uri, 2);
        }

        // Prepath-rooted relative URI.
        if (substr($uri, 0, 1) === '/') {
            return $prePath . $uri;
        }

        // Dotslash relative URI.
        if (strpos($uri, './') === 0) {
            return $pathBase . substr($uri, 2);
        }
        // Ignore hash URIs:
        if (substr($uri, 0, 1) === '#') {
            return $uri;
        }

        // Standard relative URI; add entire path. pathBase already includes a
        // trailing "/".
        return $pathBase . $uri;
    }

    /**
     * Returns full path info of an URL.
     *
     * @param  string $url
     *
     * @return array [$pathBase, $scheme, $prePath]
     */
    public function getPathInfo($url)
    {
        // Check for base URLs
        if ($this->dom->baseURI !== null) {
            if (substr($this->dom->baseURI, 0, 1) === '/') {
                // URLs starting with '/' override completely the URL defined in the link
                $pathBase = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . $this->dom->baseURI;
            } else {
                // Otherwise just prepend the base to the actual path
                $pathBase = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . dirname(parse_url($url, PHP_URL_PATH)) . '/' . rtrim($this->dom->baseURI, '/') . '/';
            }
        } else {
            $pathBase = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . dirname(parse_url($url, PHP_URL_PATH)) . '/';
        }

        $scheme = parse_url($pathBase, PHP_URL_SCHEME);
        $prePath = $scheme . '://' . parse_url($pathBase, PHP_URL_HOST);

        return [$pathBase, $scheme, $prePath];
    }

    /**
     * Gets nodes from the root element.
     *
     * @param $node DOMNode|DOMText
     *
     * @return array
     */
    private function getNodes($node)
    {
        $this->logger->info('[Get Nodes] Retrieving nodes...');

        $stripUnlikelyCandidates = $this->configuration->getStripUnlikelyCandidates();

        $elementsToScore = [];

        /*
         * First, node prepping. Trash nodes that look cruddy (like ones with the
         * class name "comment", etc), and turn divs into P tags where they have been
         * used inappropriately (as in, where they contain no other block level elements.)
         */

        while ($node) {
            $matchString = $node->getAttribute('class') . ' ' . $node->getAttribute('id');

            // Remove DOMComments nodes as we don't need them and mess up children counting
            if ($node->nodeType === XML_COMMENT_NODE) {
                $this->logger->debug(sprintf('[Get Nodes] Found comment node, removing... Node content was: \'%s\'', substr($node->nodeValue, 0, 128)));
                $node = NodeUtility::removeAndGetNext($node);
                continue;
            }

            // Check to see if this node is a byline, and remove it if it is.
            if ($this->checkByline($node, $matchString)) {
                $this->logger->debug(sprintf('[Get Nodes] Found byline, removing... Node content was: \'%s\'', substr($node->nodeValue, 0, 128)));
                $node = NodeUtility::removeAndGetNext($node);
                continue;
            }

            // Remove unlikely candidates
            if ($stripUnlikelyCandidates) {
                if (
                    preg_match(NodeUtility::$regexps['unlikelyCandidates'], $matchString) &&
                    !preg_match(NodeUtility::$regexps['okMaybeItsACandidate'], $matchString) &&
                    $node->nodeName !== 'body' &&
                    $node->nodeName !== 'a'
                ) {
                    $this->logger->debug(sprintf('[Get Nodes] Removing unlikely candidate. Node content was: \'%s\'', substr($node->nodeValue, 0, 128)));
                    $node = NodeUtility::removeAndGetNext($node);
                    continue;
                }
            }

            // Remove DIV, SECTION, and HEADER nodes without any content(e.g. text, image, video, or iframe).
            if (($node->nodeName === 'div' || $node->nodeName === 'section' || $node->nodeName === 'header' ||
                    $node->nodeName === 'h1' || $node->nodeName === 'h2' || $node->nodeName === 'h3' ||
                    $node->nodeName === 'h4' || $node->nodeName === 'h5' || $node->nodeName === 'h6' ||
                    $node->nodeName === 'p') &&
                $node->isElementWithoutContent()) {
                $this->logger->debug(sprintf('[Get Nodes] Removing empty \'%s\' node.', $node->nodeName));
                $node = NodeUtility::removeAndGetNext($node);
                continue;
            }

            if (in_array(strtolower($node->nodeName), $this->defaultTagsToScore)) {
                $this->logger->debug(sprintf('[Get Nodes] Adding node to score list, node content is: \'%s\'', substr($node->nodeValue, 0, 128)));
                $elementsToScore[] = $node;
            }

            // Turn all divs that don't have children block level elements into p's
            if ($node->nodeName === 'div') {
                /*
                 * Sites like http://mobile.slate.com encloses each paragraph with a DIV
                 * element. DIVs with only a P element inside and no text content can be
                 * safely converted into plain P elements to avoid confusing the scoring
                 * algorithm with DIVs with are, in practice, paragraphs.
                 */
                if ($node->hasSinglePNode()) {
                    $this->logger->debug(sprintf('[Get Nodes] Found DIV with a single P node, removing DIV. Node content is: \'%s\'', substr($node->nodeValue, 0, 128)));
                    $pNode = $node->getChildren(true)[0];
                    $node->parentNode->replaceChild($pNode, $node);
                    $node = $pNode;
                    $elementsToScore[] = $node;
                } elseif (!$node->hasSingleChildBlockElement()) {
                    $this->logger->debug(sprintf('[Get Nodes] Found DIV with a single child block element, converting to a P node. Node content is: \'%s\'', substr($node->nodeValue, 0, 128)));
                    $node = NodeUtility::setNodeTag($node, 'p');
                    $elementsToScore[] = $node;
                } else {
                    // EXPERIMENTAL
                    foreach ($node->getChildren() as $child) {
                        /** @var $child DOMNode */
                        if ($child->nodeType === XML_TEXT_NODE && mb_strlen(trim($child->getTextContent())) > 0) {
                            $this->logger->debug(sprintf('[Get Nodes] Found DIV a text node inside, converting to a P node. Node content is: \'%s\'', substr($node->nodeValue, 0, 128)));
                            $newNode = $node->createNode($child, 'p');
                            $child->parentNode->replaceChild($newNode, $child);
                        }
                    }
                }
            }

            $node = NodeUtility::getNextNode($node);
        }

        return $elementsToScore;
    }

    /**
     * Checks if the node is a byline.
     *
     * @param DOMNode $node
     * @param string $matchString
     *
     * @return bool
     */
    private function checkByline($node, $matchString)
    {
        if (!$this->configuration->getArticleByLine()) {
            return false;
        }

        /*
         * Check if the byline is already set
         */
        if ($this->getAuthor()) {
            return false;
        }

        $rel = $node->getAttribute('rel');

        if ($rel === 'author' || preg_match(NodeUtility::$regexps['byline'], $matchString) && $this->isValidByline($node->getTextContent())) {
            $this->logger->info(sprintf('[Metadata] Found article author: \'%s\'', $node->getTextContent()));
            $this->setAuthor(trim($node->getTextContent()));

            return true;
        }

        return false;
    }

    /**
     * Checks the validity of a byLine. Based on string length.
     *
     * @param string $text
     *
     * @return bool
     */
    private function isValidByline($text)
    {
        if (gettype($text) == 'string') {
            $byline = trim($text);

            return (mb_strlen($byline) > 0) && (mb_strlen($text) < 100);
        }

        return false;
    }

    /**
     * Removes all the scripts of the html.
     *
     * @param DOMDocument $dom
     */
    private function removeScripts(DOMDocument $dom)
    {
        $toRemove = ['script', 'noscript'];

        foreach ($toRemove as $tag) {
            while ($script = $dom->getElementsByTagName($tag)) {
                if ($script->item(0)) {
                    $script->item(0)->parentNode->removeChild($script->item(0));
                } else {
                    break;
                }
            }
        }
    }

    /**
     * Prepares the document for parsing.
     *
     * @param DOMDocument $dom
     */
    private function prepDocument(DOMDocument $dom)
    {
        $this->logger->info('[PrepDocument] Preparing document for parsing...');

        /*
         * DOMNodeList must be converted to an array before looping over it.
         * This is done to avoid node shifting when removing nodes.
         *
         * Reverse traversing cannot be done here because we need to find brs that are right next to other brs.
         * (If we go the other way around we need to search for previous nodes forcing the creation of new functions
         * that will be used only here)
         */
        foreach (iterator_to_array($dom->getElementsByTagName('br')) as $br) {
            $next = $br->nextSibling;

            /*
             * Whether 2 or more <br> elements have been found and replaced with a
             * <p> block.
             */
            $replaced = false;

            /*
             * If we find a <br> chain, remove the <br>s until we hit another element
             * or non-whitespace. This leaves behind the first <br> in the chain
             * (which will be replaced with a <p> later).
             */
            while (($next = NodeUtility::nextElement($next)) && ($next->nodeName === 'br')) {
                $this->logger->debug('[PrepDocument] Removing chain of BR nodes...');

                $replaced = true;
                $brSibling = $next->nextSibling;
                $next->parentNode->removeChild($next);
                $next = $brSibling;
            }

            /*
             * If we removed a <br> chain, replace the remaining <br> with a <p>. Add
             * all sibling nodes as children of the <p> until we hit another <br>
             * chain.
             */

            if ($replaced) {
                $p = $dom->createElement('p');
                $br->parentNode->replaceChild($p, $br);

                $next = $p->nextSibling;
                while ($next) {
                    // If we've hit another <br><br>, we're done adding children to this <p>.
                    if ($next->nodeName === 'br') {
                        $nextElem = NodeUtility::nextElement($next);
                        if ($nextElem && $nextElem->nodeName === 'br') {
                            break;
                        }
                    }

                    $this->logger->debug('[PrepDocument] Replacing BR with a P node...');

                    // Otherwise, make this node a child of the new <p>.
                    $sibling = $next->nextSibling;
                    $p->appendChild($next);
                    $next = $sibling;
                }
            }
        }

        // Replace font tags with span
        $fonts = $dom->getElementsByTagName('font');
        $length = $fonts->length;
        for ($i = 0; $i < $length; $i++) {
            $this->logger->debug('[PrepDocument] Converting font tag into a span tag.');
            $font = $fonts->item($length - 1 - $i);
            NodeUtility::setNodeTag($font, 'span', true);
        }
    }

    /**
     * Assign scores to each node. Returns full article parsed or false on error.
     *
     * @param array $nodes
     *
     * @return DOMDocument|bool
     */
    private function rateNodes($nodes)
    {
        $this->logger->info('[Rating] Rating nodes...');

        $candidates = [];

        /** @var DOMElement $node */
        foreach ($nodes as $node) {
            if (is_null($node->parentNode)) {
                continue;
            }

            // Discard nodes with less than 25 characters, without blank space
            if (mb_strlen($node->getTextContent(true)) < 25) {
                continue;
            }

            $ancestors = $node->getNodeAncestors();

            // Exclude nodes with no ancestor
            if (count($ancestors) === 0) {
                continue;
            }

            // Start with a point for the paragraph itself as a base.
            $contentScore = 1;

            // Add points for any commas within this paragraph.
            $contentScore += count(explode(',', $node->getTextContent(true)));

            // For every 100 characters in this paragraph, add another point. Up to 3 points.
            $contentScore += min(floor(mb_strlen($node->getTextContent(true)) / 100), 3);

            $this->logger->debug(sprintf('[Rating] Node score %s, content: \'%s\'', $contentScore, substr($node->nodeValue, 0, 128)));

            /** @var $ancestor DOMElement */
            foreach ($ancestors as $level => $ancestor) {
                $this->logger->debug('[Rating] Found ancestor, initializing and adding it as a candidate...');
                if (!$ancestor->isInitialized()) {
                    $ancestor->initializeNode($this->configuration->getWeightClasses());
                    $candidates[] = $ancestor;
                }

                /*
                 * Node score divider:
                 *  - parent:             1 (no division)
                 *  - grandparent:        2
                 *  - great grandparent+: ancestor level * 3
                 */

                if ($level === 0) {
                    $scoreDivider = 1;
                } elseif ($level === 1) {
                    $scoreDivider = 2;
                } else {
                    $scoreDivider = $level * 3;
                }

                $currentScore = $ancestor->contentScore;
                $ancestor->contentScore = $currentScore + ($contentScore / $scoreDivider);

                $this->logger->debug(sprintf('[Rating] Ancestor score %s, value: \'%s\'', $ancestor->contentScore, substr($ancestor->nodeValue, 0, 128)));
            }
        }

        /*
         * After we've calculated scores, loop through all of the possible
         * candidate nodes we found and find the one with the highest score.
         */

        $topCandidates = [];
        foreach ($candidates as $candidate) {

            /*
             * Scale the final candidates score based on link density. Good content
             * should have a relatively small link density (5% or less) and be mostly
             * unaffected by this operation.
             */

            $candidate->contentScore = $candidate->contentScore * (1 - $candidate->getLinkDensity());

            for ($i = 0; $i < $this->configuration->getMaxTopCandidates(); $i++) {
                $aTopCandidate = isset($topCandidates[$i]) ? $topCandidates[$i] : null;

                if (!$aTopCandidate || $candidate->contentScore > $aTopCandidate->contentScore) {
                    array_splice($topCandidates, $i, 0, [$candidate]);
                    if (count($topCandidates) > $this->configuration->getMaxTopCandidates()) {
                        array_pop($topCandidates);
                    }
                    break;
                }
            }
        }

        $topCandidate = isset($topCandidates[0]) ? $topCandidates[0] : null;
        $parentOfTopCandidate = null;

        /*
         * If we still have no top candidate, just use the body as a last resort.
         * We also have to copy the body node so it is something we can modify.
         */

        if ($topCandidate === null || $topCandidate->nodeName === 'body') {
            $this->logger->info('[Rating] No top candidate found or top candidate is the body tag. Moving all child nodes to a new DIV node.');

            // Move all of the page's children into topCandidate
            $topCandidate = new DOMDocument('1.0', 'utf-8');
            $topCandidate->encoding = 'UTF-8';
            $topCandidate->appendChild($topCandidate->createElement('div', ''));
            $kids = $this->dom->getElementsByTagName('body')->item(0)->childNodes;

            // Cannot be foreached, don't ask me why.
            for ($i = 0; $i < $kids->length; $i++) {
                $import = $topCandidate->importNode($kids->item($i), true);
                $topCandidate->firstChild->appendChild($import);
            }

            // Candidate must be created using firstChild to grab the DOMElement instead of the DOMDocument.
            $topCandidate = $topCandidate->firstChild;
        } elseif ($topCandidate) {
            $this->logger->info(sprintf('[Rating] Found top candidate, score: %s', $topCandidate->contentScore));
            // Find a better top candidate node if it contains (at least three) nodes which belong to `topCandidates` array
            // and whose scores are quite closed with current `topCandidate` node.
            $alternativeCandidateAncestors = [];
            for ($i = 1; $i < count($topCandidates); $i++) {
                if ($topCandidates[$i]->contentScore / $topCandidate->contentScore >= 0.75) {
                    array_push($alternativeCandidateAncestors, $topCandidates[$i]->getNodeAncestors(false));
                }
            }

            $MINIMUM_TOPCANDIDATES = 3;
            if (count($alternativeCandidateAncestors) >= $MINIMUM_TOPCANDIDATES) {
                $parentOfTopCandidate = $topCandidate->parentNode;
                while ($parentOfTopCandidate->nodeName !== 'body') {
                    $listsContainingThisAncestor = 0;
                    for ($ancestorIndex = 0; $ancestorIndex < count($alternativeCandidateAncestors) && $listsContainingThisAncestor < $MINIMUM_TOPCANDIDATES; $ancestorIndex++) {
                        $listsContainingThisAncestor += (int)in_array($parentOfTopCandidate, $alternativeCandidateAncestors[$ancestorIndex]);
                    }
                    if ($listsContainingThisAncestor >= $MINIMUM_TOPCANDIDATES) {
                        $topCandidate = $parentOfTopCandidate;
                        break;
                    }
                    $parentOfTopCandidate = $parentOfTopCandidate->parentNode;
                }
            }

            /*
             * Because of our bonus system, parents of candidates might have scores
             * themselves. They get half of the node. There won't be nodes with higher
             * scores than our topCandidate, but if we see the score going *up* in the first
             * few steps up the tree, that's a decent sign that there might be more content
             * lurking in other places that we want to unify in. The sibling stuff
             * below does some of that - but only if we've looked high enough up the DOM
             * tree.
             */

            $parentOfTopCandidate = $topCandidate->parentNode;
            $lastScore = $topCandidate->contentScore;

            // The scores shouldn't get too low.
            $scoreThreshold = $lastScore / 3;

            /* @var DOMElement $parentOfTopCandidate */
            // Check if we are actually dealing with a DOMNode and not a DOMDocument node or higher
            while ($parentOfTopCandidate->nodeName !== 'body' && $parentOfTopCandidate->nodeType === XML_ELEMENT_NODE) {
                $parentScore = $parentOfTopCandidate->contentScore;
                if ($parentScore < $scoreThreshold) {
                    break;
                }

                if ($parentScore > $lastScore) {
                    // Alright! We found a better parent to use.
                    $topCandidate = $parentOfTopCandidate;
                    $this->logger->info('[Rating] Found a better top candidate.');
                    break;
                }
                $lastScore = $parentOfTopCandidate->contentScore;
                $parentOfTopCandidate = $parentOfTopCandidate->parentNode;
            }

            // If the top candidate is the only child, use parent instead. This will help sibling
            // joining logic when adjacent content is actually located in parent's sibling node.
            $parentOfTopCandidate = $topCandidate->parentNode;
            while ($parentOfTopCandidate->nodeName !== 'body' && count($parentOfTopCandidate->getChildren(true)) === 1) {
                $topCandidate = $parentOfTopCandidate;
                $parentOfTopCandidate = $topCandidate->parentNode;
            }
        }

        /*
         * Now that we have the top candidate, look through its siblings for content
         * that might also be related. Things like preambles, content split by ads
         * that we removed, etc.
         */

        $this->logger->info('[Rating] Creating final article content document...');

        $articleContent = new DOMDocument('1.0', 'utf-8');
        $articleContent->createElement('div');

        $siblingScoreThreshold = max(10, $topCandidate->contentScore * 0.2);
        // Keep potential top candidate's parent node to try to get text direction of it later.
        $parentOfTopCandidate = $topCandidate->parentNode;
        $siblings = $parentOfTopCandidate->getChildren();

        $hasContent = false;

        $this->logger->info('[Rating] Adding top candidate siblings...');

        /** @var DOMElement $sibling */
        foreach ($siblings as $sibling) {
            $append = false;

            if ($sibling === $topCandidate) {
                $this->logger->debug('[Rating] Sibling is equal to the top candidate, adding to the final article...');

                $append = true;
            } else {
                $contentBonus = 0;

                // Give a bonus if sibling nodes and top candidates have the example same classname
                if ($sibling->getAttribute('class') === $topCandidate->getAttribute('class') && $topCandidate->getAttribute('class') !== '') {
                    $contentBonus += $topCandidate->contentScore * 0.2;
                }
                if ($sibling->contentScore + $contentBonus >= $siblingScoreThreshold) {
                    $append = true;
                } elseif ($sibling->nodeName === 'p') {
                    $linkDensity = $sibling->getLinkDensity();
                    $nodeContent = $sibling->getTextContent(true);

                    if (mb_strlen($nodeContent) > 80 && $linkDensity < 0.25) {
                        $append = true;
                    } elseif ($nodeContent && mb_strlen($nodeContent) < 80 && $linkDensity === 0 && preg_match('/\.( |$)/', $nodeContent)) {
                        $append = true;
                    }
                }
            }

            if ($append) {
                $this->logger->debug(sprintf('[Rating] Appending sibling to final article, content is: \'%s\'', substr($sibling->nodeValue, 0, 128)));

                $hasContent = true;

                if (!in_array(strtolower($sibling->nodeName), $this->alterToDIVExceptions)) {
                    /*
                     * We have a node that isn't a common block level element, like a form or td tag.
                     * Turn it into a div so it doesn't get filtered out later by accident.
                     */

                    $sibling = NodeUtility::setNodeTag($sibling, 'div');
                }

                $import = $articleContent->importNode($sibling, true);
                $articleContent->appendChild($import);

                /*
                 * No node shifting needs to be check because when calling getChildren, an array is made with the
                 * children of the parent node, instead of using the DOMElement childNodes function, which, when used
                 * along with appendChild, would shift the nodes position and the current foreach will behave in
                 * unpredictable ways.
                 */
            }
        }

        $articleContent = $this->prepArticle($articleContent);

        if ($hasContent) {
            // Find out text direction from ancestors of final top candidate.
            $ancestors = array_merge([$parentOfTopCandidate, $topCandidate], $parentOfTopCandidate->getNodeAncestors());
            foreach ($ancestors as $ancestor) {
                $articleDir = $ancestor->getAttribute('dir');
                if ($articleDir) {
                    $this->setDirection($articleDir);
                    $this->logger->debug(sprintf('[Rating] Found article direction: %s', $articleDir));
                    break;
                }
            }

            return $articleContent;
        } else {
            return false;
        }
    }

    /**
     * Cleans up the final article.
     *
     * @param DOMDocument $article
     *
     * @return DOMDocument
     */
    public function prepArticle(DOMDocument $article)
    {
        $this->logger->info('[PrepArticle] Preparing final article...');

        $this->_cleanStyles($article);
        $this->_clean($article, 'style');

        // Check for data tables before we continue, to avoid removing items in
        // those tables, which will often be isolated even though they're
        // visually linked to other content-ful elements (text, images, etc.).
        $this->_markDataTables($article);

        // Clean out junk from the article content
        $this->_cleanConditionally($article, 'form');
        $this->_cleanConditionally($article, 'fieldset');
        $this->_clean($article, 'object');
        $this->_clean($article, 'embed');
        $this->_clean($article, 'h1');
        $this->_clean($article, 'footer');
        $this->_clean($article, 'link');

        // Clean out elements have "share" in their id/class combinations from final top candidates,
        // which means we don't remove the top candidates even they have "share".
        foreach ($article->childNodes as $child) {
            $this->_cleanMatchedNodes($child, '/share/i');
        }

        /*
         * If there is only one h2 and its text content substantially equals article title,
         * they are probably using it as a header and not a subheader,
         * so remove it since we already extract the title separately.
         */
        $h2 = $article->getElementsByTagName('h2');
        if ($h2->length === 1) {
            $lengthSimilarRate = (mb_strlen($h2->item(0)->textContent) - mb_strlen($this->getTitle())) / max(mb_strlen($this->getTitle()), 1);

            if (abs($lengthSimilarRate) < 0.5) {
                if ($lengthSimilarRate > 0) {
                    $titlesMatch = strpos($h2->item(0)->textContent, $this->getTitle()) !== false;
                } else {
                    $titlesMatch = strpos($this->getTitle(), $h2->item(0)->textContent) !== false;
                }
                if ($titlesMatch) {
                    $this->logger->info('[PrepArticle] Found title repeated in an H2 node, removing...');
                    $this->_clean($article, 'h2');
                }
            }
        }

        $this->_clean($article, 'iframe');
        $this->_clean($article, 'input');
        $this->_clean($article, 'textarea');
        $this->_clean($article, 'select');
        $this->_clean($article, 'button');
        $this->_cleanHeaders($article);

        // Do these last as the previous stuff may have removed junk
        // that will affect these
        $this->_cleanConditionally($article, 'table');
        $this->_cleanConditionally($article, 'ul');
        $this->_cleanConditionally($article, 'div');

        $this->_cleanExtraParagraphs($article);

        foreach (iterator_to_array($article->getElementsByTagName('br')) as $br) {
            $next = $br->nextSibling;
            if ($next && $next->nodeName === 'p') {
                $this->logger->debug('[PrepArticle] Removing br node next to a p node.');
                $br->parentNode->removeChild($br);
            }
        }

        return $article;
    }

    /**
     * Look for 'data' (as opposed to 'layout') tables, for which we use
     * similar checks as
     * https://dxr.mozilla.org/mozilla-central/rev/71224049c0b52ab190564d3ea0eab089a159a4cf/accessible/html/HTMLTableAccessible.cpp#920.
     *
     * @param DOMDocument $article
     *
     * @return void
     */
    public function _markDataTables(DOMDocument $article)
    {
        $tables = $article->getElementsByTagName('table');
        foreach ($tables as $table) {
            /** @var DOMElement $table */
            $role = $table->getAttribute('role');
            if ($role === 'presentation') {
                $table->setReadabilityDataTable(false);
                continue;
            }
            $datatable = $table->getAttribute('datatable');
            if ($datatable == '0') {
                $table->setReadabilityDataTable(false);
                continue;
            }
            $summary = $table->getAttribute('summary');
            if ($summary) {
                $table->setReadabilityDataTable(true);
                continue;
            }

            $caption = $table->getElementsByTagName('caption');
            if ($caption->length > 0 && $caption->item(0)->childNodes->length > 0) {
                $table->setReadabilityDataTable(true);
                continue;
            }

            // If the table has a descendant with any of these tags, consider a data table:
            foreach (['col', 'colgroup', 'tfoot', 'thead', 'th'] as $dataTableDescendants) {
                if ($table->getElementsByTagName($dataTableDescendants)->length > 0) {
                    $table->setReadabilityDataTable(true);
                    continue 2;
                }
            }

            // Nested tables indicate a layout table:
            if ($table->getElementsByTagName('table')->length > 0) {
                $table->setReadabilityDataTable(false);
                continue;
            }

            $sizeInfo = $table->getRowAndColumnCount();
            if ($sizeInfo['rows'] >= 10 || $sizeInfo['columns'] > 4) {
                $table->setReadabilityDataTable(true);
                continue;
            }
            // Now just go by size entirely:
            $table->setReadabilityDataTable($sizeInfo['rows'] * $sizeInfo['columns'] > 10);
        }
    }

    /**
     * Remove the style attribute on every e and under.
     *
     * @param $node DOMDocument|DOMNode
     **/
    public function _cleanStyles($node)
    {
        if (property_exists($node, 'tagName') && $node->tagName === 'svg') {
            return;
        }

        // Do not bother if there's no method to remove an attribute
        if (method_exists($node, 'removeAttribute')) {
            $presentational_attributes = ['align', 'background', 'bgcolor', 'border', 'cellpadding', 'cellspacing', 'frame', 'hspace', 'rules', 'style', 'valign', 'vspace'];
            // Remove `style` and deprecated presentational attributes
            foreach ($presentational_attributes as $presentational_attribute) {
                $node->removeAttribute($presentational_attribute);
            }

            $deprecated_size_attribute_elems = ['table', 'th', 'td', 'hr', 'pre'];
            if (property_exists($node, 'tagName') && in_array($node->tagName, $deprecated_size_attribute_elems)) {
                $node->removeAttribute('width');
                $node->removeAttribute('height');
            }
        }

        $cur = $node->firstChild;
        while ($cur !== null) {
            $this->_cleanStyles($cur);
            $cur = $cur->nextSibling;
        }
    }

    /**
     * Clean out elements whose id/class combinations match specific string.
     *
     * @param $node DOMElement Node to clean
     * @param $regex string Match id/class combination.
     *
     * @return void
     **/
    public function _cleanMatchedNodes($node, $regex)
    {
        $endOfSearchMarkerNode = NodeUtility::getNextNode($node, true);
        $next = NodeUtility::getNextNode($node);
        while ($next && $next !== $endOfSearchMarkerNode) {
            if (preg_match($regex, sprintf('%s %s', $next->getAttribute('class'), $next->getAttribute('id')))) {
                $this->logger->debug(sprintf('Removing matched node with regex: \'%s\', node class was: \'%s\', id: \'%s\'', $regex, $next->getAttribute('class'), $next->getAttribute('id')));
                $next = NodeUtility::removeAndGetNext($next);
            } else {
                $next = NodeUtility::getNextNode($next);
            }
        }
    }

    /**
     * @param DOMDocument $article
     *
     * @return void
     */
    public function _cleanExtraParagraphs(DOMDocument $article)
    {
        $paragraphs = $article->getElementsByTagName('p');
        $length = $paragraphs->length;

        for ($i = 0; $i < $length; $i++) {
            $paragraph = $paragraphs->item($length - 1 - $i);

            $imgCount = $paragraph->getElementsByTagName('img')->length;
            $embedCount = $paragraph->getElementsByTagName('embed')->length;
            $objectCount = $paragraph->getElementsByTagName('object')->length;
            // At this point, nasty iframes have been removed, only remain embedded video ones.
            $iframeCount = $paragraph->getElementsByTagName('iframe')->length;
            $totalCount = $imgCount + $embedCount + $objectCount + $iframeCount;

            if ($totalCount === 0 && !preg_replace(NodeUtility::$regexps['onlyWhitespace'], '', $paragraph->textContent)) {
                $this->logger->debug(sprintf('[PrepArticle] Removing extra paragraph. Text content was: \'%s\'', substr($paragraph->textContent, 0, 128)));
                $paragraph->parentNode->removeChild($paragraph);
            }
        }
    }

    /**
     * @param DOMDocument $article
     *
     * @return void
     */
    public function _cleanConditionally(DOMDocument $article, $tag)
    {
        if (!$this->configuration->getCleanConditionally()) {
            return;
        }

        $isList = in_array($tag, ['ul', 'ol']);

        /*
         * Gather counts for other typical elements embedded within.
         * Traverse backwards so we can remove nodes at the same time
         * without effecting the traversal.
         */

        $DOMNodeList = $article->getElementsByTagName($tag);
        $length = $DOMNodeList->length;
        for ($i = 0; $i < $length; $i++) {
            /** @var $node DOMElement */
            $node = $DOMNodeList->item($length - 1 - $i);

            // First check if we're in a data table, in which case don't remove us.
            if ($node->hasAncestorTag($node, 'table', -1) && $node->isReadabilityDataTable()) {
                continue;
            }

            $weight = 0;
            if ($this->configuration->getWeightClasses()) {
                $weight = $node->getClassWeight();
            }

            if ($weight < 0) {
                $this->logger->debug(sprintf('[PrepArticle] Removing tag \'%s\' with 0 or less weight', $tag));

                NodeUtility::removeNode($node);
                continue;
            }

            if (substr_count($node->getTextContent(), ',') < 10) {
                /*
                 * If there are not very many commas, and the number of
                 * non-paragraph elements is more than paragraphs or other
                 * ominous signs, remove the element.
                 */

                $p = $node->getElementsByTagName('p')->length;
                $img = $node->getElementsByTagName('img')->length;
                $li = $node->getElementsByTagName('li')->length - 100;
                $input = $node->getElementsByTagName('input')->length;

                $embedCount = 0;
                $embeds = $node->getElementsByTagName('embed');

                foreach ($embeds as $embedNode) {
                    if (preg_match(NodeUtility::$regexps['videos'], $embedNode->C14N())) {
                        $embedCount++;
                    }
                }

                $linkDensity = $node->getLinkDensity();
                $contentLength = mb_strlen($node->getTextContent(true));

                $haveToRemove =
                    ($img > 1 && $p / $img < 0.5 && !$node->hasAncestorTag($node, 'figure')) ||
                    (!$isList && $li > $p) ||
                    ($input > floor($p / 3)) ||
                    (!$isList && $contentLength < 25 && ($img === 0 || $img > 2) && !$node->hasAncestorTag($node, 'figure')) ||
                    (!$isList && $weight < 25 && $linkDensity > 0.2) ||
                    ($weight >= 25 && $linkDensity > 0.5) ||
                    (($embedCount === 1 && $contentLength < 75) || $embedCount > 1);

                if ($haveToRemove) {
                    $this->logger->debug(sprintf('[PrepArticle] Removing tag \'%s\'.', $tag));

                    NodeUtility::removeNode($node);
                }
            }
        }
    }

    /**
     * Clean a node of all elements of type "tag".
     * (Unless it's a youtube/vimeo video. People love movies.).
     *
     * @param $article DOMDocument
     * @param $tag string tag to clean
     *
     * @return void
     **/
    public function _clean(DOMDocument $article, $tag)
    {
        $isEmbed = in_array($tag, ['object', 'embed', 'iframe']);

        $DOMNodeList = $article->getElementsByTagName($tag);
        $length = $DOMNodeList->length;
        for ($i = 0; $i < $length; $i++) {
            $item = $DOMNodeList->item($length - 1 - $i);

            // Allow youtube and vimeo videos through as people usually want to see those.
            if ($isEmbed) {
                $attributeValues = [];
                foreach ($item->attributes as $name => $value) {
                    $attributeValues[] = $value->nodeValue;
                }
                $attributeValues = implode('|', $attributeValues);

                // First, check the elements attributes to see if any of them contain youtube or vimeo
                if (preg_match(NodeUtility::$regexps['videos'], $attributeValues)) {
                    continue;
                }

                // Then check the elements inside this element for the same.
                if (preg_match(NodeUtility::$regexps['videos'], $item->C14N())) {
                    continue;
                }
            }
            $this->logger->debug(sprintf('[PrepArticle] Removing node \'%s\'.', $item->tagName));

            NodeUtility::removeNode($item);
        }
    }

    /**
     * Clean out spurious headers from an Element. Checks things like classnames and link density.
     *
     * @param DOMDocument $article
     *
     * @return void
     **/
    public function _cleanHeaders(DOMDocument $article)
    {
        for ($headerIndex = 1; $headerIndex < 3; $headerIndex++) {
            $headers = $article->getElementsByTagName('h' . $headerIndex);
            /** @var $header DOMElement */
            foreach ($headers as $header) {
                $weight = 0;
                if ($this->configuration->getWeightClasses()) {
                    $weight = $header->getClassWeight();
                }

                if ($weight < 0) {
                    $this->logger->debug(sprintf('[PrepArticle] Removing H node with 0 or less weight. Content was: \'%s\'', substr($header->nodeValue, 0, 128)));

                    NodeUtility::removeNode($header);
                }
            }
        }
    }

    /**
     * Removes the class="" attribute from every element in the given
     * subtree.
     *
     * Readability.js has a special filter to avoid cleaning the classes that the algorithm adds. We don't add classes
     * here so no need to filter those.
     *
     * @param DOMDocument|DOMNode $node
     *
     * @return void
     **/
    public function _cleanClasses($node)
    {
        if ($node->getAttribute('class') !== '') {
            $node->removeAttribute('class');
        }

        for ($node = $node->firstChild; $node !== null; $node = $node->nextSibling) {
            $this->_cleanClasses($node);
        }
    }

    /**
     * @param DOMDocument $article
     *
     * @return DOMDocument
     */
    public function postProcessContent(DOMDocument $article)
    {
        $this->logger->info('[PostProcess] PostProcessing content...');

        // Readability cannot open relative uris so we convert them to absolute uris.
        if ($this->configuration->getFixRelativeURLs()) {
            foreach (iterator_to_array($article->getElementsByTagName('a')) as $link) {
                /** @var DOMElement $link */
                $href = $link->getAttribute('href');
                if ($href) {
                    // Replace links with javascript: URIs with text content, since
                    // they won't work after scripts have been removed from the page.
                    if (strpos($href, 'javascript:') === 0) {
                        $this->logger->debug(sprintf('[PostProcess] Removing \'javascript:\' link. Content is: \'%s\'', substr($link->textContent, 0, 128)));

                        $text = $article->createTextNode($link->textContent);
                        $link->parentNode->replaceChild($text, $link);
                    } else {
                        $this->logger->debug(sprintf('[PostProcess] Converting link to absolute URI: \'%s\'', substr($href, 0, 128)));

                        $link->setAttribute('href', $this->toAbsoluteURI($href));
                    }
                }
            }

            foreach ($article->getElementsByTagName('img') as $img) {
                /** @var DOMElement $img */
                /*
                 * Extract all possible sources of img url and select the first one on the list.
                 */
                $url = [
                    $img->getAttribute('src'),
                    $img->getAttribute('data-src'),
                    $img->getAttribute('data-original'),
                    $img->getAttribute('data-orig'),
                    $img->getAttribute('data-url')
                ];

                $src = array_filter($url);
                $src = reset($src);
                if ($src) {
                    $this->logger->debug(sprintf('[PostProcess] Converting image URL to absolute URI: \'%s\'', substr($src, 0, 128)));

                    $img->setAttribute('src', $this->toAbsoluteURI($src));
                }
            }
        }

        $this->_cleanClasses($article);

        return $article;
    }

    /**
     * @return null|string
     */
    public function __toString()
    {
        return sprintf('<h1>%s</h1>%s', $this->getTitle(), $this->getContent());
    }

    /**
     * @return string|null
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    protected function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string|null
     */
    public function getContent()
    {
        return ($this->content instanceof DOMDocument) ? $this->content->C14N() : null;
    }

    /**
     * @return DOMDocument|null
     */
    public function getDOMDocument()
    {
        return $this->content;
    }

    /**
     * @param DOMDocument $content
     */
    protected function setContent(DOMDocument $content)
    {
        $this->content = $content;
    }

    /**
     * @return null|string
     */
    public function getExcerpt()
    {
        return $this->excerpt;
    }

    /**
     * @param null|string $excerpt
     */
    public function setExcerpt($excerpt)
    {
        $this->excerpt = $excerpt;
    }

    /**
     * @return string|null
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * @param string $image
     */
    protected function setImage($image)
    {
        $this->image = $image;
    }

    /**
     * @return string|null
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @param string $author
     */
    protected function setAuthor($author)
    {
        $this->author = $author;
    }

    /**
     * @return null|string
     */
    public function getDirection()
    {
        return $this->direction;
    }

    /**
     * @param null|string $direction
     */
    public function setDirection($direction)
    {
        $this->direction = $direction;
    }
}
