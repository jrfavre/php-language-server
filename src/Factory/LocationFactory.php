<?php

namespace LanguageServer\Factory;

use LanguageServerProtocol\Location;
use LanguageServerProtocol\Position;
use LanguageServerProtocol\Range;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Range as ParserRange;
use Microsoft\PhpParser\PositionUtilities;
use phpDocumentor\Reflection\DocBlock\Tags\Property;

class LocationFactory
{
    /**
     * Returns the location of the node
     *
     * @param Node $node
     * @return self
     */
    public static function fromNode(Node $node): Location
    {
        $range = PositionUtilities::getRangeFromPosition(
            $node->getStart(),
            $node->getWidth(),
            $node->getFileContents()
        );

        return self::fromUriAndRange($node->getUri(), $range);
    }

    /**
     * Returns the location of a DocBlock Property
     * @param string $uri
     * @param Range $range
     * @return self
     */
    public static function fromUriAndRange(string $uri, ParserRange $range): Location
    {
        return new Location($uri, new Range(
            new Position($range->start->line, $range->start->character),
            new Position($range->end->line, $range->end->character)
        ));
    }
}
