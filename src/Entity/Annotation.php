<?php declare(strict_types=1);

namespace Annotate\Entity;

use Omeka\Entity\Resource;

/**
 * @Entity
 */
class Annotation extends Resource
{
    /**
     * @Id
     * @Column(type="integer")
     */
    protected $id;

    public function getResourceName()
    {
        return 'annotations';
    }
}
