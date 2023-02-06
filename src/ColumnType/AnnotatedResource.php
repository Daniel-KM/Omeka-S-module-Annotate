<?php declare(strict_types=1);

namespace Annotate\ColumnType;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\ColumnType\ColumnTypeInterface;

class AnnotatedResource implements ColumnTypeInterface
{
    public function getLabel() : string
    {
        return 'Annotated resource'; // @translate
    }

    public function getResourceTypes() : array
    {
        return [
            'annotations',
        ];
    }

    public function getMaxColumns() : ?int
    {
        return 1;
    }

    public function renderDataForm(PhpRenderer $view, array $data) : string
    {
        return '';
    }

    public function getSortBy(array $data) : ?string
    {
        return 'resource_id';
    }

    public function renderHeader(PhpRenderer $view, array $data) : string
    {
        return $this->getLabel();
    }

    public function renderContent(PhpRenderer $view, AbstractEntityRepresentation $resource, array $data) : ?string
    {
        /** @var \Annotate\Api\Representation\AnnotationRepresentation $resource */
        $annotatedResource = $resource->primaryTargetSource();
        return $annotatedResource ? $annotatedResource->linkPretty() : null;
    }
}
