<?php

namespace andreskrey\Readability\Nodes;

use andreskrey\Readability\Nodes\DOM\DOMDocument;
use andreskrey\Readability\Nodes\DOM\DOMElement;
use andreskrey\Readability\Nodes\DOM\DOMNode;
use andreskrey\Readability\Nodes\DOM\DOMText;

/**
 * @method \DOMNode removeAttribute($name)
 */
trait NodeTrait
{
    /**
     * Content score of the node. Used to determine the value of the content.
     *
     * @var int
     */
    public $contentScore = 0;

    /**
     * Flag for initialized status.
     *
     * @var bool
     */
    private $initialized = false;

    /**
     * Flag data tables.
     *
     * @var bool
     */
    private $readabilityDataTable = false;

    /**
     * @var array
     */
    private $divToPElements = [
        'a',
        'blockquote',
        'dl',
        'div',
        'img',
        'ol',
        'p',
        'pre',
        'table',
        'ul',
        'select',
    ];

    /**
     * initialized getter.
     *
     * @return bool
     */
    public function isInitialized()
    {
        return $this->initialized;
    }

    /**
     * @return bool
     */
    public function isReadabilityDataTable()
    {
        return $this->readabilityDataTable;
    }

    /**
     * @param bool $param
     */
    public function setReadabilityDataTable($param)
    {
        $this->readabilityDataTable = $param;
    }

    /**
     * Initializer. Calculates the current score of the node and returns a full Readability object.
     *
     * @ TODO: I don't like the weightClasses param. How can we get the config here?
     *
     * @param $weightClasses bool Weight classes?
     *
     * @return static
     */
    public function initializeNode($weightClasses)
    {
        if (!$this->isInitialized()) {
            $contentScore = 0;

            switch ($this->nodeName) {
                case 'div':
                    $contentScore += 5;
                    break;

                case 'pre':
                case 'td':
                case 'blockquote':
                    $contentScore += 3;
                    break;

                case 'address':
                case 'ol':
                case 'ul':
                case 'dl':
                case 'dd':
                case 'dt':
                case 'li':
                case 'form':
                    $contentScore -= 3;
                    break;

                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                case 'th':
                    $contentScore -= 5;
                    break;
            }

            $this->contentScore = $contentScore + ($weightClasses ? $this->getClassWeight() : 0);

            $this->initialized = true;
        }

        return $this;
    }

    /**
     * Override for native getAttribute method. Some nodes have the getAttribute method, some don't, so we need
     * to check first the existence of the attributes property.
     *
     * @param $attributeName string Attribute to retrieve
     *
     * @return string
     */
    public function getAttribute($attributeName)
    {
        if (!is_null($this->attributes)) {
            return parent::getAttribute($attributeName);
        }

        return '';
    }

    /**
     * Get the ancestors of the current node.
     *
     * @param int|bool $maxLevel Max amount of ancestors to get. False for all of them
     *
     * @return array
     */
    public function getNodeAncestors($maxLevel = 3)
    {
        $ancestors = [];
        $level = 0;

        $node = $this->parentNode;

        while ($node && !($node instanceof DOMDocument)) {
            $ancestors[] = $node;
            $level++;
            if ($level === $maxLevel) {
                break;
            }
            $node = $node->parentNode;
        }

        return $ancestors;
    }

    /**
     * Returns all links from the current element.
     *
     * @return array
     */
    public function getAllLinks()
    {
        return iterator_to_array($this->getElementsByTagName('a'));
    }

    /**
     * Get the density of links as a percentage of the content
     * This is the amount of text that is inside a link divided by the total text in the node.
     *
     * @return int
     */
    public function getLinkDensity()
    {
        $linkLength = 0;
        $textLength = mb_strlen($this->getTextContent(true));

        if (!$textLength) {
            return 0;
        }

        $links = $this->getAllLinks();

        if ($links) {
            /** @var DOMElement $link */
            foreach ($links as $link) {
                $linkLength += mb_strlen($link->getTextContent(true));
            }
        }

        return $linkLength / $textLength;
    }

    /**
     * Calculates the weight of the class/id of the current element.
     *
     * @return int
     */
    public function getClassWeight()
    {
        $weight = 0;

        // Look for a special classname
        $class = $this->getAttribute('class');
        if (trim($class)) {
            if (preg_match(NodeUtility::$regexps['negative'], $class)) {
                $weight -= 25;
            }

            if (preg_match(NodeUtility::$regexps['positive'], $class)) {
                $weight += 25;
            }
        }

        // Look for a special ID
        $id = $this->getAttribute('id');
        if (trim($id)) {
            if (preg_match(NodeUtility::$regexps['negative'], $id)) {
                $weight -= 25;
            }

            if (preg_match(NodeUtility::$regexps['positive'], $id)) {
                $weight += 25;
            }
        }

        return $weight;
    }

    /**
     * Returns the full text of the node.
     *
     * @param bool $normalize Normalize white space?
     *
     * @return string
     */
    public function getTextContent($normalize = false)
    {
        $nodeValue = $this->nodeValue;
        if ($normalize) {
            $nodeValue = trim(preg_replace('/\s{2,}/', ' ', $nodeValue));
        }

        return $nodeValue;
    }

