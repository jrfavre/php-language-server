<?php

namespace LanguageServer\Factory;

use LanguageServerProtocol\Location;
use LanguageServerProtocol\SymbolInformation;
use LanguageServerProtocol\SymbolKind;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Range;
use Microsoft\PhpParser\ResolvedName;
use LanguageServer\Factory\LocationFactory;
use phpDocumentor\Reflection\DocBlock\Tags\{BaseTag, Method};

class SymbolInformationFactory
{
    /**
     * Converts a Node to a SymbolInformation
     *
     * @param Node $node
     * @param string $fqn If given, $containerName will be extracted from it
     * @return SymbolInformation|null
     */
    public static function fromNode($node, string $fqn = null)
    {
        $symbol = new SymbolInformation();
        if ($node instanceof Node\Statement\ClassDeclaration) {
            $symbol->kind = SymbolKind::CLASS_;
        } else if ($node instanceof Node\Statement\TraitDeclaration) {
            $symbol->kind = SymbolKind::CLASS_;
        } else if (\LanguageServer\ParserHelpers\isConstDefineExpression($node)) {
            // constants with define() like
            // define('TEST_DEFINE_CONSTANT', false);
            $symbol->kind = SymbolKind::CONSTANT;
            $symbol->name = $node->argumentExpressionList->children[0]->expression->getStringContentsText();
        } else if ($node instanceof Node\Statement\InterfaceDeclaration) {
            $symbol->kind = SymbolKind::INTERFACE;
        } else if ($node instanceof Node\Statement\NamespaceDefinition) {
            $symbol->kind = SymbolKind::NAMESPACE;
        } else if ($node instanceof Node\Statement\FunctionDeclaration) {
            $symbol->kind = SymbolKind::FUNCTION;
        } else if ($node instanceof Node\MethodDeclaration) {
            $nameText = $node->getName();
            if ($nameText === '__construct' || $nameText === '__destruct') {
                $symbol->kind = SymbolKind::CONSTRUCTOR;
            } else {
                $symbol->kind = SymbolKind::METHOD;
            }
        } else if ($node instanceof Node\Expression\Variable && $node->getFirstAncestor(Node\PropertyDeclaration::class) !== null) {
            $symbol->kind = SymbolKind::PROPERTY;
        } else if ($node instanceof Node\ConstElement) {
            $symbol->kind = SymbolKind::CONSTANT;
        } else if (
            (
                ($node instanceof Node\Expression\AssignmentExpression)
                && $node->leftOperand instanceof Node\Expression\Variable
            )
            || $node instanceof Node\UseVariableName
            || $node instanceof Node\Parameter
        ) {
            $symbol->kind = SymbolKind::VARIABLE;
        } else {
            return null;
        }

        if ($node instanceof Node\Expression\AssignmentExpression) {
            if ($node->leftOperand instanceof Node\Expression\Variable) {
                $symbol->name = $node->leftOperand->getName();
            } elseif ($node->leftOperand instanceof PhpParser\Token) {
                $symbol->name = trim($node->leftOperand->getText($node->getFileContents()), "$");
            }
        } else if ($node instanceof Node\UseVariableName) {
            $symbol->name = $node->getName();
        } else if (isset($node->name)) {
            if ($node->name instanceof Node\QualifiedName) {
                $symbol->name = (string)ResolvedName::buildName($node->name->nameParts, $node->getFileContents());
            } else {
                $symbol->name = ltrim((string)$node->name->getText($node->getFileContents()), "$");
            }
        } else if (isset($node->variableName)) {
            $symbol->name = $node->variableName->getText($node);
        } else if (!isset($symbol->name)) {
            return null;
        }

        $symbol->location = LocationFactory::fromNode($node);
        if ($fqn !== null) {
            $symbol->containerName = self::getContainerName($fqn);
        }
        return $symbol;
    }

    /**
     * Converts a DocBlock Property to a SymbolInformation
     *
     * @param Property $property
     * @param string $fqn
     * @return SymbolInformation|null
     */
    public static function fromDocBlockTag(BaseTag $tag, Range $position, string $fqn, Node\Statement\ClassDeclaration $node)
    {
        $symbol = new SymbolInformation();
        $symbol->name = ($tag instanceof Method) ? $tag->getMethodName() : $tag->getVariableName();
        $symbol->kind = ($tag instanceof Method) ? SymbolKind::METHOD : SymbolKind::PROPERTY;
        $symbol->location = LocationFactory::fromUriAndRange($node->getUri(), $position);
        $symbol->containerName = self::getContainerName($fqn);
        return $symbol;
    }

    private static function getContainerName(string $fqn): string
    {
        $parts = preg_split('/(::|->|\\\\)/', $fqn);
        array_pop($parts);
        return implode('\\', $parts);
    }
}
