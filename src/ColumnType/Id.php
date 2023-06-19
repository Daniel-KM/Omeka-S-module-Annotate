<?php declare(strict_types=1);

namespace Annotate\ColumnType;

class Id extends \Omeka\ColumnType\Id
{
    public function getLabel() : string
    {
        return 'Annotation id'; // @translate
    }

    public function getResourceTypes() : array
    {
        return [
            'annotations',
        ];
    }
}