    /**
     * Returns the children of the current node.
     *
     * @param bool $filterEmptyDOMText Filter empty DOMText nodes?
     *
     * @return array
     */
    public function getChildren($filterEmptyDOMText = false)
    {
        $ret = iterator_to_array($this->childNodes);
        if ($filterEmptyDOMText) {
            // Array values is used to discard the key order. Needs to be 0 to whatever without skipping any number
            $ret = array_values(array_filter($ret, function ($node) {
                return $node->nodeName !== '#text' || mb_strlen(trim($node->nodeValue));
            }));
        }

        return $ret;
    }

    /**
     * Return an array indicating how many rows and columns this table has.
     *
     * @return array
     */
    public function getRowAndColumnCount()
    {
        $rows = $columns = 0;
        $trs = $this->getElementsByTagName('tr');
        foreach ($trs as $tr) {
            /** @var \DOMElement $tr */
            $rowspan = $tr->getAttribute('rowspan');
            $rows += ($rowspan || 1);

            // Now look for column-related info
            $columnsInThisRow = 0;
            $cells = $tr->getElementsByTagName('td');
            foreach ($cells as $cell) {
                /** @var \DOMElement $cell */
                $colspan = $cell->getAttribute('colspan');
                $columnsInThisRow += ($colspan || 1);
            }
            $columns = max($columns, $columnsInThisRow);
        }

        return ['rows' => $rows, 'columns' => $columns];
    }

    /**
     * Creates a new node based on the text content of the original node.
     *
     * @param $originalNode DOMNode
     * @param $tagName string
     *
     * @return DOMElement
     */
    public function createNode($originalNode, $tagName)
    {
        $text = $originalNode->getTextContent();
        $newNode = $originalNode->ownerDocument->createElement($tagName, $text);

        return $newNode;
    }

    /**
     * Check if a given node has one of its ancestor tag name matching the
     * provided one.
     *
     * @param DOMElement $node
     * @param string $tagName
     * @param int $maxDepth
     *
     * @return bool
     */
    public function hasAncestorTag($node, $tagName, $maxDepth = 3)
    {
        $depth = 0;
        while ($node->parentNode) {
            if ($maxDepth > 0 && $depth > $maxDepth) {
                return false;
            }
            if ($node->parentNode->nodeName === $tagName) {
                return true;
            }
            $node = $node->parentNode;
            $depth++;
        }

        return false;
    }

    /**
     * Checks if the current node has a single child and if that child is a P node.
     * Useful to convert <div><p> nodes to a single <p> node and avoid confusing the scoring system since div with p
     * tags are, in practice, paragraphs.
     *
     * @param DOMNode $node
     *
     * @return bool
     */
    public function hasSinglePNode()
    {
        // There should be exactly 1 element child which is a P:
        if (count($children = $this->getChildren(true)) !== 1 || $children[0]->nodeName !== 'p') {
            return false;
        }

        // And there should be no text nodes with real content (param true on ->getChildren)
        foreach ($children as $child) {
            /** @var $child DOMNode */
            if ($child->nodeType === XML_TEXT_NODE && !preg_match('/\S$/', $child->getTextContent())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the current element has a single child block element.
     * Block elements are the ones defined in the divToPElements array.
     *
     * @return bool
     */
    public function hasSingleChildBlockElement()
    {
        $result = false;
        if ($this->hasChildNodes()) {
            foreach ($this->getChildren() as $child) {
                if (in_array($child->nodeName, $this->divToPElements)) {
                    $result = true;
                } else {
                    // If any of the hasSingleChildBlockElement calls return true, return true then.
                    /** @var $child DOMElement */
                    $result = ($result || $child->hasSingleChildBlockElement());
                }
            }
        }

        return $result;
    }

    /**
     * Determines if a node has no content or it is just a bunch of dividing lines and/or whitespace.
     *
     * @return bool
     */
    public function isElementWithoutContent()
    {
        return $this instanceof DOMElement &&
            mb_strlen(preg_replace(NodeUtility::$regexps['onlyWhitespace'], '', $this->textContent)) === 0 &&
            ($this->childNodes->length === 0 ||
                $this->childNodes->length === $this->getElementsByTagName('br')->length + $this->getElementsByTagName('hr')->length
                /*
                 * Special PHP DOMDocument case: We also need to count how many DOMText we have inside the node.
                 * If there's an empty tag with an space inside and a BR (for example "<p> <br/></p>) counting only BRs and
                 * HRs will will say that the example has 2 nodes, instead of one. This happens because in DOMDocument,
                 * DOMTexts are also nodes (which doesn't happen in JS). So we need to also count how many DOMText we
                 * are dealing with (And at this point we know they are empty or are just whitespace, because of the
                 * mb_strlen in this chain of checks).
                 */
                + count(array_filter(iterator_to_array($this->childNodes), function ($child) {
                    return $child instanceof DOMText;
                }))

            );
    }
}
